<?php

use Luracast\Restler\RestException;

class ReturnObject
{
	public $data = null;
	public $message = "";
	public $error = null;

	/**
	 * Status code of the error
	 * 
	 * @var int
	 */
	public $status;

	public $errors = [];
	public $debug = null;

	/**
	 * @param mixed $data Data to return
	 * @param mixed $error Error to throw
	 * @param string $message Error message
	 * @param int $status Error status code
	 */
	public function __construct($data = [], $error = null, $message = "", $status = 500, $errors = [], $debug = null)
	{
		$this->data = $data;
		$this->errors = $errors;
		$this->setDebug($debug);
		$this->setError($error, $message, $status);
	}

	/**
	 * `$this->error` setter
	 * 
	 * Appens the error to the errors array
	 * 
	 * @param mixed $error Error to throw
	 * @param string $message Error message
	 * @param int $status Error status code
	 * 
	 * @return $this
	 */
	public function setError($error = null, $message = "", $status = 500)
	{
		$this->error = $error;
		$this->message = $message;
		$this->status = $status;
		$this->errors[] = $error;
		return $this;
	}

	/**
	 * `$this->debug` setter
	 * 
	 * @param mixed $debug Debug data
	 * 
	 * @return $this
	 */
	public function setDebug($debug = null)
	{
		if ($debug) {
			$this->debug = $debug;
			return;
		}

		$stack = debug_backtrace(2);
		$output = '';

		$stackLen = count($stack);
		for ($i = 1; $i < $stackLen; $i++) {
			$entry = $stack[$i];

			$func = $entry['function'] . '(';
			if (!empty($entry["args"])) {
				$argsLen = count($entry['args']);
				for ($j = 0; $j < $argsLen; $j++) {
					$my_entry = $entry['args'][$j];
					if (is_string($my_entry)) {
						$func .= $my_entry;
					}
					if ($j < $argsLen - 1) $func .= ', ';
				}
			}
			$func .= ')';

			$entry_file = 'NO_FILE';
			if (array_key_exists('file', $entry)) {
				$entry_file = $entry['file'];
			}
			$entry_line = 'NO_LINE';
			if (array_key_exists('line', $entry)) {
				$entry_line = $entry['line'];
			}
			$output .= $entry_file . ':' . $entry_line . ' - ' . $func . PHP_EOL;
		}

		$this->debug = explode(PHP_EOL, $output);
		return $this;
	}

	/**
	 * Create a new `ReturnObject` instance with only the success data
	 * 
	 * @param mixed $data Data to return
	 * 
	 * @return ReturnObject
	 */
	public static function success($data = [])
	{
		return new ReturnObject($data);
	}

	/**
	 * Create a new `ReturnObject` instance with only the error data
	 * 
	 * @param mixed $error Error to throw
	 * @param string $message Error message
	 * @param int $status Error status code
	 * 
	 * @return ReturnObject
	 */
	public static function error($error, $message = "", $status = 500, $errors = [])
	{
		return new ReturnObject(null, $error, $message, $status, $errors);
	}

	/**
	 * Returns the data or throw the error
	 * 
	 * @param bool $isDebug If true, return `$this`
	 * 
	 * @return mixed
	 * 
	 * @throws RestException
	 */
	public function send($isDebug = false)
	{
		if ($isDebug) {
			return $this;
		}
		if ($this->error) {
			throw new RestException($this->status, $this->message, [$this->errors]);
		}
		return $this->data;
	}
}
