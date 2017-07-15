<?php

namespace Softneta\MedDream\Core;


/** @brief %Constants shared between AuthDB and PACSes.

	@todo The code that uses literal constants (for example, @c FOR_WORKSTATION)
	      from this class is not fully testable in automatic fashion as constants
	      can't be mocked. Must use getters instead.
 */
class Constants
{
	/** @brief Manufacturer's website root

		For "Register" function, system::register(), and indirectly for the
		"BUY NOW" button; the latter must be disabled ONLY in system::connect()
		instead of setting this string to an empty value.
	 */
	const HOME_URL = 'http://www.softneta.com';

	/** @brief EXPERIMENTAL: command constructors to throw an exception instead of calling exit().

		<ul>
		  <li>0: traditional exit()
		  <li>1: exception
		  <li>2: exception + more detailed output in handlers
		</ul>
	 */
	const EXCEPTIONS_NO_EXIT = 1;

	/** @brief Using MedDream in MedDream Workstation, Optomed Workstation and their derivatives */
	const FOR_WORKSTATION = false;

	/** @brief Using MedDream in Surgery Workstation */
	const FOR_SW = false;

	/** @brief Using MedDream in MedDreamRIS */
	const FOR_RIS = false;

	/** @brief Using MedDream in DICOMDIR Viewer */
	const FOR_DICOMDIR = false;

	/** @brief Additional adjustments for <tt>$pacs='DCMSYS'</tt> */
	const FOR_DCMSYS = false;

	public $FDL = false;
	const DL_USER = 'example_user';
	const DL_PASSWORD = '';
	const DL_SESS_HDR = '';
	const DL_DB = '';
	const DL_REGENERATE = false;	/* true: update cached files .dcm.md and .dcm.thumbnail-$size.jpg */

	/** @name Parts of generated DICOM UIDs */
	/**@{*/
	const ROOT_UID = '1.3.6.1.4.1.44316';       /**< @brief Root UID from IANA */
	const PRODUCT_ID = '1';                     /**< @brief Indicates "MedDream" */
	/**@}*/

	/** @brief Value of the Series Description attribute for our PRs

		New PRs will have this Description. The detection whether existing
		PRs are created by us, also uses this value.
	 */
	const PR_SERIES_DESC = 'presentation state';


	public function __construct()
	{
		if (self::FOR_DCMSYS)
			$this->FDL = isset($_COOKIE['suid']);
	}
}
