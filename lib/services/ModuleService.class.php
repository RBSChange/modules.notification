<?php
/**
 * @package modules.notification
 * @method notification_ModuleService getInstance()
 */
class notification_ModuleService extends ModuleBaseService
{	
	/**
	 * @param string $stringLine
	 */
	public function log($stringLine)
	{
		if (Framework::inDevelopmentMode() || Framework::isInfoEnabled())
		{
			change_LoggingService::getInstance()->namedLog($stringLine, 'notification');
		}
	}
}