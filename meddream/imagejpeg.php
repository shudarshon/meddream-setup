<?php
/*
	Original name: imageJpeg.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		db <daina@softneta.lt>

	Description:
		Implements a server interface for handling the link that was generated
		by using the "Copy image link" context menu item.

		Sends out a single image in JPEG format. The image is always resized to
		$imagejpegsize (usually 1024). For most PACSes, a path to DICOM file is
		obtained from the database, then the file is converted to JPEG.
 */

	use Softneta\MedDream\Core\Backend;
	use Softneta\MedDream\Core\Audit;
	use Softneta\MedDream\Core\Logging;

	function imagecopyresampledSMOOTH(&$dst_img, &$src_img, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $mult=1.25)
	{
		$tgt_w = round($src_w * $mult);
		$tgt_h = round($src_h * $mult);

		// using $mult <= 1 will make the current step w/h smaller (or the same), don't allow this, always resize by at least 1 pixel larger
		if ($tgt_w <= $src_w){ $tgt_w += 1; }
		if ($tgt_h <= $src_h){ $tgt_h += 1; }

		// if the current step w/h is larger than the final height, adjust it back to the final size
		// this check also makes it so that if we are doing a resize to smaller image, it happens in one step (since that's already smooth)
		if ($tgt_w > $dst_w){ $tgt_w = $dst_w; }
		if ($tgt_h > $dst_h){ $tgt_h = $dst_h; }

		$tmpImg = imagecreatetruecolor($tgt_w, $tgt_h);

		imagecopyresampled($tmpImg, $src_img, 0, 0, $src_x, $src_y, $tgt_w, $tgt_h, $src_w, $src_h);
		imagecopy($dst_img, $tmpImg, $dst_x, $dst_y, 0, 0, $tgt_w, $tgt_h);
		imagedestroy($tmpImg);

		// as long as the final w/h has not been reached, keep on resizing
		if ($tgt_w < $dst_w OR $tgt_h < $dst_h){
			imagecopyresampledSMOOTH($dst_img, $dst_img, $dst_x, $dst_y, $dst_x, $dst_y, $dst_w, $dst_h, $tgt_w, $tgt_h, $mult);
		}
	}

	if (!strlen(session_id()))
		@session_start();

	require_once('autoload.php');
	$modulename = basename(__FILE__);
	$log = new Logging();
	$log->asDump('begin ' . $modulename);

	$audit = new Audit('IMAGEJPEG');

	/* this file is also used in MedDreamHTML5 but for a different purpose */
	if (!isset($htmlMode))
		$htmlMode = false;
	if (isset($_REQUEST['mobileMode']))		/* ...and in MedDreamMobile, too */
		$htmlMode = true;

	if (!isset($_GET["uid"]))
	{
		$audit->log(false);
		$log->asErr("missing parameter 'uid'");
		exit;
	}
	$uid = $_GET["uid"];
	$log->asDump('$uid = ', $uid);

	$backend = new Backend(array('Structure', 'Preload'));

	if ($htmlMode)
	{
		if (!isset($_GET["size"]))
		{
			$audit->log(false, $uid);
			$log->asErr("missing parameter 'size'");
			exit;
		}
		$imagejpegsize = $_GET["size"];
		$log->asDump('$size = ', $imagejpegsize);

		if (!$backend->authDB->isAuthenticated())
		{
			$audit->log(false, $uid);
			$log->asErr("not authenticated");
			return false;
		}
	}
	else
	{
		if (!file_exists(__DIR__ . '/external.php'))
		{
			$audit->log(false, $uid);
			$log->asErr('not configured');
			exit;
		}
		include_once(__DIR__ . '/external.php');

		$db = "";
		$user = "";
		$password = "";

		$imagejpeg = true;		/* request login credentials from external.php */
		$imagejpegsize = 512;	/* default; external.php may update */
		externalLoginInfo($db, $user, $password);
		if (!$backend->authDB->login($db, $user, $password))
		{
			$backend->authDB->logoff();
			$audit->log(false, $uid);
			$log->asErr('not authenticated');
			exit;
		}
	}

	/* assume that with $pacs='DICOM' and similar ones, the file is already cached

		RetrieveStudy is somewhat risky because md-html uses us for thumbnails
		and one fine day could make multiple requests in parallel, which would result
		in multiple C-FIND/C-MOVE requests to download the study. As there are no
		other legitimate uses (the original "Copy image link" functionality is hardly
		remembered by current customers), there won't be any preloading just in case.
	*/
	$st = $backend->pacsStructure->instanceGetMetadata($uid, true);
	if (strlen($st['error']))
	{
		$audit->log(false, $uid);
		exit($st['error']);
	}
	$path = $st['path'];
	$xfersyntax = $st['xfersyntax'];
	$bitsstored = $st['bitsstored'];
	$uid = $st['uid'];
	if ($bitsstored == '') $bitsstored = 8;

	$tempPath = $backend->pacsConfig->getWriteableRoot();
	if (is_null($tempPath))
	{
		$return['error'] = 'getWriteableRoot failed';
		$audit->log(false, $uid);
		exit($return['error']);
	}
	$tempPath .= 'temp' . DIRECTORY_SEPARATOR;
	if (!file_exists($tempPath) && !mkdir($tempPath))
	{
		$audit->log(false, $uid);
		$error = "No directory for temporary files";
		$log->asErr($error);
		exit($error);
	}

	if (empty($xfersyntax))
	{
		$xfersyntax = meddream_extract_transfer_syntax(__DIR__, $path);
		if (!strlen($xfersyntax) || ($xfersyntax[0] == '*'))
		{
			$log->asErr('meddream_extract_transfer_syntax: ' . var_export($xfersyntax, true));
			$xfersyntax = '';
		}
	}

	$quality = ($htmlMode && !$imagejpegsize) ? 100 : 80;
	if (!defined('MEDDREAM_THUMBNAIL_JPG'))
	{
		$msg = 'php_meddream does not support MEDDREAM_THUMBNAIL_JPG';
		$log->asErr($msg);
		return $msg;
	}
	if ($htmlMode && !$imagejpegsize)
		$flags = MEDDREAM_THUMBNAIL_JPG444 | $quality;
	else
		$flags = $quality;
	$flags |= MEDDREAM_THUMBNAIL_JPG;

	$thumbnail = "$tempPath$uid.image-tmp.jpg";
	$log->asDump("meddream_thumbnail('$path', '$thumbnail', $imagejpegsize, '$xfersyntax', $bitsstored, $flags)");

	$r = meddream_thumbnail($path, $thumbnail, dirname(__FILE__), $imagejpegsize,
		$xfersyntax, $bitsstored, 0, 0, $backend->enableSmoothing, $flags);
	$log->asDump("meddream_thumbnail: ", substr($r, 0, 6));

	if (strlen($r) < 1) exit;

	if ($r[0] == '2')
	{
		if (function_exists('imagecreatefromstring'))
		{
			$r = substr($r, 5);
			$r = imagecreatefromstring($r);
			imagejpeg($r, $thumbnail, $quality);
			imagedestroy($r);
		}
		else
		{
			$msg = 'GD2 extension is missing';
			$log->asErr($msg);
			exit($msg);
		}
	}
	else
		if ($r[0] == 'J')
		{
			$r = substr($r, 5);
			file_put_contents($thumbnail, $r);
		}
		else
		{
			$r = substr($r, 5);

			if (file_exists($r))
				copy($r, $thumbnail);
		}

	/* resize the image further

		When using this script from Flash ("Copy image link..." in context menu),
		must force the output dimensions (`$imagejpegsize` updated in external.php).
		Too large images were already scaled down by meddream_thumbnail; but, we
		also need to scale up if the image is smaller.

		When MedDreamHTML5 calls us to obtain thumbnails and $imagejpegsize comes
		from request parameters, the resulting thumbnail must be square. The image
		coming from meddream_thumbnail often isn't.
	*/
	if ($imagejpegsize != 0)
	{
		if (!function_exists('imagecreatefromstring'))
			$log->asWarn('GD2 extension is missing, will not resize the image');
		else
		{
			$source = imagecreatefromjpeg($thumbnail);

			list($width, $height) = getimagesize($thumbnail);
			$percentwidth = $imagejpegsize / $width;
			$percentheight = $imagejpegsize / $height;
			if ($percentwidth < $percentheight)
				$percentheight = $percentwidth;
			else
				$percentwidth = $percentheight;

			$newwidth = $width * $percentwidth;
			$newheight = $height * $percentheight;
			$thumb = imagecreatetruecolor($newwidth, $newheight);

			imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
			//imagecopyresampledSMOOTH($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
			//imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

			/* ensure a square thumbnail for MedDreamHTML5 */
			if ($htmlMode)
			{
				if ($newwidth > $newheight)
				{
					$x2 = 0;
					$y2 = ($newwidth - $newheight) / 2;
					$extend = true;
				}
				else
					if ($newwidth < $newheight)
					{
						$x2 = ($newheight - $newwidth) / 2;
						$y2 = 0;
						$extend = true;
					}
					else
						$extend = false;

				if ($extend)
				{
					if (isset($_GET["width"]) && isset($_GET["height"]))
					{
						$thumb2 = imagecreatetruecolor($_GET["width"], $_GET["height"]);
						imagecopy($thumb2, $thumb, 0, 0, 0, 0, $_GET["width"], $_GET["height"]);
					}
					else
					{
						$thumb2 = imagecreatetruecolor($imagejpegsize, $imagejpegsize);
						imagecopy($thumb2, $thumb, $x2, $y2, 0, 0, $newwidth, $newheight);
					}

					imagedestroy($thumb);
					$thumb = $thumb2;
				}
			}

			/* overwrite the same temporary file once more */
			imagejpeg($thumb, $thumbnail, $quality);

			imagedestroy($thumb);
			imagedestroy($source);
		}
	}
		/*  zero $imagejpegsize is used by MedDreamHTML5 in some cases to fetch a
			JPEG stream of the large image. The file $thumbnail must remain untouched
			for performance and in order to preserve already existing quality that is
			supported only by meddream_thumbnail (not by GD2).
		*/

	/**
	 * Do not change header "Expires"
	 *
	 * Firefox Bug 583351
	 * https://bugzilla.mozilla.org/show_bug.cgi?id=583351
	 */
	header("Pragma: public");
	header("Expires: " . date("D, d M Y H:i:s GMT", strtotime('+1 seconds')));
	header("Cache-Control: maxage=0");
	header('Content-Type: image/jpeg');
	readfile($thumbnail);
	@unlink($thumbnail);

	/* also remove the source file that might be created by instanceGetMetadata() */
	$backend->pacsPreload->removeFetchedFile($path);

	$audit->log(true, $uid);
	$log->asDump('end ' . $modulename);
	exit;
?>
