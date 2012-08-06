<?php
namespace application\plugin\gsrc
{
	use nutshell\core\plugin\LibraryPlugin;
	use nutshell\Nutshell;
	
	/**
	 * GSRC. The Get/Set/Remove/Check alternative to CRUD
	 * @author Dean Rather
	 */
	class Gsrc extends LibraryPlugin
	{
		public function init()
		{
			// MVC is required for me to work, as I extend stuff in there.
			$this->plugin->Mvc;
			$this->plugin->MvcQuery;
		}
	}
}