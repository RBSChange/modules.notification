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
	 * @var string
	 */
	private $logFilePath;

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
	
	protected function __construct()
	{
		parent::__construct();
		if (Framework::inDevelopmentMode() || Framework::isInfoEnabled())
		{
			$this->logFilePath = f_util_FileUtils::buildWebeditPath('log', 'notification', 'notification.log');
			if (!file_exists($this->logFilePath))
			{
				f_util_FileUtils::writeAndCreateContainer($this->logFilePath, gmdate('Y-m-d H:i:s')."\t Created" . PHP_EOL);
			}
		}
	}
	
	public function log($stringLine)
	{
		if ($this->logFilePath !== null)
		{
			error_log(gmdate('Y-m-d H:i:s')."\t".$stringLine . PHP_EOL, 3, $this->logFilePath);
		}
	}
}