<?php namespace Rj;

use Rj\Migration\Table,
	Exception,
	Phalcon\DI;

class Migration {

	public static $_dir;

	public static function test() {
		if (file_exists(self::_getTableDataFileName())) {
			Assert::true(is_writeable(self::_getTableDataFileName()));

		} else {
			Assert::true(is_writeable(dirname(self::_getTableDataFileName())));
		}
	}

	public static function setDir($dir) {
		static::$_dir = $dir;
	}

	protected static function _dir() {
		return static::$_dir ?: DOCROOT . 'migrations/';
	}

	protected static function _getTableDataFileName() {
		return DOCROOT . '../.tableData';
	}

	protected static function _getNewMigrationFileName() {
		$fileName = 'migration_%s.php';
		$i        = date('Y-m-d_His');

		return sprintf($fileName, $i);
	}

	protected static function _readMeta() {
		//$raw = file_get_contents(static::_getTableDataFileName());
		/** @var Model\Settings $settings */
		$settings = DI::getDefault()['Settings'];
		$raw = gzdecode($settings::get('_database_struct'));

		list ($a1, $a2) = @ unserialize($raw);
		return [ $a1 ?: [], $a2 ?: [] ];
	}

	protected static function _writeMeta($tableData, $migrations) {
		//file_put_contents(static::_getTableDataFileName(), serialize([ $tableData, $migrations ]));
		/** @var Model\Settings $settings */
		$settings = DI::getDefault()['Settings'];
		$settings::set('_database_struct', gzencode(serialize([ $tableData, $migrations ]), 9));
	}

	public static function dump() {
		self::test();

		list ($td, $mg) = static::_readMeta();
		static::_writeMeta(Table::getList(), $mg);
	}

	public static function gen() {
		self::test();

		list ($old, $mg) = static::_readMeta();

		if ( ! is_array($old))
			$old = [];

		$new = Table::getList();

		$migration = '';

		foreach ($old as $table) {
			if (empty($new[$table->name])) {
				$migration .= "DROP TABLE `{$table->name}`;\n";
			}
		}

		foreach ($new as $table) {
			if (empty($old[$table->name])) {
				$migration .= $table->getCreateTable() . "\n";

			} else {
				$migration .= $table->diff($old[$table->name]);
			}
		}

		if ( ! trim($migration)) {
			echo "Nothing to do.\n";
			return;
		}

		$fileName = static::_getNewMigrationFileName();
		file_put_contents(static::_dir() . $fileName, "<?php\n\n\$db->execute(\"\n\t" . str_replace("\n", "\n\t", $migration) . "\n\");");

		echo $migration . "\n";
		echo "Created file $fileName\n";

		$mg[] = $fileName;
		static::_writeMeta($new, $mg);
	}

	public static function run($force = null) {
		self::test();

		list ($td, $mg) = static::_readMeta();
		$dir = static::_dir();

		$db = DI::getDefault()->getShared('db');
		$files = [];

		if ($handle = opendir($dir)) {
			while (false !== ($entry = readdir($handle))) {
				if ( ! is_dir($dir . $entry)) {
					$files[$entry] = [ $dir, $entry ];
				}
			}
		}

		ksort($files);

		foreach ($files as $row) {
			$dir   = $row[0];
			$entry = $row[1];

			try {
				if (false !== array_search($entry, $mg)) continue;

				echo "Executing $entry...\n";

				try {
                    include "{$dir}$entry";

                } catch (Exception $e) {
				    if ($force) {
				        echo $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";

                    } else {
				        throw $e;
                    }
                }

				$mg[] = $entry;
				static::_writeMeta($td, $mg);

			} catch (Exception $e) {
				throw $e;
			}
		}
	}

}
