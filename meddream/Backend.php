<?php

/** @brief Modules designed to use from other PHP code.

	Everything at this level and below shall have a "pure PHP" interface:
	data is input via function/method parameters, and returned via return
	value (or stdout with rare exceptions).

	Scripts, especially the ones with input from webserver (@c $_REQUEST
	etc), do not belong here. @htmlonly <tt>Softneta\MedDream</tt> @endhtmlonly
	is a more suitable place for these.
 */
namespace Softneta\MedDream\Core;

use Softneta\MedDream\Core\PacsGateway\PacsGw;
use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief Common things not directly related to database, authentication and PACSes

	This class replaces AuthDB in its traditional use as universal storage for settings
	etc.

	@bug (documentation itself) The current Doxyfile uses a third-party @c INPUT_FILTER
	     (https://github.com/alinex/php-server/wiki/Doxygen-for-PHP) to convert PHP-style
	     namespace delimiters that confuse Doxygen, @c \\ , to @c :: . This results in
	     wrong reference syntax, like "Pacs::StructureIface::studyGetMetadata". Known
	     candidates of regular expressions for sed, for example,
	     <tt>s/::\([_0-9A-Za-z]*\)\([^(]*\)/\\\1\2/g</tt> or <tt>s/::\([^(]*\)/\\\1/g</tt>,
	     are still unsuitable: they convert all @c :: including the class delimiter.
 */
class Backend extends Pacs\PACS
{
	/** @brief Product version (determined automatically).

		Currently it's MINOR.MAJOR from name of meddream.swf, which formally means
		component version (not product version).

		The true product version is @c $VERSION in sharedData.php, however it is often
		set to @c "DEV" and is therefore unsuitable as part of a UID.

		Not a problem as MINOR.MAJOR are the same in all component versions anyway.
	 */
	public $productVersion = '';

	/** @name Validated configuration parameters from config.php */
	/**@{*/
	public $demoLoginUser = '';
	public $demoLoginPassword = '';
	public $hisReportLink = "";
	public $reportTextRightAlign = false;
	public $mdpacsDir = "";
	public $enableSmoothing = 0;                    /**< @brief 3x3 median filter for CR/DX (0 or 1) */
	public $preparedFilesDir = array();
	public $preparedFilesSuf = ".prep";             /* for $enable_smoothing==0 */
	public $preparedFilesSufSmooth = ".smooth";     /* for $enable_smoothing==1 */
	public $preparedFilesSubstitute = 0;
		/* 1: if a file generated from $prepared_files_suf is absent, then another
			  attempt is made with $prepared_files_suf_smooth, and vice versa.
			  Therefore if only one of prepared files exists, then it will be
			  impossible to switch the filter on/off but images will always load
			  quickly.
		   0: if a corresponding prepared file is absent, then meddream_convert*
			  is called instead. That's slower, of course.
		 */
	public $preparedFilesNestingDepth = 0;
		/* what subdirectories are expected under $prepared_files_dir
			   <1: PacsOne-like path structure, ...\YYYY-MM-DD-WWW\UID
			   >0: this number of original path components from the bottom
		 */
	public $preparedFilesTotalDepth = array();
		/* TODO: validation of original paths for nonzero $prepared_files_nesting_depth
			Will contain numbers of path components for valid paths to which
			$prepared_files_nesting_depth applies.

			A heterogeneous environment might sport various path structures at the
			same time. At the moment $prepared_files_nesting_depth can have a single
			value, which simplifies things.

			If the original path has insufficient components, there surely is no
			prepared file for it. But, what if number of components is a lot bigger,
			and actually the variable portion of this path is bigger as well? This
			mechanism will at least allow to filter incompatible paths out.
		 */
	public $m3dLink = '';
		/* non-empty string enables the "3D..." context menu link in the UI;
		   its actual contents matter in system::call3d()
		 */
	public $m3dLink2 = '';
	public $m3dLink3 = '';
	public $attachmentUploadDir = '';
	/**@}*/

	public $tr;         /**< @brief Instance of Translation */
	public $log;        /**< @brief Instance of Logging */
	public $cnf;        /**< @brief Instance of Configuration */


	/** @brief Constructor.

		@param array         $loadWhichPacsParts  Passed to PACS::loadParts()
		@param bool          $performDbConnect    Passed to AuthDB::__construct(), see its parameter with the same name.
		                                          Ignored if @p $authDB is provided.
		@param Logging       $log                 An instance of Logging
		@param Configuration $cnf                 An instance of Configuration
		@param CharacterSet  $cs                  An instance of CharacterSet. If @c null, an instance will be created and
		                                          %configure()'d internally.
		@param ForeignPath   $fp                  An instance of ForeignPath. If @c null, an instance will be created and
		                                          %configure()'d internally.
		@param PacsGw        $gw                  An instance of PacsGw. If @c null, an instance will be created internally.
		@param QR            $qr                  An instance of QR. If @c null, an instance will be created internally.
		@param Translation   $tr                  An instance of Translation. If @c null, an instance will be created and
		                                          %configure()'d internally.
		@param AuthDB        $authDb              An instance of AuthDB. If @c null, an instance will be created internally
		                                          (not here but in PACS::initialize()).
	 */
	public function __construct($loadWhichPacsParts = array(), $performDbConnect = true, Logging $log = null,
		Configuration $cnf = null, CharacterSet $cs = null, ForeignPath $fp = null, PacsGw $gw = null,
		QR $qr = null, Translation $tr = null, AuthDB $authDb = null)
	{
		/* make sure we have instances of Logging, Configuration and CharacterSet */
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
				if (Constants::EXCEPTIONS_NO_EXIT)
					throw new \Exception($err);
				else
					exit($err);
			}
		}
		$this->cnf = $cnf;

		if (is_null($cs))
		{
			$cs = new CharacterSet($log, $cnf);
			$err = $cs->configure();
			if (strlen($err))
			{
				$log->asErr('fatal: ' . $err);
				if (Constants::EXCEPTIONS_NO_EXIT)
					throw new \Exception($err);
				else
					exit($err);
			}
		}
		$this->cs = $cs;

		if (is_null($fp))
		{
			$fp = new ForeignPath($log, $cnf);
			$err = $fp->configure();
			if (strlen($err))
			{
				$log->asErr('fatal: ' . $err);
				if (Constants::EXCEPTIONS_NO_EXIT)
					throw new \Exception($err);
				else
					exit($err);
			}
		}
		$this->fp = $fp;

		parent::__construct($log, $cnf, $cs, $fp, $gw, $qr, $loadWhichPacsParts, $performDbConnect, null, false, $authDb);

		/* import common parameters */
		$err = $this->configure($cnf);
		if ($err)
		{
			$log->asErr('fatal: ' . $err);
			if (Constants::EXCEPTIONS_NO_EXIT)
				throw new \Exception($err);
			else
				exit($err);
		}

		/* if a valid $tr wasn't provided, then create one

			This also validates $languages and might die with a message from Translation.php.
		 */
		$this->tr = $tr;
		if (is_null($tr))
		{
			$this->tr = new Translation();
			$err = $this->tr->configure($cnf);
			if (is_string($err) && strlen($err))
			{
				$log->asErr('fatal: ' . $err);
				if (Constants::EXCEPTIONS_NO_EXIT)
					throw new \Exception($err);
				else
					exit($err);
			}
		}

		/* our version number, for generated UIDs */
		$this->productVersion = $this->getMeddreamVersion();
	}


	/** @brief Validate common parameters from config.php.

		"Public" just because of unit tests.
	  */
	public function configure(Configuration $cnf)
	{
		/* a sample user and password, for demo purposes */
		if (isset($cnf->data['demo_login_user']))
			$this->demoLoginUser = $cnf->data['demo_login_user'];
		if (isset($cnf->data['demo_login_password']))
			$this->demoLoginPassword = $cnf->data['demo_login_password'];

		/* support for prepared files ($prepared_files_dir etc) */
		if (isset($cnf->data['prepared_files_dir']) && !empty($cnf->data['prepared_files_dir']))
		{
			$dirs_orig = explode(';', $cnf->data['prepared_files_dir']);
			$dirs_good = array();
			for ($i = 0; $i < count($dirs_orig); $i++)
				if (strlen($dirs_orig[$i]))
					$dirs_good[] = $dirs_orig[$i];
					/* can't validate here: any output here (for example, trigger_error
					   at E_USER_WARNING) shall not interfere with non-interactive loading
					   of MedDream. exit() is even worse as missing storage for preparation
					   isn't a fatal condition. The validation is done in login.php instead.
					 */

			$this->preparedFilesDir = $dirs_good;
		}
		if (isset($cnf->data['prepared_files_suf']) && !empty($cnf->data['prepared_files_suf']))
			$this->preparedFilesSuf = $cnf->data['prepared_files_suf'];
		if (isset($cnf->data['prepared_files_suf_smooth']) && !empty($cnf->data['prepared_files_suf_smooth']))
			$this->preparedFilesSufSmooth = $cnf->data['prepared_files_suf_smooth'];
		if (isset($cnf->data['prepared_files_substitute']))
		{
			$this->preparedFilesSubstitute = $cnf->data['prepared_files_substitute'];
			if (($this->preparedFilesSubstitute !== 0) && ($this->preparedFilesSubstitute !== 1))
				return '$prepared_files_substitute (config.php) must be either 0 or 1';
		}
		if (isset($cnf->data['prepared_files_nesting_depth']))
		{
			$this->preparedFilesNestingDepth = $cnf->data['prepared_files_nesting_depth'];
			if (!is_int($this->preparedFilesNestingDepth) ||
					($this->preparedFilesNestingDepth < 0))
				return '$prepared_files_nesting_depth (config.php) must be non-negative integer';
		}

		/* $enable_smoothing */
		if (isset($cnf->data['enable_smoothing']))
		{
			$this->enableSmoothing = $cnf->data['enable_smoothing'];
			if (($this->enableSmoothing !== 0) && ($this->enableSmoothing !== 1))
				return '$enable_smoothing (config.php) must be either 0 or 1';
		}

		/* $attachment_upload_dir: directory must exist */
		if (isset($cnf->data['attachment_upload_dir']))
		{
			$this->attachmentUploadDir = trim($cnf->data['attachment_upload_dir']);
			if (strlen($this->attachmentUploadDir))
				if (!is_dir($this->attachmentUploadDir))
					return '$attachment_upload_dir (config.php) is not a directory: "' .
						$this->attachmentUploadDir . '"';
		}

		/* a few remaining settings that are accepted without validation */
		if (isset($cnf->data['his_report_link']))
			$this->hisReportLink = $cnf->data['his_report_link'];
		if (isset($cnf->data['report_text_right_align']))
			$this->reportTextRightAlign = $cnf->data['report_text_right_align'];
		if (isset($cnf->data['m3d_link']))
			$this->m3dLink = $cnf->data['m3d_link'];
		if (isset($cnf->data['m3d_link_2']))
			$this->m3dLink2 = $cnf->data['m3d_link_2'];
		if (isset($cnf->data['m3d_link_3']))
			$this->m3dLink3 = $cnf->data['m3d_link_3'];

		return '';
	}


	/** @brief Provide a value for @link $productVersion @endlink

		@todo By tradition we're using the version of the Flash frontend. Product version
		      from sharedData.php ($VERSION) would be better, however it is often set to
		      "DEV". Probably it's better to detect this string and only then attempt to
		      use the version of Flash frontend?
	 */
	public static function getMeddreamVersion()
	{
		$ver = '';

		/* extract version from the name */
		$str = self::getSwfFileByVersionAndTime(false);
		$parts = explode('-', $str);
		if (count($parts) > 1)
			$ver = $parts[1];

		/* make sure there are only two version components */
		$parts = explode('.', $ver);
		while (count($parts) > 2)
			array_pop($parts);
		$ver = implode('.', $parts);

		return $ver;
	}


	/** @brief Return version number from file name.

		@param $filename  Entire file name with extension
		@param $ext       Extension to remove (without a dot character)

		Input format:

		@verbatim
file name ::= [ prefix ], [ '-', version, ] [ '.', $ext ];
version ::= number, [ '-', hash, [ '+', date ] ];
		@endverbatim

		Output format:

		@verbatim
version number ::= prefix | ( version, [ '-', date ] );
		@endverbatim

		@return string
	 */
	public static function getVersionFromName($filename, $ext)
	{
		$tmp = explode('-', $filename);
		if (count($tmp) < 2)
			$version = $tmp[0];
		else
		{
			$version = $tmp[1];

			/* if we have a non-tagged&non-committed version like "meddream-4.04-09b14796add6+20151006.swf",
			   then the hash is irrelevant, however the date should be taken into account
			   as it's a stronger hint than the timestamp of the file.
			 */
			if (count($tmp) > 2)
			{
				$meta = explode('+', $tmp[2]);
				if (count($meta) > 1)
					$version .= '-' . $meta[1];
			}
		}

		return str_replace('.' . $ext, '', $version);
	}


	/** @brief Searches for meddream*.swf and returns the latest one without extension.

		@param $addTimestamp  If true (default), then timestamp is appended in the format
		                      <tt>?UNIX_TIME</tt>.

		See @link getVersionFromName() @endlink about version extraction. If extracted
		versions are identical, then file timestamps are compared.

		@return string
	 */
	public static function getSwfFileByVersionAndTime($addTimestamp = true)
	{
		$file = 'meddream';
		$swfFiles = array_filter(glob(__DIR__ . '/swf/meddream*.swf'), 'is_file');

		$count = count($swfFiles);
		if ($count)
		{
			if ($count > 1)
				usort($swfFiles, function($f1, $f2)
					{
						$v1 = Backend::getVersionFromName($f1, 'swf');
						$v2 = Backend::getVersionFromName($f2, 'swf');
						$compare = version_compare($v2, $v1);
						if ($compare == 0)
							return filemtime($f2) - filemtime($f1);
						else
							return $compare;
					}
				);
			$file = basename($swfFiles[0], '.swf');
			if ($addTimestamp)
				$file .= '?' . filemtime($swfFiles[0]);
		}

		return $file;
	}
}
