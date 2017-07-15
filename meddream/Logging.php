<?php
/*
	Original name: Logging.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		A logging mechanism, OOP-style
 */

namespace Softneta\MedDream\Core;

/** @brief A file-based logger designed for a very compact code.

Replaces the legacy code that looks like this:

@code
	include_once('logging.php');

	if (log_enabled(LL_FCNS))
		log_write('begin ' . __METHOD__);

	.
	:
		if (log_enabled(LL_BASIC))
			log_write('not authenticated');
@endcode

... with this:

@code
	include_once('logging.php');
	$log = new Logging();

	$log->asDump('begin ' . __METHOD__);

	.
	:
		$log->asErr('not authenticated');
@endcode

The object name "$log" is preferred as it also reads as a verb in English.

A typical level mapping might be:

<pre>LL_BASIC  => asErr()
LL_DETAIL => asInfo()
LL_FCNS   => asDump()
LL_DUMP   => asDump()</pre>

There is no direct equivalent for asWarn(); it should be used for true warning
messages. Currently most uses of @c LL_DETAIL are in essence just another case of
information messages.
 */
class Logging
{
	/** @name Possible log levels from the settings file */
	/**@{*/
	const LEVEL_DISABLED = 0;		/**< @brief No output whatsoever */
	const LEVEL_ERRORS = 1;		/**< @brief Only error messages */
	const LEVEL_WARNINGS = 2;		/**< @brief Errors and warnings */
	const LEVEL_INFORMATION = 3;	/**< @brief Errors, warnings, information */
	const LEVEL_DUMPS = 4;		/**< @brief Errors, warnings, information, dump */
	const LEVEL_DEBUG = 5;		/**< @brief Errors, warnings, information, dump, debug */
	/**@}*/

	/** @brief The default log level.

		For cases when the settings file exists and is empty or contains a non-number.
		Probably the user attempts to turn on logging without knowing exact contents
		of the file required for that.
	 */
	const LEVEL_DEFAULT = self::LEVEL_ERRORS;

	/** @brief Current log level. Default value is @link LEVEL_DISABLED @endlink.
			Call reload() any time to update.
	 */
	protected $level;

	protected $logDir;				/**< @brief Configured directory for log files and the settings file */
	protected $filePrefix;			/**< @brief Configured prefix of a log file name */
	protected $settingsFileName;	/**< @brief Configured settings file name */


	/** @brief Constructor

		@param $prefix	Log file name prefix. The default value @c "php-" corresponds
				to the behavior of the legacy code.

		@param $dir		Directory for log files and the file "enabled".
				The value @c null (default) is replaced with "log" under <tt>dirname(\_\_FILE\_\_)</tt>.
				An empty string is equivalent to "log" under the current directory,
				obviously. A directory separator, if needed, is added to a non-empty
				string automatically.

		@param $settingsFileName	Use this name instead of "enabled"
	 */
	public function __construct($prefix = 'php-', $dir = null, $settingsFileName = 'enabled')
	{
		if (is_null($dir))
			$this->logDir = dirname(__FILE__) . '/log/';
		else
		{
			$this->logDir = $dir;
			if (strlen($dir))		/* an empty string must remain empty */
			{
				$lc = substr($dir, -1);
				if (($lc != '/') && ($lc != '\\'))
					$this->logDir .= DIRECTORY_SEPARATOR;
			}
			else
				$this->logDir .= 'log/';
		}
		if (!@is_dir($this->logDir))
			error_log("directory missing, logging will fail: '" . $this->logDir . "'");

		$this->filePrefix = $prefix;
		$this->settingsFileName = $settingsFileName;

		$this->reload();
	}


	/** @brief Indicate if subsequent asErr() etc will actually write to the log.

		A typical purpose is optimization: this way one can avoid preparation of
		large amount of data that won't actually go to the log.
	 */
	public function isLoggingLevelEnabled($level)
	{
		return $this->level >= $level;
	}


	/** @brief Reload the log level from the settings file.

		The level falls back to @link LEVEL_DEFAULT @endlink when:
			- the settings file is empty,
			- the first character is not a number.

		Basically the first character must be <tt>[0-9]</tt>. The value is clamped to the
		maximum supported one.

		If the settings file doesn't exist, however, the level is set to
		@link LEVEL_DISABLED @endlink.
	 */
	public function reload()
	{
		$ll = self::LEVEL_DISABLED;
		$fn = $this->logDir . $this->settingsFileName;
		if (@file_exists($fn))
		{
			$fh = @fopen($fn, 'r');
			$fc = @fread($fh, 1);
			fclose($fh);
			if (strlen($fc))
			{
				if (($fc < '0') || ($fc > '9'))		/* obviously some rubbish */
					$ll = self::LEVEL_DEFAULT;
				else
				{
					$ll = intval($fc, 10);

					/* clamp the value to allowed limits */
					if ($ll > 5)
						$ll = 5;
				}
			}
			else
				$ll = self::LEVEL_DEFAULT;
		}
		$this->level = $ll;
	}


	/** @brief A universal writer

		The format of the log line is: <tt>[$prefixHHIISS.UUUUUU] $text</tt>.
	 */
	protected function write($text, $msgType = '', $addTimestamp = true)
	{
		$ts = explode(' ', microtime());
		$fn = $this->logDir . $this->filePrefix . date('Ymd', $ts[1]) . '.log';
		$fh = @fopen($fn, 'at');
		if ($fh)
		{
			if ($addTimestamp)
				$header = "[$msgType" . date('H:i:s', $ts[1]) .
					sprintf('.%06u] ', $ts[0] * 1e6);
			else
				$header = '';

			@fwrite($fh, $header . $text . "\n");
			fclose($fh);
		}
	}


	/** @brief Build compact multiline information about callers (file and line).

		@param $numLevelsToSkip How many initial levels are ignored. For example, the value
		                        should be 1 when this method is used from Logging itself, as
		                        this will exclude the first record that points to Logging.php.

		@return string Records with file name/path and line number, delimited by @c "\n"

		Only file records are included. Function, object, etc records are silently
		ignored and not counted against @p $numLevelsToSkip.

		A trailing @c "\n" is deliberately not added.

		File paths are made relative to \_\_DIR\_\_ of Logging.php if this class is used
		in files from this directory and below it. If the calling code resides in some
		parent directory, or in an unrelated directory, then paths will remain absolute.
	 */
	protected function formatStackTrace($numLevelsToSkip = 0)
	{
		$text = '';
		$bs = debug_backtrace(false);
		$depth = 0;
		foreach ($bs as $caller)
		{
			if ($numLevelsToSkip > 0)
			{
				$depth++;
				$numLevelsToSkip--;
				continue;
			}
			if (!array_key_exists('file', $caller))
				continue;
			$depth++;

			$strippedPath = str_replace(__DIR__ . DIRECTORY_SEPARATOR,
				'',
				$caller['file']);

			if (strlen($text))
				$text .= "\n";
			$text .= "\t{" . $depth . "} $strippedPath," . $caller['line'];
		}
		return $text;
	}


	/** @brief Log an error message.

			The message will NOT be logged if the current log level is below @link LEVEL_ERRORS @endlink.
	 */
	public function asErr($text)
	{
		if ($this->level >= self::LEVEL_ERRORS)
		{
			$text .= "\n" . $this->formatStackTrace(1);
			$this->write($text, 'E ');
		}
	}


	/** @brief Log a warning message.

			The message will NOT be logged if the current log level is below @link LEVEL_WARNINGS @endlink.
	 */
	public function asWarn($text)
	{
		if ($this->level >= self::LEVEL_WARNINGS)
			$this->write($text, 'W ');
	}


	/** @brief Log an information message.

			The message will NOT be logged if the current log level is below @link LEVEL_INFORMATION @endlink.
	 */
	public function asInfo($text)
	{
		if ($this->level >= self::LEVEL_INFORMATION)
			$this->write($text, 'I ');
	}


	/** @brief Log a dump-level message.

The message will NOT be logged if the current log level is below @link LEVEL_DUMPS @endlink.

Number of arguments is unlimited. All found arguments will be converted
to a string and concatenated. Example:

@code
$log->asDump('testing: ', $argv, ", '$php_errormsg', ", false);
@endcode

might produce the following:

@verbatim
[D 21:29:28.951925] testing: array (
  0 => 'test.php',
), 'unlink(missing.txt): No such file or directory', false
@endverbatim

Strings <b>at even positions (0, 2, 4, ...)</b> are included as is. They will
be neither quoted automatically (use a wrapper string with quotes where needed)
nor escaped (use var_export, serialize, etc for binary strings). However a
"natural" use case reserves even positions for label/description strings that
do not need this automation. Another example:

@code
$log->asDump('testing: ', ", '$php_errormsg', ", ', another label: ', false);
@endcode

@verbatim
[D 21:29:28.965875] testing: ', \'unlink(missing.txt): No such file or directory\', ', another label: false
@endverbatim
	 */
	public function asDump()
	{
		if ($this->level >= self::LEVEL_DUMPS)
		{
			/* first join all arguments into a single string */
			$str = '';
			$num = func_num_args();
			for ($i = 0; $i < $num; $i++)
			{
				$arg = func_get_arg($i);

				if (!($i % 2) && is_string($arg))	/* strings "as is" at even positions only */
					$str .= $arg;
				else
					$str .= var_export($arg, true);
			}
			$this->write($str, 'D ');
		}
	}


	/** @brief At specified level, logs output of formatStackTrace().

		The message header with a timestamp etc is not added.

		Example:

@code
	$log->asWarn('something wrong');
	$log->addBacktrace(Logging::LEVEL_WARNINGS);
@endcode

@verbatim
[W 19:01:45.440698] something wrong
	{2} test.php,9
@endverbatim
	 */
	public function addBacktrace($level)
	{
		if ($this->level >= $level)
		{
			$this->write($this->formatStackTrace(1), '', false);
		}
	}
}
