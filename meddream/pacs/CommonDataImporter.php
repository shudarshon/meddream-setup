<?php

namespace Softneta\MedDream\Core\Pacs;


/** @brief Defines a certain method for %PACS parts different from PacsConfig */
interface CommonDataImporter
{
	/** @brief Import data returned by PacsConfig::exportCommonData()

		@param $data  An opaque array (interpretation is up to particular implementation)

		@retval string  Error message (empty if success)

		PacsConfig might set up values important for other %PACS parts.
		PACS::loadParts() calls this function with data from PacsConfig.
	 */
	public function importCommonData($data);
}
