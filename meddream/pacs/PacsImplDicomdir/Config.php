<?php

/** @brief Implementation for <tt>$pacs='DICOMDIR'</tt> (pseudo-PACS for the DICOMDIR Viewer).

	@note Only a manual test (not an integration test) of swf\data.php, dicom.php, flv.php,
	      SR.php was performed.
 */
namespace Softneta\MedDream\Core\Pacs\Dicomdir;

use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='DICOMDIR'</tt>. */
class PacsPartConfig extends ConfigAbstract implements ConfigIface
{
	public function getWriteableRoot()
	{
		return session_save_path() . DIRECTORY_SEPARATOR;
	}


	public function configure()
	{
		/* $dbms (already imported by parent)

			Legacy config.php tells that this variable is ignored; however DB.php will
			react to 'MySQL' etc accordingly, and that means unnecessary troubleshooting.
			Let's clean it out.
		 */
		$this->dbms = '';

		/* Note how we are *not* using ConfigAbstract.

			ConfigAbstract validates $local_aet and $forward_aets if not empty. This
			indicates a brave and inquisitive user but ignoring any changes is still
			better. On the other hand, the Forward feature would be quite interesting,
			and feasible, in the DICOMDIR Viewer.

			ConfigAbstract also imports $sop_class_blacklist that is useful when
			displaying the study. A certain problem, though, is to transfer it to md-swf
			that parses the DICOMDIR file directly and builds the study structure
			without using StructureIface. Probably in AuthIface::onConnect?

			ConfigAbstract also imports $medreport_root_link that is listed in legacy
			config.php as an ordinary parameter, not under the "not required" section.
			Was this really supported?
		*/

		return '';
	}
}
