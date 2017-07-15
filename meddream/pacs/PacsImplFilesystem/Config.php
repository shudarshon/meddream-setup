<?php

/** @brief Implementation for <tt>$pacs='FileSystem'</tt>. */
namespace Softneta\MedDream\Core\Pacs\Filesystem;

use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='FileSystem'</tt>. */
class PacsPartConfig extends ConfigAbstract implements ConfigIface
{
	public function configure()
	{
		/* initialize $this->config->data, import some generic settings */
		$err = parent::configure();
		if (strlen($err))
			return $err;
		$cnf = $this->config->data;

		/* $archive_dir_prefix */
		$sl = strlen($this->archiveDirPrefix);
		if (!$sl)
			return __METHOD__ . ': $archive_dir_prefix (config.php) is not set';
		$lc = $this->archiveDirPrefix[$sl - 1];
		if (($lc != "/") && ($lc != "\\"))
			$this->archiveDirPrefix .= DIRECTORY_SEPARATOR;
		clearstatcache(false, $this->archiveDirPrefix);
		if (!@is_dir($this->archiveDirPrefix))
			return __METHOD__ . ": \$archive_dir_prefix (config.php) is not a directory: '" .
				$this->archiveDirPrefix . "'";

		return '';
	}


	public function supportsAuthentication()
	{
		return false;
	}
}
