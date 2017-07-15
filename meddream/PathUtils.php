<?php

namespace Softneta\MedDream\Core;


/** @brief Various adjustments for a path to a file */
final class PathUtils
{
	private static function parseName($name)
	{
		return array_reverse(explode(' ', ucwords(strtolower(preg_replace('![\s\^]+!', ' ', $name)))));
	}


	public static function getName($row)
	{
		$name = array();
		if (isset($row['firstname']) && ($firstname = trim($row['firstname'])))
			$name = self::parseName($firstname);
		if (isset($row['lastname']) && ($lastname = trim($row['lastname'])))
			$name = array_merge($name, self::parseName($lastname));
		if (empty($name) && isset($row['fullname']) && ($fullname = trim($row['fullname'])))
			$name = self::parseName($fullname);
		return join('', $name);
	}


	public static function escapeFileName($name)
	{
		$badChars = array('<', '>', ':', '"', '/', "\\", '|', '?', '*' , '.');
		return str_replace($badChars, ' ', $name);
	}


	/** @brief Remove <tt>"file:"</tt> etc, if present. Forward slashes assumed. */
	public static function stripUriPrefix($path)
	{
		$result = $path;

		if ((substr($result, 0, 7) == 'file://') && ($result[7] != '/'))
			$result = substr($result, 5);			/* UNC; we'll support only 2 slashes, though 4+ also work */
		else
			if (substr($result, 0, 7) == 'file://')
				$result = substr($result, 7);		/* typical prefix */
			else
				if (substr($path, 0, 5) == 'file:')
					$result = substr($result, 5);	/* short prefix */

		/* under Windows, an additional slash remains */
		if (($result[0] == '/') && ($result[2] == ':') && ($result[3] == '/'))
			$result = substr($result, 1);

		return $result;
	}
}
