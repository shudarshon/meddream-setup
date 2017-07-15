<?php
/*
	Original name: Translation.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Translations support for Flash GUI and backend
 */

namespace Softneta\MedDream\Core;

use Softneta\MedDream\Core\Configurable;

/** @brief Translations support for Flash GUI and backend

	At the moment it is based on files @c languages.xml in Softneta's own XML
	format. They are expected at <tt>locales/&lt;language code>/languages.xml</tt>.

	The code for the current language is specified by a cookie named @c 'userLanguage'.

	@todo The name of the translation file is immutable at the moment (can be
	      specified for the constructor only, and not changed afterwards).
	      Transition to JSON format for backend, while keeping XML for Flash,
	      will be complicated. load() could have an override parameter, but
	      read() already has one and this makes things less intuitive.
 */
class Translation implements Configurable
{
	/** @brief Default languages for configure().

		Will be used if the common settings file doesn't contain the <tt>$languages</tt>
		parameter, or it is empty. A non-empty value ensures that the user can't
		accidentally enable all possible translations, of which some might be too
		crude for uninformed customers.

		@note Until configure() is not called, @link $configuredLanguages @endlink
		      remains empty by default and therefore all possible translations are
		      enabled.
	 */
	const defaultConfiguredLanguages = 'en,lt,ru';

	/** @brief A fallback language for cases when the file for the current language
		       is missing
	 */
	const fallbackLanguage = 'en';

	/** @brief Last error message from methods that do not have a dedicated channel
		       for error reporting
	 */
	private $lastInternalError;

	/** @brief Full path to the file used by load() or read() last time */
	private $lastPath;

	/** @brief An override for <tt>\_\_DIR\_\_ . '/locales'</tt>. */
	protected $localesDir;

	/** @brief Name (without path) of the current translation file.

		The name is the same for all languages. The current language determines
		in which subdirectory this file is expected.
	 */
	protected $fileName;

	/** @brief List of configured (enabled) languages.

		Allows to use a smaller set of translations than available under <tt>locales/</tt>.
		This might be important if some translation is too incomplete and not suitable
		for majority of customers.

		Format: <tt>array('lang1', 'lang2', ...)</tt>.

		<b>If empty (as by default), then all found languages will be enabled.</b>
	 */
	protected $configuredLanguages;

	/** @brief Array with translations loaded from the current translation file */
	protected $translation;


	/** @brief A simple initializing constructor.

		@param $localesDir If @c null or empty string, is changed to <tt>"/locales"</tt>
		                   in the directory of Translation.php. Cached as @link $localesDir @endlink.
		@param $fileName   A translation file name. Cached as @link $fileName @endlink.
	 */
	function __construct($localesDir = null, $fileName = 'languages.xml')
	{
		if (is_null($localesDir) || !strlen($localesDir))
			$this->localesDir = __DIR__ . '/locales';
		else
			$this->localesDir = $localesDir;

		$this->fileName = $fileName;
		$this->translation = null;
		$this->lastInternalError = '';
		$this->lastPath = '';
		$this->configuredLanguages = array();

		/** @todo Will need a parameter <tt>"$useXmlFormat = true"</tt> when the backend begins to use i18next. */
	}


	/** @brief Wrapper for error_get_last() that returns a string. */
	private function formatError()
	{
		$msg = 'N/A';

		$e = error_get_last();
		if (isset($e['message']))
			$msg = $e['message'];

		return $msg;
	}


	/** @brief Convert a comma-separated list to an array, while stripping all spaces. */
	protected function listToArray($str)
	{
		$lang1 = explode(',', $str);
		$lang2 = array();

		foreach ($lang1 as $l)
		{
			$s = trim($l);
			if (strlen($s))
				$lang2[] = $s;
		}

		return $lang2;
	}


	/** @brief Detect available translations.

		@param $error Updated with an error message if some problem is encountered

		@retval array Numerically indexed array with lowercase language codes sorted
		              in ascending order; for example, <tt>array('en', 'lt')</tt>

		Collects subdirectories under the "locales" subdirectory, then removes those
		where the file @link $fileName @endlink does not exist.
	 */
	protected function detect(&$error)
	{
		$error = '';
		$found = array();

		$names = @glob($this->localesDir . '/*', GLOB_ONLYDIR | GLOB_ERR);
		if ($names === false)
		{
			// @codeCoverageIgnoreStart
			$error = 'glob() failed in ' . __METHOD__ . ', reason: ' . $this->formatError();
			return $found;
			// @codeCoverageIgnoreEnd
		}
		foreach ($names as $subdir)
		{
			$fileToTest = $subdir . '/' . $this->fileName;
			clearstatcache(false, $fileToTest);
			if (@file_exists($fileToTest))
				$found[] = strtolower(basename($subdir));
		}

		return $found;
	}


	/** @brief Return the language code for the current language (from a cookie).

		Reverts to @link fallbackLanguage @endlink if the current language is
		not listed among results of supported().
	 */
	public function getLanguage()
	{
		$lang = '';

		if (array_key_exists('userLanguage', $_COOKIE))
			$lang = strtolower($_COOKIE['userLanguage']);

		$ignored = '';
		$supp = $this->supported($ignored);
		if (!in_array($lang, $supp))
			$lang = self::fallbackLanguage;

		return $lang;
	}


	/** @brief Return full path to the current translation file (depends on language).

		@retval string Path to an existing file
		@retval false  No file has been found, even for the fallback language. See
		               @link $lastInternalError @endlink for a more specific message.

		The file <tt>$localesDir/&lt;language code>/$fileName</tt> must exist. If it
		doesn't, a second attempt is made for the language @link fallbackLanguage
		@endlink.

		<b>Triggers a warning</b> if @link $fileName @endlink is empty, which means the
		constructor has been called with an empty string.
	 */
	public function getPathForLanguage()
	{
		$this->lastInternalError = '';

		if (!strlen($this->fileName))
			return !trigger_error(__METHOD__ . ': file name not specified', E_USER_WARNING);
			/* trigger_error() returns `true` due to a valid third parameter. All hail 100%
			   coverage!

			   We still need to return `false`, as in production environments a PHP warning
			   won't interrupt the execution.
			 */

		$localesPath = $this->localesDir;

		$lang = $this->getLanguage();

		$path =  "$localesPath/$lang/" . $this->fileName;
		clearstatcache(false, $path);
		if (!@file_exists($path))
		{
			$this->lastInternalError =  "Translation file missing for language '$lang'";
			$path = false;
		}

		return $path;
	}


	/** @brief Dumb setter for the list of enabled languages (@link $configuredLanguages
		       @endlink).
	 */
	public function enableLanguages($languages)
	{
		$this->configuredLanguages = $languages;
	}


	/** @brief Getter for last error (@link $lastInternalError @endlink). */
	public function getError()
	{
		return $this->lastInternalError;
	}


	/** @brief Return a set of translations that are both available and enabled.

		@param $error Updated with an error message if some problem is encountered.

		@retval array Either a copy of @link $configuredLanguages @endlink or a smaller
		              subset of it, in the original order

		All languages specified in @link $configuredLanguages @endlink must be available,
		otherwise an error will be reported via @p $error. (This parameter might
		contain other messages as well, for example, related to detection of available
		languages.)

		The order of language codes in the return value is unchanged with respect
		to @link $configuredLanguages @endlink. This is important as the latter
		usually contains languages configured by the user, in the order of
		preference. (For example, the first language will be the default one when
		a particular browser opens MedDream for the first time.)
	 */
	public function supported(&$error)
	{
		$supp = array();
		$unavail = array();
		$error = '';

		/* verify whether all entries in $configuredLanguages have been detected */
		$detected = $this->detect($error);
		if (strlen($error))
			return $supp;
		if (!count($detected))		/* no point in trying further */
		{
			$error = 'No languages are available';
			return $supp;
		}
		if (!count($this->configuredLanguages))
			$supp = $detected;
		else
		{
			foreach ($this->configuredLanguages as $c)
				if (in_array($c, $detected))
					$supp[] = $c;
				else
					$unavail[] = $c;
			if (count($unavail))
				$error = 'The following languages have been configured but are unavailable: ' .
					join(' ', $unavail) . '.';
		}

		return $supp;
	}


	/** @brief High-level autoconfiguration according to the common settings file.

		@p $config can be of different types:
		@li @c array: a ready to use Configuration::$data or its equivalent.
		@li Configuration: it is assumed that Configuration::load() has been called
		    already. $data is used accordingly.
		@li @c null: replaced with a local instance of Configuration that is created
		    with default parameters, then used accordingly.

		@retval array  Languages that are both available and enabled
		@retval string An error message (the operation failed)
	 */
	public function configure($config = null)
	{
		/* some type-juggling -- the eventual $config is an array */
		if (is_null($config))
		{
			$config = new Configuration();
			$error = $config->load();
			if (strlen($error))
				return $error;
		}
		if (is_a($config, __NAMESPACE__ . '\Configuration'))
		{
			$configName = $config->getFileName();
			$config = $config->data;
		}
		else
			/* assume an array; in this case we don't know what name of the file was,
			   or was there a file at all
			 */
			$configName = 'the configuration file';

		/* if $config was initially a ready instance of Configuration, then the caller
		   is obliged to call ::load() on it. Luckily that's easy to detect. This will
		   also react to a situation when a non-initialized Configuration::$data is
		   passed.
		 */
		if (!is_array($config))
			return 'internal: configuration has not been loaded';

		/* if the settings file contains a not empty comma-separated list '$languages',
		   then use it
		 */
		$this->configuredLanguages = $this->listToArray(self::defaultConfiguredLanguages);
		if (array_key_exists('languages', $config))
		{
			$lang = $this->listToArray($config['languages']);
			if (count($lang))
				$this->configuredLanguages = $lang;
		}

		/* call supported() for self-check */
		$supp = $this->supported($error);
		if (strlen($error))
			return 'Problem with translations, check installation and $languages' .
				" in $configName.<br>\n$error";

		return $supp;
	}


	/** @brief Return raw contents of the current translation file.

		@param $path On success, will receive a full path to the translation file.

		@retval string File contents
		@retval false  Failed to read the file, or a file neither for the current language
		               nor for the fall-back language does exist. Call getError()
		               for more details.

		The current language is determined, then a corresponding file is simply
		read. If the file for the current language (specified by the cookie)
		does not exist, then a second attempt is made by using a fallback
		language (currently a class constant).

		Note that this call does not change @link $translation @endlink nor @link $lastPath
		@endlink and is meant for situations where the caller itself will parse the file.
	 */
	public function read(&$path)
	{
		$p = $this->getPathForLanguage();
		if ($p === false)
			return false;

		$f = @file_get_contents($p);
		if ($f === false)
			$this->lastInternalError = "Failed to read translation file '$p', reason: " .
				$this->formatError();
		else
			$path = $p;
		return $f;
	}


	/** @brief Read the current translation file and parse it into an internal
		       variable (@link $translation @endlink).

		@retval string An error message, empty in case of success
	 */
	public function load()
	{
		require_once(__DIR__ . '/xml2array.php');

		$f = $this->read($this->lastPath);
		if ($f === false)
			return $this->lastInternalError;

		$xml = xml2array($f, array());
		if ($xml === false)
			return "Failed to load '" . $this->lastPath . "'";
		if (!array_key_exists('languages', $xml))
			return "Invalid contents in '" . $this->lastPath . "'";
		$this->translation = $xml['languages'];

		return '';
	}


	/** @brief Return the translation for a particular selector.

		@param $selector String in form <tt>"group_name\control_name"</tt>
		@param $default Default string in case @p $selector isn't found in the
		                currently loaded translation file
		@param $inserts Array for inserts (key names are used for insert names)

		@retval string The translated text

		The function attempts to fetch the string in the current or the fallback
		language. If no such language is available, the first one in the file
		is used.

		<b>Triggers a warning</b> if no file has been successfully loaded yet.
		Do not forget to call load() after constructing the object, then check
		whether it succeeded.

		<b>Triggers a warning</b> if the current file does not contain the
		"languages>language" element that indicates contained language(s).

		<b>Triggers a warning</b> if the current language is missing in the
		current file. A typical cause is a wrong file copied to <tt>locales/&lt;language
		code>/</tt>.

		<b>Triggers a warning</b> if @p $selector does not contain exactly two
		parts delimited by @c "\". In this case the translation file is searched
		no further and @p $default is used instead.

		Before returning the string, it is updated as per @p $inserts. Every key
		is a "replace from" substring, and a corresponding value -- a "replace to"
		substring. Example:
@code
	$default = 'Wrong PHP version, 5.3.0+ required (found {v})';
	$inserts = array('{v}' => PHP_VERSION);
@endcode
	 */
	public function translate($selector, $default, $inserts = array())
	{
		$value = $default;
		if (is_null($this->translation))
			/* only $default will be used */
			trigger_error('No translation has been loaded', E_USER_WARNING);
		else
		{
			/* determine the index at which the current language is available. This is
			   important for the old format where a translation file contained more
			   than one language. For the new format, it will still be useful due to
			   detection of a file with a wrong language.

			   There is a big chance that customers who translated MedDream themselves
			   and didn't share the result, will simply use their existing file. This
			   should still work more or less, and save them from bothering us with
			   conversion to the new format.
			 */
			$lang = $this->getLanguage();
			if (!array_key_exists('language', $this->translation))
			{
				trigger_error("Language identifier missing in '" . $this->lastPath . "'",
					E_USER_WARNING);
				$idx = 0;			/* will blindly use the first entry */
			}
			else
				/* in case of a single language we have a string, not array */
				if (is_string($this->translation['language']))
				{
					if (strtolower($this->translation['language']) != $lang)
						trigger_error("Language '$lang' not found in '" . $this->lastPath .
							"', available: " . $this->translation['language'] . '.',
							E_USER_WARNING);
					$idx = 0;		/* will blindly use the first entry */
				}
				else
				{
					/* let's do a "dumb" search as language codes are case-insensitive but
					   array_search() is not. The number of entries is usually 2 so efficiency
					   is also irrelevant.
					 */
					$idx = null;
					$i = 0;
					foreach ($this->translation['language'] as $t)
					{
						if (strtolower($t) == $lang)
						{
							$idx = $i;
							break;
						}
						$i++;
					}
					if (is_null($idx))
					{
						trigger_error("Language '$lang' not found in '" . $this->lastPath . "', available: " .
							join(' ', $this->translation['language']) . '.', E_USER_WARNING);
						$idx = 0;	/* will blindly use the first entry */
					}
				}

			/* fetch the translation

				Success is optional this time. We'll simply avoid missing array keys etc.
			 */
			$s = explode('\\', $selector);
			if (count($s) != 2)
				trigger_error("Incorrect selector '$selector'", E_USER_WARNING);
			else
			{
				$sel0 = $s[0];
				$sel1 = $s[1];
				if (isset($this->translation[$sel0][$sel1]))
					if (is_array($this->translation[$sel0][$sel1]))
					{
						/* old-style file, multiple languages */
						if (isset($this->translation[$sel0][$sel1][$idx]))
							if (strlen($this->translation[$sel0][$sel1][$idx]))
								$value = $this->translation[$sel0][$sel1][$idx];
					}
					else
					{
						/* new-style file, a single language. xml2array() didn't create
						   a subarray this time.
						 */
						if (strlen($this->translation[$sel0][$sel1]))
							$value = $this->translation[$sel0][$sel1];
					}
			}
		}

		$value = str_replace("\r", '<br>', $value);
		if (count($inserts) > 0)
			foreach ($inserts as $key => $val)
				$value = str_replace($key, $val, $value);
		return $value;
	}
}
?>
