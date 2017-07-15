<?php
/*
	Original name: ForeignPath.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Maps paths valid on a different system, to corresponding local mounts
 */

namespace Softneta\MedDream\Core;


/** @brief Mapping of paths from a different system, to corresponding local mounts.

	To be used after fetching the path from the database.

	@c $foreign_path_mapping in config.php defines the replacements. Format:
	<tt>"FROM1|TO1\nFROM2|TO2\n..."</tt>.

	Make sure to define the replacement string uniquely enough, so that (for
	example) it surely catches the beginning of the original path and not something
	in the middle.
 */
class ForeignPath implements Configurable
{
	protected $log;         /**< @brief An instance of Logging */
	protected $cnf;         /**< @brief An instance of Configuration */

	/** @brief Mapping table.

		Keys are substrings to replace, and values are replacements. A dumb substring
		replacement will be performed.
	 */
	public $mappingTable = array();


	public function __construct(Logging $log = null, Configuration $cnf = null)
	{
		if (is_null($log))
			$log = new Logging();
		$this->log = $log;

		if (is_null($cnf))
		{
			$cnf = new Configuration();
			$err = $cnf->load();
			if (strlen($err))
			{
				$log->asErr('fatal: ' . $err);
				exit($err);
			}
		}
		$this->cnf = $cnf;
	}


	public function configure()
	{
		$this->mappingTable = array();

		$cfg = $this->cnf->data;

		/* convert our configuration variable to array */
		if (!isset($cfg['foreign_path_mapping']))
			return '';		/* the functionality is optional */
		$prm = trim($cfg['foreign_path_mapping']);
		$tbl = explode("\n", $prm);
		foreach ($tbl as $mapping)
		{
			$repl = explode('|', $mapping);
			if (count($repl) != 2)
				return "wrong syntax in \$foreign_path_mapping (config.php): '$mapping'";
			$key = $repl[0];
			if (array_key_exists($key, $this->mappingTable))
				return "\$foreign_path_mapping (config.php): FROM substring already defined: '$key'";

			$this->mappingTable[$key] = $repl[1];
		}

		return '';
	}


	/** @brief Remap "remote" part of the filesystem path to a "local" one

		Returns the unchanged @p $value immediately if no mappings are configured.
	 */
	public function toLocal($value)
	{
		if (!count($this->mappingTable))
			return $value;
		return str_replace(array_keys($this->mappingTable), array_values($this->mappingTable), $value);
	}


	/** @brief Remap "local" part of the filesystem path to a "remote" one

		@todo Not implemented at the moment, the use case isn't clear enough.
	 */
	public function toRemote($value)
	{
		$this->log->asWarn(__METHOD__ . ": not implemented, leaving the original value '$value'");
		return $value;
	}
}
