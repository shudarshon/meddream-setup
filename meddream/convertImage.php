<?php
/*
	Original name: convertImage.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Converts an image file to TIFF format

	Depends on:
		convert/convert.exe
 */
class convertImage
{
	/**
	 * @var string $toolpath - tool convert.exe path name
	 * @var string $error - error from Exception during convert
	 * @var string $out - convert output(if exis any value)
	 */
	private static $toolpath = '';
	public static $error = '';
	public static $out = array();


	/**
	 * Set default parameters
	 */
	private static function setParameters()
	{
		self::$error = '';
		self::$toolpath = dirname(__FILE__) . DIRECTORY_SEPARATOR .
			'convert' . DIRECTORY_SEPARATOR . 'convert.exe';
	}


	/**
	 * Convert image to desired type
	 * creates the same filename but with other extention
	 *
	 * Dependant on convet/convert.exe
	 *
	 * @param type $path
	 * @param type $type
	 * @return string|boolean
	 */
	public static function convertToType($path, $type)
	{
		if (!file_exists($path))
		{
			self::$error = 'input file does not exist';
			return false;
		}

		self::setParameters();

		if (PHP_OS != "WINNT")
		{
			self::$error = 'supported only under Windows';
			return '';
		}
		$resultpath = self::formPathName($path, $type);
		$comand = '"'.self::$toolpath . '" "' . $path . '" "' . $resultpath . '" 2>&1';
		self::$error = self::tryExec($comand, $out);
		self::$out = implode("\n", $out);

		if (file_exists($resultpath))
			return $resultpath;

		return '';
	}


	/**
	 * Form new file path name with new extention
	 *
	 * @param type $path
	 * @param type $extention
	 * @return string
	 */
	private static function formPathName($path, $extention)
	{
		$extention = str_replace('.', '', $extention);
		$array = explode('.', $path);
		$pathextention = end($array);
		return dirname($path) . DIRECTORY_SEPARATOR .
			basename($path, $pathextention) . $extention;
	}


	/**
	 * Execute command line and return error or updates output
	 *
	 * @param array $comand - updates the sme value
	 * @param array $out
	 * @return string
	 */
	private static function tryExec($comand, &$out = array())
	{
		$out = array();
		if (trim($comand) == '')
			return '';

		set_time_limit(0);
		session_write_close();
		$error = '';
		try
		{
			exec($comand, $out);
		}
		catch (Exception $e)
		{
			$error = $e->getMessage();
		}

		return $error;
	}
}
?>