<?php
/*
	Original name: SR.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Provides human-readable contents when viewing a DICOM SR file
 */

namespace Softneta\MedDream\Core;


/** @brief Handling of DICOM Structured Report data. */
class SR
{
	protected $log;


	/* str_replace with multiple criteria */
	protected function replaceAll($data, $array)
	{
		foreach ($array as $key => $value)
			$data = str_replace($key, $value, $data);
		return $data;
	}


	/* extracts a substring that starts after $begin and ends before $end */
	protected function cut($data, $begin, $end)
	{
		$array = explode($begin, $data);
		if (count($array) == 2)
		{
			$array = explode($end, $array[1]);
			return $array[0];
		}
		return $data;
	}


	/* get command line option for dsr2html */
	protected function getCharsetFordsr2html($defaultCharset)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $defaultCharset, ')');

		switch ($defaultCharset)
		{
			case 'ISO-IR 6':
				$setCharset = 'latin-1';
				break;

			case 'ISO-IR 100':
				$setCharset = 'latin-1';
				break;

			case 'ISO-IR 101':
				$setCharset = 'latin-2';
				break;

			case 'ISO-IR 109':
				$setCharset = 'latin-3';
				break;

			case 'ISO-IR 110':
				$setCharset = 'latin-4';
				break;

			case 'ISO-IR 148':
				$setCharset = 'latin-5';
				break;

			case 'ISO_8859-5':
			case 'ISO-IR 144':
			case 'WINDOWS-1251':
				$setCharset = 'cyrillic';
				break;

			case 'ISO_8859-6':
			case 'WINDOWS-1256':
			case 'ISO-IR 127':
				$setCharset = 'arabic';
				break;

			case 'ISO_8859-7':
			case 'WINDOWS-1253':
			case 'ISO-IR 126':
				$setCharset = 'greek';
				break;

			case 'ISO_8859-8':
			case 'WINDOWS-1255':
				$setCharset = 'hebrew';
				break;

			default:
				$setCharset = '';
				break;
		}
		if (!empty($setCharset))
			$setCharset = ' --charset-assume ' . $setCharset;

		$this->log->asDump('returning: ', $setCharset);
		$this->log->asDump('end ' . __METHOD__);
		return $setCharset;
	}


	public function __construct()
	{
		require_once __DIR__ . '/autoload.php';

		$this->log = new Logging();
	}


	/** @brief Converts a %SR file given its database reference, to a HTML document.

		@param string $imageId  Primary key in "images" database table. __This is not necessarily a UID.__

		@return array

		Format of the returned array:

		<ul>
			<li><tt>'error'</tt> - error message. Empty if success. Non-empty string will be
			    displayed in the frontend.
			<li><tt>'html'</tt> - HTML-formatted content
		</ul>
	 */

	public function getHtml($imageId)
	{
 		$modulename = basename(__FILE__);
		$this->log->asDump('begin ' . __METHOD__);

		$audit = new Audit('VIEW SR');

		$backend = new Backend(array('Structure', 'Preload'));
		if (!$backend->authDB->isAuthenticated())
		{
			$this->log->asDump('not authenticated');
			$audit->log(false, $imageId);
			return '';
		}

		$return['error'] = '';
		$return['html'] = '';

		$st = $backend->pacsStructure->instanceGetMetadata($imageId);
		if (strlen($st['error']))
		{
			$return['error'] = $st['error'];
			$audit->log(false, $imageId);
			return $return;
		}
		$dicomFile = $st['path'];

		if (($dicomFile == '') || !file_exists($dicomFile))
		{
			$return['error'] = "Can't find file '$dicomFile'";
			$audit->log(false, $imageId);
			return $return;
		}

		$htmlFile = $backend->pacsConfig->getWriteableRoot();
		if (is_null($htmlFile))
		{
			$return['error'] = 'getWriteableRoot failed';
			$audit->log(false, $imageId);
			return $return;
		}
		$htmlFile .= 'temp' . DIRECTORY_SEPARATOR . basename($dicomFile) . '.html';

		$dcmtkDir = __DIR__ . DIRECTORY_SEPARATOR . 'dcmtk' . DIRECTORY_SEPARATOR;

		$tagsClass = new DicomTags($backend);
		$tag = $tagsClass->getTagsListByPath($dicomFile, 1);
		$tag = $tagsClass->getTag($tag['tags'], 8, 5);
		$charSet = '';
		if (isset($tag['data']) && !is_null($tag['data']))
			$charSet = $tag['data'];
		unset($tag);
		unset($tagsClass);

		$setCharset = !strlen($charSet) ? $this->getCharsetFordsr2html($backend->cs->defaultCharSet) : '';

		if (PHP_OS == "WINNT")
		{
			$dsr2html = $dcmtkDir . "dsr2html-win.exe";
			$command = "CMD /C \"\"$dsr2html\" --ignore-item-errors$setCharset \"$dicomFile\" \"$htmlFile\"\"  2>&1";
		}
		else
		{
			$dsr2html = $dcmtkDir . 'dsr2html';
			$dict = $dcmtkDir . 'dicom.dic';
			$command = "DCMDICTPATH=\"$dict\" \"$dsr2html\" --ignore-item-errors$setCharset \"$dicomFile\" \"$htmlFile\"  2>&1";
		}
		$this->log->asDump('starting converter: ', $command);

		session_write_close();
		try
		{
			exec($command, $out);
		}
		catch (Exception $e)
		{
			$return["error"] = $e->getMessage();
			$this->log->asErr('exception: ' . $return['error']);
			$audit->log(false, $imageId);
			return $return;
		}

		if (!empty($out))
		{
			$out = join("\n", $out);
			$this->log->asDump('$out = ', $out);
		}
		if (!file_exists($htmlFile))
		{
			$return['error'] = "Can't convert to HTML\n";
			$return['error'] .= $out;

			$this->log->asErr($return['error'] . ": $out");
			$audit->log(false, $imageId);
			return $return;
		}

		/* remove the source file that might be created by instanceGetMetadata() */
		$backend->pacsPreload->removeFetchedFile($dicomFile);

		/* strip unsupported tags */
		$return['html'] = str_replace("\n", '', file_get_contents($htmlFile));
		$return['html'] = $this->cut($return['html'], '<body>', '</body>');
		$return['html'] = str_replace($this->cut($return['html'],
				'<div class="footnote">', '</div>'), '', $return['html']);
		$array = array(
			'</b>' => '</b> ',
			'<table>' => '',
			'</table>' => '',
			'<div>' => '<br>',
			'</div>' => '',
			'<td>' => '',
			'</td>' => '',
			'<tr>' => '',
			'</tr>' => '<br>',
			'<h1>' => '<br><b>',
			'</h1>' => '</b><br>',
			'<h2>' => '<br><br><b>',
			'</h2>' => '</b><br>',
			'<h3>' => '<br><br><b>',
			'</h3>' => '</b><br>',
			'<h4>' => '<br><br><b>',
			'</h4>' => '</b><br>',
			'<small>' => '<br>',
			'</small>' => '',
			'<span class="under">' => '',
			'</span>' => ' ',
			'<hr>' => '<br>',
			'<p>' => '<br>',
			'</p>' => '',
			"\n" => '',
			"\r" => ''
		);
		$return['html'] = trim($this->replaceAll($return['html'], $array));
		$this->log->asDump(__METHOD__ . ": \$charSet: '$charSet'");
		$return['html'] = $backend->cs->encodeWithCharset($charSet, $return['html']);
		@unlink($htmlFile);

		if (empty($return['html']) && !empty($out))
		{
			$return['error'] = "Can't convert to HTML\n";
			$return['error'] .= $out;

			$this->log->asErr($return['error']);
			$audit->log(false, $imageId);
		}
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		$audit->log(true, $imageId);
		return $return;
	}
}
