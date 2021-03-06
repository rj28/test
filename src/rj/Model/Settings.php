<?php namespace Rj\Model;

use Rj\Assert,
	Phalcon\Mvc\Model as Phalcon_Model;

class Settings extends Phalcon_Model {

	public static $defaults = [
	];

	protected static $_settings = null;

	public static function get($key = null) {
		if (null === static::$_settings) {
			static::$_settings = [];
			foreach (static::find() as $entry) {
				static::$_settings[$entry->key] = $entry->value;
			}
		}

		if ($key) {
			return isset(static::$_settings[$key])
				? static::$_settings[$key]
				: (isset(static::$defaults[$key]) ? static::$defaults[$key] : null);

		} else {
			$ret = [];
			foreach (static::$defaults as $key => $_) {
				$ret[$key] = static::get($key);
			}
			return $ret;
		}
	}

	public static function set($key, $value) {
		$entry = static::findFirst([ 'key = ?0', 'bind' => [ $key ] ]);
		if ( ! $entry) {
			$entry = new static();
			$entry->key = $key;
		}

		$entry->save([ 'value' => $value ]);
		Assert::noMessages($entry);
	}

	public $key, $value;

}
