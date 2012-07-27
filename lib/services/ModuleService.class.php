<?php
/**
 * @package modules.notification.lib.services
 */
class notification_ModuleService extends ModuleBaseService
{
	/**
	 * Singleton
	 * @var notification_ModuleService
	 */
	private static $instance = null;
	
	/**
	 * @return notification_ModuleService
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}
	
	/**
	 * @param string $stringLine
	 */
	public function log($stringLine)
	{
		if (Framework::inDevelopmentMode() || Framework::isInfoEnabled())
		{
			LoggingService::getInstance()->namedLog($stringLine, 'notification');
		}
	}
}