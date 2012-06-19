<?php
/**
 * @package modules.notification
 * @method notification_ModuleService getInstance()
 */
class notification_ModuleService extends ModuleBaseService
{	
	/**
	 * @var string
	 */
	private $logFilePath;
	
	protected function __construct()
	{
		parent::__construct();
		if (Framework::inDevelopmentMode() || Framework::isInfoEnabled())
		{
			$this->logFilePath = f_util_FileUtils::buildWebeditPath('log', 'notification', 'notification.log');
			if (!file_exists($this->logFilePath))
			{
				f_util_FileUtils::writeAndCreateContainer($this->logFilePath, gmdate('Y-m-d H:i:s') . "\t Created" . PHP_EOL);
			}
		}
	}
	
	/**
	 * @param string $stringLine
	 */
	public function log($stringLine)
	{
		if ($this->logFilePath !== null)
		{
			error_log(gmdate('Y-m-d H:i:s') . "\t" . $stringLine . PHP_EOL, 3, $this->logFilePath);
		}
	}
}