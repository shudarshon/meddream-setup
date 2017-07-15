<?php

use Softneta\MedDream\Core\Logging;

class ZipFile
{
	public $num_entries = 0;
	public $datasec = "";
	public $ctrl_dir = ""; // central directory
	public $eof_ctrl_dir = "\x50\x4b\x05\x06\x00\x00\x00\x00"; //end of Central directory record
	public $old_offset = 0;
	public $files = array();
	public $tmp_file = "";
	public $smooth = false;
	public $slowDown = 0;

	function __construct()
	{
		require_once('autoload.php');
		$this->log = new Logging();
	}

	function getTimestamp()
	{
		list($usec, $sec) = explode(" ", microtime());
		list($int, $dec) = explode(".", $usec);
		$dec = date("YmdHis").sprintf("%06d", (int)($dec / 100));
		return $dec;
	}

	function setImage($img, $type)
	{
		if (connection_aborted())
			return;

		if (isset($_SESSION[$img['client']]))
			$_SESSION[$img['client']]['action'] = 1;

		$path = $img['path'];
		$tmp = $img['tmp'];
		$uid = $img['uid'];
		$xfersyntax = $img["xfersyntax"];
		$bitsstored = $img["bitsstored"];
		if ($bitsstored == '') $bitsstored = 8;
		$thumbnail = "$tmp$uid-" . $this->getTimestamp() . '.tmp';

		$this->log->asDump('$img: ', $img);

		$quality = 100;			/* CONFIG: decrease this for smaller JPEGs */
		$useJPG = true;			/* CONFIG: change this to `false` for legacy behavior */
		if ($type == '.tiff')
		{
			if (!function_exists('imagecreatefromstring'))
			{
				$type = '.jpg';
				$this->log->asErr('GD2 extension is missing, TIFF format unsupported, using JPEG');
			}
			else
				$useJPG = false;
		}
		if (!defined('MEDDREAM_THUMBNAIL_JPG'))		/* older extension doesn't parse excess arguments */
		{
			$this->log->asErr('php_meddream does not support MEDDREAM_THUMBNAIL_JPG');
			exit('processing failed, see logs');
		}
		if ($useJPG)
			$flags = $quality | MEDDREAM_THUMBNAIL_JPG | MEDDREAM_THUMBNAIL_JPG444;
		else
			$flags = 0;

		$basedir = __DIR__;
		$thumbnailSize = 0;
		$this->log->asDump('meddream_thumbnail(', $path, ', ', $thumbnail, ', ', $basedir, ', ',
			$thumbnailSize, ', ', $xfersyntax, ', ', $bitsstored, ', 0, 0, ', $this->smooth, ', ',
			$flags, ')');
		$r = meddream_thumbnail($path, $thumbnail, $basedir, $thumbnailSize, $xfersyntax,
			$bitsstored, 0, 0, $this->smooth, $flags);
		$this->log->asDump('meddream_thumbnail: ', substr($r, 0, 6));

		if (strlen($r) < 1)
			exit('processing failed, see logs');
		if ($r[0] == 'E')
		{
			$r = substr($r, 5);
			if (file_exists($r))
				copy($r, $thumbnail);
		}
		elseif ($r[0] == '2')
		{
			/* verify once more as the GD2 format is still possible

			   Possible reasons are bugs in code before meddream_thumbnail (especially
			   if it was manipulated to force the legacy format), and even wrong version
			   of mdc.exe (for images that need it)
			 */
			if (!function_exists('imagecreatefromstring'))
			{
				$this->log->asErr('internal: GD2 extension is missing');
				exit('processing failed, see logs');
			}

			$r = substr($r, 5);
			$r = imagecreatefromstring($r);

			/* convert to TIFF if requested */
			if ($type == '.tiff')
			{
				include_once('convertImage.php');

				imagepng($r, $thumbnail);

				$newfile = convertImage::convertToType($thumbnail, $type);
				if ($newfile == '')
				{
					if (convertImage::$error != '')
						$this->log->asErr("conversion error: '" . convertImage::$error . "'");
					$this->log->asDump('converter output: ', convertImage::$out);

					$this->log->asWarn('falling back to JPEG format');
					$type = '.jpg';
					imagejpeg($r, $thumbnail, $quality);
				}
				else
				{
					@unlink($thumbnail);
					$thumbnail = $newfile;
				}
			}
			else
				imagejpeg($r, $thumbnail, $quality);

			imagedestroy($r);
		}
		elseif ($r[0] == 'J')
		{
			/* the decision about this format was made before the meddream_thumbnail call,
			   no further processing is needed
			 */
			$r = substr($r, 5);
			file_put_contents($thumbnail, $r);
		}
		else
			exit('processing failed, see logs');

		if (!file_exists($thumbnail))
			$img['path'] = '';
		else
			$img['path'] = $thumbnail;

		return $img;
	}

	function setVideo($img,$speed)
	{
		$path = $img['path'];
		$tmp = $img['tmp'];
		$headerFile = meddream_convert2($img['client'], dirname(__FILE__), $path, $tmp, 0);
		if (is_array($headerFile))
			$headerFile = $headerFile['result'];

		$matches = array();
		preg_match("/<pixelOffset>(.*?)<\/pixelOffset><pixelSize>(.*?)<\/pixelSize>/", file_get_contents($headerFile), $matches);
		@unlink($headerFile);

		$pixelOffset = $matches[1];
		$size = $matches[2];

		$this->log->asDump("mp4 tmp: '$path', $pixelOffset, $size");

		$handle1 = @fopen($path, 'rb');
		if (!$handle1)
			$this->log->asWarn('failed to add to archive: ' . var_export($path, true));
		$handle2 = fopen($headerFile,'w+');
		$readSize = round($speed*1024);

		fseek($handle1, $pixelOffset);

		while ($size > 0)
		{
			if ($size < $readSize)
				$readSize = $size;

			$contents = '';
			$contents = fread($handle1, $readSize);
			fwrite($handle2, $contents, $readSize);
			$size -= $readSize;
			if (connection_aborted())
				break;
			if ($this->slowDown > 0)
				usleep($this->slowDown);
		}

		fclose($handle1);
		fclose($handle2);
		if (!file_exists($headerFile))
			$img['path'] = '';
		else
			$img['path'] = $headerFile;

		return $img;
	}


	/**
	 * Converts Unix timestamp to a four byte DOS date and time format (date
	 * in high two bytes, time in low two bytes allowing magnitude comparison).
	 *
	 * @param int $unixtime current Unix timestamp
	 * @return int current date in a four byte DOS format
	 */
	private function unix2DosTime($unixtime = 0)
	{
		$timearray = ($unixtime == 0) ? getdate() : getdate($unixtime);

		if ($timearray['year'] < 1980)
		{
			$timearray['year'] = 1980;
			$timearray['mon'] = 1;
			$timearray['mday'] = 1;
			$timearray['hours'] = 0;
			$timearray['minutes'] = 0;
			$timearray['seconds'] = 0;
		} // end if

		return (($timearray['year'] - 1980) << 25) | ($timearray['mon'] << 21) | ($timearray['mday'] << 16) |
			($timearray['hours'] << 11) | ($timearray['minutes'] << 5) | ($timearray['seconds'] >> 1);
	}


	function set_file($file)
	{
		$this->files[] = $file;
	}


	function create_zip($tmp_name = "", $speed = 64)
	{
		$this->tmp_file = $tmp_name;

		$this->log->asDump('zip tmp: ', $this->tmp_file);

		for ($i = 0; $i < sizeof($this->files); $i++)
		{
			if (is_file($this->files[$i]['path']))
				$this->add_file($this->files[$i], $speed);
			else
				continue; // need to ad directory to file path

			if (isset($this->files[$i]['client']))
				if (isset($_SESSION[$this->files[$i]['client']]))
					$_SESSION[$this->files[$i]['client']]['completed'] = $i + 1;
		}

		$data = $this->ctrl_dir .
			$this->eof_ctrl_dir .
			pack("v", $this->num_entries) .
			pack("v", $this->num_entries) .
			pack("V", strlen($this->ctrl_dir)) .
			pack("V", filesize($this->tmp_file)) . "\x00\x00";
		$this->saveTemp($data, $this->tmp_file);
		return (strlen($data) + filesize($this->tmp_file));
	}


	function saveTemp($data, $path = "")
	{
		if ($path != "") {
			$f = fopen($path, 'ab+');
			fwrite($f, $data, strlen($data));
			fclose($f);
			$data = null;
		}
	}


	/* copy file contents to a common temporary file (no compression) */
	function add_file($file, $speed, $time = 0)
	{
		if (isset($file['client']))
			if (isset($_SESSION[$file['client']]))
				$_SESSION[$file['client']]['action'] = 1;

		$isVideo = false;
		if (($file["xfersyntax"] == "1.2.840.10008.1.2.4.103") ||
			($file["xfersyntax"] == "1.2.840.10008.1.2.4.100"))
			$isVideo = true;

		$ext = strtolower($file['ext']);

		$this->log->asDump('add : ', $file);

		if ($isVideo && (($ext == '.mp4') || ($ext == '.mpg')))
			$file = $this->setVideo($file, $speed);
		else
			if (!$isVideo && (($ext == '.jpg') || ($ext == '.tiff')))
				 $file = $this->setImage($file, $ext);

		if (connection_aborted())
			return;
		if (($file['path'] == '') || !file_exists($file['path']))
			return;

		if (isset($file['client']))
			if (isset($_SESSION[$file['client']]))
				$_SESSION[$file['client']]['action'] = 2;

		$dtime = dechex($this->unix2DosTime($time));
		$hexdtime = '\x' . $dtime[6] . $dtime[7]
			. '\x' . $dtime[4] . $dtime[5]
			. '\x' . $dtime[2] . $dtime[3]
			. '\x' . $dtime[0] . $dtime[1];
		eval('$hexdtime = "' . $hexdtime . '";');
		$this->datasec = '';
		$this->datasec .= "\x50\x4b\x03\x04";
		$this->datasec .= "\x14\x00";	// ver needed to extract
		$this->datasec .= "\x00\x00";	// gen purpose bit flag
		$this->datasec .= "\x00\x00";	// compression method
		$this->datasec .= $hexdtime;	// last mod time and date

		$name = $file['name'];
		$path = $file['path'];
		$size = filesize($path);

		// workaround for bug #45028 in versions prior to 5.2.7
		$crc_order = (version_compare(PHP_VERSION, '5.2.7') < 0) ? 'N' : 'V';
		// however, 5.3.0-dev still has the bug! let's hope that those old versions are rare

		$unc_len = $size;
		$crc = hexdec(hash_file('CRC32b', $path));
		$c_len = $size;

		$this->datasec .= pack($crc_order, $crc); // crc32
		$this->datasec .= pack("V", $c_len); //compressed filesize
		$this->datasec .= pack("V", $unc_len); //uncompressed filesize
		$this->datasec .= pack("v", strlen($name)); //length of filename
		$this->datasec .= pack("v", 0); //extra field length
		$this->datasec .= $name;

		$this->saveTemp($this->datasec, $this->tmp_file);

		$handle1 = @fopen($path, "rb");
		if (!$handle1)
			$this->log->asWarn('failed to add to archive: ' . var_export($path, true));
		$handle2 = fopen($this->tmp_file, 'ab+');

		$readSize = round($speed * 1024);

		while (!feof($handle1)) {
			$contents = '';
			$contents = fread($handle1, $readSize);
			fwrite($handle2, $contents, strlen($contents));
			if (connection_aborted())
				break;
			if ($this->slowDown > 0)
				usleep($this->slowDown);
		}

		fclose($handle1);
		fclose($handle2);

		if (in_array($ext, array('.jpg', '.tiff','.mpg','.mp4')))
			if ($file['realpath'] != $file['path'])
				if (file_exists($path))
					unlink($path);

		$new_offset = filesize($this->tmp_file);

		// now add to central directory record
		$cdrec = "\x50\x4b\x01\x02";
		$cdrec .= "\x00\x00";	// version made by
		$cdrec .= "\x14\x00";	// version needed to extract
		$cdrec .= "\x00\x00";	// gen purpose bit flag
		$cdrec .= "\x00\x00";	// compression method
		$cdrec .= $hexdtime;	// last mod time & date
		$cdrec .= pack($crc_order, $crc);		// crc32
		$cdrec .= pack("V", $c_len);	//compressed filesize
		$cdrec .= pack("V", $unc_len);	//uncompressed filesize
		$cdrec .= pack("v", strlen($name));	//length of filename
		$cdrec .= pack("v", 0);	//extra field length
		$cdrec .= pack("v", 0);	//file comment length
		$cdrec .= pack("v", 0);	//disk number start
		$cdrec .= pack("v", 0);	//internal file attributes
		$cdrec .= pack("V", 0);	//external file attributes - 'archive' bit set

		$cdrec .= pack("V", $this->old_offset);
		$this->old_offset = $new_offset;

		$cdrec .= $name;

		$this->ctrl_dir .= $cdrec;
		$this->num_entries++;
	}
}
