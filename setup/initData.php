<?php
class notification_Setup extends object_InitDataSetup
{
	public function install()
	{
		$this->executeModuleScript('init.xml');
   	 	if (ModuleService::getInstance()->moduleExists('webservices'))
   	 	{
   	 		$this->executeModuleScript('webservices.xml');
   	 	}
	}
}