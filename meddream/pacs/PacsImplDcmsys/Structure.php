<?php

namespace Softneta\MedDream\Core\Pacs\Dcmsys;

use Softneta\MedDream\Core\Pacs\StructureIface;
use Softneta\MedDream\Core\Pacs\StructureAbstract;


/** @brief Implementation of StructureIface for <tt>$pacs='DCMSYS'</tt>.

	Stubs suggesting that this part of md-core must not be used by the frontend.
 */
class PacsPartStructure extends StructureAbstract implements StructureIface
{
	/** @brief A stub suggesting that it must not be used by the frontend. */
	public function instanceGetMetadata($instanceUid, $includePatient = false)
	{
		$this->log->asErr('not implemented but still called');
		return array('error' => 'not implemented');
	}


	/** @brief A stub suggesting that it must not be used by the frontend. */
	public function studyGetMetadata($studyUid, $disableFilter = false, $fromCache = false)
	{
		$this->log->asErr('not implemented but still called');
		return array('error' => 'not implemented');
	}
}
