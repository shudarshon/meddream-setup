<?php

/** @brief Implementation for <tt>$pacs='DCMSYS'</tt>. */
namespace Softneta\MedDream\Core\Pacs\Dcmsys;

use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='DCMSYS'</tt>. */
class PacsPartConfig extends ConfigAbstract implements ConfigIface
{
	public function configure()
	{
		/* initialize $this->config->data, import some generic settings including $this->dbms */
		$err = parent::configure();
		if (strlen($err))
			return $err;
		$cnf = $this->config->data;

		/* $pacs='DCMSYS' needs $dbms='DCMSYS' for authentication (see ../../db/DbImplDcmsys.php).
			It's simpler to ignore user input in config.php and force the mandatory value here.
		 */
		$this->dbms = 'DCMSYS';

		/* $db_host

			If we're using HTTPS, then a corresponding wrapper must be available;
			however PHP won't generate an eligible error message in that case, so
			we must compensate here
		 */
		if (empty($this->dbHost))
			return __METHOD__ . ': $db_host (config.php) is not set';
		if (strpos($this->dbHost, 'https://') !== false)
			if (!in_array('https', stream_get_wrappers()))
				return __METHOD__ . ': [DCMSYS] $db_host (config.php) is using HTTPS.' .
					"<br>\nPlease enable the openssl extension for HTTPS support.";
		if (substr($this->dbHost, -1) != '/')			/* ensure trailing path separator */
			$this->dbHost .= '/';

		return '';
	}
}
