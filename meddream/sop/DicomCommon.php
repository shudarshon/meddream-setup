<?php
/*
	Original name: DicomCommon.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Common functions for creating a DICOM file
 */

namespace Softneta\MedDream\Core\SOP;
use Softneta\MedDream\Core\CharacterSet;

class DicomCommon
{
	public $error = '';
	public $data = array();
	public $characterSetClass = null;
	public $encoding = 'ISO_IR 6';
	
	public function getCharacterSet()
	{
		if(is_null($this->characterSetClass))
			$this->characterSetClass = new CharacterSet ();
		return $this->characterSetClass;
	}
	/**
     * test if body part exist and is valid
	 *
	 *@param string $key - body part key name
     *@return boolean true/false
     */
	function validateBodyPart($key)
	{
		if (isset($this->data[$key]))
			return in_array($this->data[$key], $this->bodyparts);
		return true;
	}
	/**
     * test if sex exist and is valid
	 *
	 *@param string $key - sex part key name
     *@return string 'O', 'M' or 'F'
     */
	function validateSex($key)
	{
		if (isset($this->data[$key]))
		{
			$this->data[$key] = strtoupper($this->data[$key]);
			if (($this->data[$key] == 'F') ||
				($this->data[$key] == 'M') ||
				($this->data[$key] == 'O'))
			{
				return $this->data[$key];
			}
		}
		return 'O';
	}
	/**
     * calculate age according birthdate and date
	 *
	 *@param string $birthdate - date of birth
	 *@param string $currentdate - current date
     *@return float
     */
	function caulculatePatientAge($birthdate,$currentdate)
	{
		$array = $this->parseDate($birthdate);

		if (count($array) != 3)
			return '';

		if (!is_numeric($array[0]) ||
			!is_numeric($array[1]) ||
			!is_numeric($array[2]))
			return '';

		$Y = (int)$array[0];
		$m = (int)$array[1];
		$d = (int)$array[2];

		$array1 = $this->parseDate($currentdate);

		if (count($array1) != 3)
			return '';

		if (!is_numeric($array1[0]) ||
			!is_numeric($array1[1]) ||
			!is_numeric($array1[2]))
			return '';

		$Y1 = (int) $array1[0];
		$m1 = (int) $array1[1];
		$d1 = (int) $array1[2];

		if (((int) $m1 . $d1 < (int) $m . $d))
			return $Y1 - $Y - 1;
		else
			return $Y1 - $Y;
	}
	/**
     * parse date
	 *
	 *@param string $date - date
     *@return array
     */
	public function parseDate($date)
	{
		if (trim($date) == '')
			return array();

		if (strpos($date,'-') === false)
		{
			$array = array(
				substr($date, 0, 4),
				substr($date, 4, 2),
				substr($date, 6, 2),
			);
			
			if (!is_numeric($array[0]))
				return array();
			if (!is_numeric($array[1]))
				return array();
			if (!is_numeric($array[2]))
				return array();
		}
		else
			$array = explode('-', $date);

		return $array;
	}
	/**
     * test date
	 *
	 *@param string $date - date
     *@return string
     */
	public function validateDate($date)
	{
		$array = $this->parseDate($date);

		if (count($array) == 3)
		{
			if (!is_numeric($array[0]))
				return '';

			$Y = $this->fixNumber($array[0], 4, '0', false);
			if ((int)$Y != $Y)
				return '';

			if (!is_numeric($array[1]))
				return '';
			$m = $this->fixNumber($array[1]);
			if (((int) $m < 1) || ((int) $m > 12))
				return '';

			if (!is_numeric($array[2]))
				return '';
			$d = $this->fixNumber($array[2]);
			if (((int) $d < 1) || ((int) $d > 31))
				return '';
			$date = $Y . $m . $d;
		}

		if (!is_numeric($date))
			return '';

		return $date;
	}
	/**
     * test time
	 *
	 *@param string $time - time
     *@return string
     */
	public function validateTime($time)
	{
		$array = $this->parseTime($time);

		if (count($array) == 3)
		{
			if (!is_numeric($array[0]))
				return '';
			$h = $this->fixNumber($array[0]);
			if ((int) $h != $h)
				return '';
			if (!is_numeric($array[1]))
				return '';

			$m = $this->fixNumber($array[1]);
			if (((int) $m < 0) || ((int) $m > 60))
				return '';
			if (!is_numeric($array[2]))
				return '';

			$sms = explode('.', $array[2]);
			$s = $this->fixNumber($sms[0]);
			if (((int) $s < 0) || ((int) $s > 60))
				return '';

			$time = $h . $m . $s;
			$ms = '';
			if (count($sms) == 2)
			{
				$ms = $this->fixNumber($sms[1], 6, '0', false);
				if (((int) $ms < 0) || ((int) $ms > 999999))
					return '';
				$time .= '.' . $ms;
			}
		}

		if (!is_numeric($time))
			return '';

		return $time;
	}
	/**
     * parse date
	 *
	 *@param string $date - date
     *@return array
     */
	public function parseTime($time)
	{
		if (trim($time) == '')
			return array();
		if (strpos($time,':') === false)
		{
			$array = array(
				substr($time, 0, 2),
				substr($time, 2, 2),
				substr($time, 4, 2),
			);
			
			if (!is_numeric($array[0]))
				return array();
			if (!is_numeric($array[1]))
				return array();
			if (!is_numeric($array[2]))
				return array();
			
			if (strpos($time,'.') !== false)
			{
				$parts = explode('.', $time);
				$ms = end($parts);
				if (is_numeric($ms))
					$array[2] .= '.'.$ms;
			}
		}
		else
			$array = explode(':', $time);
		return $array;
	}
	/**
     * extend number
	 *
	 *@param string $number - number
	 *@param int $len - string length
	 *@param string $pad - string to add
	 *@param boolean $beginning - true - add $pad to the beginning or to the end
     *@return string
     */
	function fixNumber($number, $len = 2, $pad = '0', $beginning = true)
	{
		if ($beginning)
			$number = str_pad($number, $len, $pad, STR_PAD_LEFT);
		else
			$number = str_pad($number, $len, $pad, STR_PAD_RIGHT);
		return $number;
	}
	/**
     * validate patient, referring and other names
	 *
	 *@param string $key - key name
     */
	function validateName($key)
	{
		if (isset($this->data[$key]))
			if (trim($this->data[$key]) != '')
			{
				$this->setSeparators($key, 2);
				$this->data[$key] = $this->fixNames($this->data[$key]);
			}
			else
				$this->data[$key] = '';
	}
	/**
     * add separators to name string
	 *
	 *@param string $key - key name
	 *@param int $requiredsep - how many separators needs
     */
	function setSeparators($key, $requiredsep)
	{
		if (!isset($this->data[$key]))
			return false;

		//$this->data[$key] = str_replace(' ', '^', $this->data[$key]);
		if ($requiredsep >0)
		{
			$charcount = substr_count($this->data[$key], '^');
			while(substr_count($this->data[$key], '^') < $requiredsep)
				$this->data[$key] .= '^';
		}
	}
	/**
     * validate size of string if exist
	 *
	 *@param string $key - key name
	 *@param int $size - max length
     */
	function validateStringSize($key,$size = 64)
	{
		if (isset($this->data[$key]))
		if ($this->data[$key] != '')
			$this->data[$key] = $this->fixString($this->data[$key],$size);
	}
	/**
     * validate size of string
	 *
	 *@param string $key - key name
	 *@param int $size - max length
     */
	function fixString($data, $seize=64)
	{
		if ($seize == 16)
			$data = strtoupper($data);
		while (strlen($data) > $seize)
		{
			$data = iconv('UTF-8', 'UCS-4', $data);
			$data = substr($data,0,-4);
			$data = iconv('UCS-4', 'UTF-8', $data);
		}
		return $data;
	}
	/**
     * shorten name to fit to dicom (group len = 64 symbols)
	 *
	 *@param string $data - patient or referenting physican name
     *@return string
     */
	function fixNames($data)
	{
		$components = explode('=', $data);
		$compCount = count($components);
		for ($i = 0; $i < $compCount; $i++)
		{

			$parts = explode('^', $components[$i]);
			$partsCount = count($parts);

			//delimitors len
			$dellen = $partsCount;
			if (($i+1) == $compCount)
				$dellen = $dellen -1;

			$data = implode('', $parts);
			while ((strlen($data)+$dellen) > 64)
			{
				for ($j = ($partsCount -1); $j >= 0; $j--)
				{
					if ($parts[$j] == '')
						continue;

					$parts[$j] = iconv('UTF-8', 'UCS-4', $parts[$j]);
					$parts[$j] = substr($parts[$j],0,-4);
					$parts[$j] = iconv('UCS-4', 'UTF-8', $parts[$j]);
					break;
				}
				$data = implode('', $parts);
			}
			 $components[$i] = implode('^', $parts);
		}
		
		return implode('=',$components);
	}
	/**
	* execute exec action in safe way
	*
	*@param string $comand - command line
	*@param array $out - output data
	*@return string error
	*/
	public static function tryExec($comand, &$out = array())
	{
		if (trim($comand) == '')
			return 'Empty command';

		$stopSession = (strlen(session_id()) >0);
		if ($stopSession)
		{
			$sessionId = session_id();
			session_write_close();
		}

		set_time_limit(0);

		$error = '';
		try
		{
			exec($comand, $out);
		}
		catch (Exception $e)
		{
			$error = $e->getMessage();
		}

		if ($stopSession)
		{
			session_id($sessionId);
			if (!strlen(session_id()))
				session_start();
		}

		return $error;
	}
	/**
     * set error string
	 *
	 *@param string $str - error message
     *@return string
     */
	function setError($str)
	{
		if (trim($str) != '')
			$this->error .= $str . "\n";
	}
	/**
     * generate UID
	 *
	 *@param string $root_uid - root uid
	 *@param string $product_id - product numebr
	 *@param string $product_version - product version x.xx.xx.xx
	 *@param int $type - 1-study, 2- series, 3- image
	 *@param string $datetime - today date time
	 *@param int $$number - image or series number or militime
     *@return string
     */
	public static function generateUid($root_uid, $product_id, $product_version, $type, $datetime, $number=1)
	{
		if ($type == 1)
			$fpart = self::millitime();
		else
			$fpart = $number;

		$uid = $root_uid . '.' . $product_id . '.' . str_replace('.', '', $product_version) . '.' .
				$type . '.' . $datetime . '.' . (int) $fpart . '.' .
				rand(100000, 999999);
		$len = 64 - strlen($uid) - 1;
		for ($i = 0; $i < $len; $i++)
			$uid .= rand(1, 9);
		
		return $uid;
	}
	/**
     * get milisecons
	 *
     *@return string
     */
	public static function millitime()
	{
		$microtime = microtime();
		$comps = explode(' ', $microtime);
		return  str_pad((int) ($comps[0] * 1000), 3, '0', STR_PAD_LEFT);
	}
	/**
     * conver all html characters
	 *
	 *@param string $str - value
     *@return string
     */
	function escapeTagalue($value)
	{
		return htmlspecialchars($value);
	}
}
?>
