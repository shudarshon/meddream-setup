<?php

namespace Softneta\MedDream\Core\Pacs\Dicomdir;

use Softneta\MedDream\Core\Pacs\StructureIface;
use Softneta\MedDream\Core\Pacs\StructureAbstract;


/** @brief Implementation of StructureIface for <tt>$pacs='DICOMDIR'</tt>. */
class PacsPartStructure extends StructureAbstract implements StructureIface
{
	/** @brief Implementation of StructureIface::instanceGetMetadata().

		@param string   $instanceUid     Contents: full path, <tt>'|'</tt>, SOP Instance UID
		@param boolean  $includePatient  Include patient-level attributes (however those will
		                                 be still empty)

		@todo In dicom.php, the 2nd component was Transfer Syntax UID that we didn't
		      need anyway even back then. In flv.php, the 2nd component was the SOP
		      Instance UID. In sr.php, path was the only component. If these differences
		      are real, then __the GUI needs to be unified__ to use the second format.
	 */
	public function instanceGetMetadata($instanceUid, $includePatient = false)
	{
		$log = $this->log;
		$log->asDump('begin ' . __METHOD__ . '(', $instanceUid, ', ', $includePatient, ')');

		$parts = explode('|', $instanceUid);

		$return = array('error' => '',
			'path' => $parts[0],
			'xfersyntax' => '',
			'sopclass' => '',
			'bitsstored' => '',
			'uid' => $parts[1]);
		if ($includePatient)
		{
			$return['firstname'] = '';
			$return['lastname'] = '';
			$return['fullname'] = '';
		}

		$log->asDump('$return = ', $return);
		$log->asDump('end ' . __METHOD__);

		return $return;
	}


	/** @brief A stub suggesting that it must not be used by the frontend. */
	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false)
	{
		$this->log->asErr('not implemented but still called');
		return array('error' => 'not implemented');
			/* the GUI must not call us, it parses the metadata itself */
	}
}
