<?php
/*
	Original name: CharacterSet.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Character set conversions
 */

namespace Softneta\MedDream\Core;


/** @brief Conversions from/to UTF-8 */
class CharacterSet implements Configurable
{
	/** @brief (bool) Database fully supports UTF-8.

		For MySQL this means that variables 'character_set_%' are 'utf8'.
	 */
	const DB_IS_UNICODE = false;


	protected $log;         /**< @brief An instance of Logging */
	protected $cnf;         /**< @brief An instance of Configuration */

	/** @brief (string) A copy of <tt>$dbms</tt> from config.php.

		Some DBMSes do not support some encodings, and based on this we can make shortcuts.
	 */
	public $dbms = '';

	/** @brief (string) %Database encoding and default DICOM file encoding */
	public $defaultCharSet = '';
	/** @brief (array) supported DICOM encodings */
	public $supportedCharset = array(
		'ISO_IR 192',
		'ISO_IR 100', 'ISO_IR 101', 'ISO_IR 109',
		'ISO_IR 110', 'ISO_IR 144', 'ISO_IR 127',
		'ISO_IR 126', 'ISO_IR 138', 'ISO_IR 148',
		'ISO_IR 13', 'ISO_IR 166', 'ISO_IR 6',
		'ISO 2022 IR 100', 'ISO 2022 IR 101', 'ISO 2022 IR 109',
		'ISO 2022 IR 110', 'ISO 2022 IR 144', 'ISO 2022 IR 127',
		'ISO 2022 IR 126', 'ISO 2022 IR 138', 'ISO 2022 IR 148',
		'ISO 2022 IR 13', 'ISO 2022 IR 166', 'ISO 2022 IR 6',
		'ISO 2022 IR 87', 'ISO 2022 IR 159', 'ISO 2022 IR 149'
	);
	/** @brief (array) charset used for annotation */
	public $defaultAnnotationCharSet = '';

	public function __construct(Logging $log = null, Configuration $cnf = null)
	{
		if (!function_exists('iconv'))
		{
			$err = 'iconv support is missing';
			$log->asErr('fatal: ' . $err);
			exit($err);
		}

		if (is_null($log))
			$log = new Logging();
		$this->log = $log;

		if (is_null($cnf))
		{
			$cnf = new Configuration();
			$err = $cnf->load();
			if (strlen($err))
			{
				$log->asErr('fatal: ' . $err);
				exit($err);
			}
		}
		$this->cnf = $cnf;
	}


	public function configure()
	{
		$cfg = $this->cnf->data;

		/* $default_character_set: simply imported */
		if (isset($cfg['default_character_set']))
			$this->defaultCharSet = trim($cfg['default_character_set']);
		if (isset($cfg['default_annotation_character_set']))
			$this->defaultAnnotationCharSet = trim($cfg['default_annotation_character_set']);
		/* $dbms: simply imported

			Can't require a non-empty string as pseudo-PACSes like FileSystem do not use
			a database anyway.
		 */
		if (isset($cfg['dbms']))
			$this->dbms = strtoupper($cfg['dbms']);

		return '';
	}


	/** @brief Conditional encode of database content to UTF-8.

		Does nothing in case of MWS+SQLite3 or @link DB_IS_UNICODE @endlink, as data in the
		database is assumed to be already in UTF-8.

		Latin1 is assumed for input character set, and can be overridden by $default_character_set
		(config.php).
	 */
	public function utf8Encode($value)
	{
		if(empty($value))
			return $value;
		
		if ((($this->dbms == "SQLITE3") && Constants::FOR_WORKSTATION) || self::DB_IS_UNICODE)
			return $value;

		$encoding = $this->getPHPEncoding($this->defaultCharSet);
		if (!empty($encoding))
		{
			$result = @iconv($encoding, 'utf-8//IGNORE//TRANSLIT', $value);
			$this->log->asDump('@iconv("'. $encoding.'", \'utf-8//IGNORE//TRANSLIT\', "'.$value.'")');
			if (strlen($result) == 0)
				$result = utf8_encode($value);
			return $result;
		}
		else
			return utf8_encode($value);
	}


	/** @brief Conditional decode from UTF-8 to database content.

		Does nothing in case of MWS+SQLite3 or @link DB_IS_UNICODE @endlink, as data in the
		database is assumed to be already in UTF-8.
	 */
	public function utf8Decode($value)
	{
		if ((($this->dbms == "SQLITE3") && Constants::FOR_WORKSTATION) || self::DB_IS_UNICODE)
			return $value;

		return utf8_decode($value);
	}


	/** @brief Encode value of a DICOM tag from the given charset to UTF-8.

		@param string $charSet  Assumed DICOM Specific Character Set
		@param string $value    String to convert

		@return string  Converted value

		Tries to encode @p $value from @p $charSet (if empty, @link $defaultCharSet @endlink is
		assumed) to UTF-8. In case of iconv failure, falls back to utf8_encode so that something
		is still visible.

		If both @p $charSet and @p $defaultCharSet are empty, <tt>ISO-IR 6</tt> is assumed.
	 */
	public function encodeWithCharset($charSet, $value)
	{
		if (empty($charSet))
		{
			$charSet = $this->defaultCharSet;
			if (empty($charSet))
				$charSet = 'ISO-IR-6';
		}

		$encoding = $this->getPHPEncoding($charSet);
		$this->log->asDump(__METHOD__ . ": using the charset '$encoding'");
		if($encoding != 'UTF-8')
		{
			$result = @iconv($encoding, 'UTF-8//TRANSLIT//IGNORE', $value);
			/* if failed to encode (result is empty), then force utf-8 for viewer */
			$tmp = str_replace(array('^', '.'), '', $result);
			/* those characters might remain even after unsuccessful conversion */
			if (strlen($tmp) == 0)
			{
				$this->log->asErr("failed to convert from '$encoding': '$value'");
				$value = utf8_encode($value);
			}
			else
				$value = $result;
		}
		return $value;
	}
	/** @brief see if $charSet or $default is valid, else sets error
	   
		@param string $charSet - dicom file charset
		@param string $default - charset from config(default)
		@return array array('error'=>'', 'charset'=>$charSet);
	 */
	public function validateCharSet($charSet, $default = '')
	{
		$return = array('error'=>'', 'charset'=>$charSet);
		if(empty($charSet))
		{
			$charSet = $default;
			if (empty($charSet))
			{
				$return['charset'] = 'ISO_IR 6';
				return $return;
			}
		}
		$encoding = str_replace(array('ISO-IR-', 'ISO-IR '), 'ISO_IR ', $charSet);
		if(!in_array($encoding, $this->supportedCharset))
			$return['error'] = 'Charset not supported:' . $charSet;
		else
			$return['charset'] = $charSet;
		return $return;
	}
	/** @brief get php iconv supported encoding
	   
		@param string $charSet
		@return string
	 */
	public function getPHPEncoding($charSet)
	{
		$encoding = str_replace(array('ISO_IR ', 'ISO-IR ', 'ISO 2022 IR '), 'ISO-IR-', $charSet);
		switch ($encoding)
		{
			case 'ISO-IR-13'://japan
				$encoding = 'CSHALFWIDTHKATAKANA';
				break;
			case 'ISO-IR-192':
				$encoding = 'UTF-8';
				break;
		}
		return $encoding;
	}
}
