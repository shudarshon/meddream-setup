<?php

/** @brief Implementation for <tt>$pacs='dcm4chee-arc-5'</tt>. */
namespace Softneta\MedDream\Core\Pacs\Dcm4chee_arc_5;

use Softneta\MedDream\Core\PathUtils;
use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='dcm4chee-arc-5'</tt>. */
class PacsPartConfig extends ConfigAbstract implements ConfigIface
{
	public function configure()
	{
		/* initialize $this->config->data, import some generic settings including $this->dbms */
		$err = parent::configure();
		if (strlen($err))
			return $err;
		$cnf = $this->config->data;

		/* validate actual values of $dbms */
		if (($this->dbms != "MYSQL") && ($this->dbms != "OCI8"))
			return __METHOD__ . ": [dcm4chee-arc-lite] Unsupported value of \$dbms '" . $this->dbms . "' in config.php. " .
				'Allowed are "MySQL", "OCI8".';

		/* validate $db_host

			Import and validation take place in AuthDB and below. However their messages
			are not for the end user -- they do not refer to config.php and parameter
			name there.
		 */
		if (empty($this->dbHost))
			return __METHOD__ . ': $db_host (config.php) is not set';

		/* $login_form_db */
		if (($this->dbms != 'OCI8'))
			if (empty($this->loginFormDb))
				return __METHOD__ . ': $login_form_db (config.php) is not set';

		/* $archive_dir_prefix */
		if (!strlen($this->archiveDirPrefix))
			return __METHOD__ . ': $archive_dir_prefix (config.php) is not set';
		$this->storageDevices = array();
		$devicesRaw = explode("\n", $this->archiveDirPrefix);
		foreach ($devicesRaw as $dev)
		{
			$assignment = explode('=', $dev);
			if (count($assignment) < 2)
				return __METHOD__ . ": storage device definition must be in form STORAGE_ID=STORAGE_URI: '$dev'";
			$k = trim(array_shift($assignment));
			$v = trim(implode('=', $assignment));	/* the path is allowed to contain '=' elsewhere */
			$v = PathUtils::stripUriPrefix($v);

			/* strip the trailing path separator */
			$len = strlen($v);
			if (($v[$len - 1] == '/') || ($v[$len - 1] == '\\'))
				$v = substr($v, 0, $len - 1);

			if (array_key_exists($k, $this->storageDevices))
				return __METHOD__ . ": duplicate storage device '$k'";
			$this->storageDevices[$k] = $v;
		}
		$this->archiveDirPrefix = '';

		return '';
	}


	public function exportCommonData($what = null)
	{
		$r = parent::exportCommonData($what);
		$r['storage_devices'] = $this->storageDevices;
		return $r;
	}
}
