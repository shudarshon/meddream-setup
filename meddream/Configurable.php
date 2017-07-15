<?php

namespace Softneta\MedDream\Core;


/** @brief Helper interface for the Configuration class */
interface Configurable
{
	/** @brief Configure itself via Configuration.php

		@return  string  Error message (empty if success)

		The main purpose of this method is to import any related settings via
		Configuration. Of course it is suitable for any other setup.
	 */
	public function configure();
}
