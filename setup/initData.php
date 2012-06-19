<?php
class notification_Setup extends object_InitDataSetup
{
	public function install()
	{
		try
		{
			$scriptReader = import_ScriptReader::getInstance();
	   	 	$scriptReader->executeModuleScript('notification', 'init.xml');
	   	 	if (ModuleService::getInstance()->moduleExists('webservices'))
	   	 	{
	   	 		$scriptReader->executeModuleScript('notification', 'webservices.xml');
	   	 	}
		}
		catch (Exception $e)
		{
			echo "ERROR: " . $e->getMessage() . "\n";
			Framework::exception($e);
		}
	}
}