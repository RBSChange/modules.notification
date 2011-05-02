<?php
/**
 * notification_persistentdocument_notification
 * @package notification
 */
class notification_persistentdocument_notification extends notification_persistentdocument_notificationbase
{
	/**
	 * @return Boolean
	 */
	public function canBeDeleted()
	{
		return $this->getAuthor() != 'system';
	}
	
	/**
	 * @return String
	 */
	public function getSenderUsername()
	{
		$senderEmail = $this->getSenderEmail();
		return substr($senderEmail, 0, strpos($senderEmail, '@'));
	}
	
	/**
	 * @param String $userName
	 */
	public function setSenderUsername($userName)
	{
		$senderHost = (defined('MOD_NOTIFICATION_SENDER_HOST') ? MOD_NOTIFICATION_SENDER_HOST : Framework::getDefaultSenderHost());
		$this->setSenderEmail($userName . '@' . $senderHost);
	}
	
	/**
	 * Used in pre and post save.
	 * @var Boolean
	 */
	public $refreshChildren;
	
	// Volatile properties to configure sending.
	
	/**
	 * @var integer
	 */
	private $sendingWebsiteId;
	
	/**
	 * @param integer $websiteId
	 */
	public function setSendingWebsiteId($websiteId)
	{
		$this->sendingWebsiteId = $websiteId;
	}
	
	/**
	 * @return integer
	 */
	public function getSendingWebsiteId()
	{
		return $this->sendingWebsiteId;
	}
	
	/**
	 * @var string
	 */
	private $sendingLang;
	
	/**
	 * @param string $lang
	 */
	public function setSendingLang($lang)
	{
		$this->sendingLang = $lang;
	}
	
	/**
	 * @return string
	 */
	public function getSendingLang()
	{
		return $this->sendingLang;
	}
	
	/**
	 * @var string
	 */
	private $sendingModuleName;
	
	/**
	 * @param string $moduleName
	 */
	public function setSendingModuleName($moduleName)
	{
		$this->sendingModuleName = $moduleName;
	}
	
	/**
	 * @return string
	 */
	public function getSendingModuleName()
	{
		return $this->sendingModuleName;
	}
	
	/**
	 * @var string
	 */
	private $sendingReplyTo;
	
	/**
	 * @param string $replyTo
	 */
	public function setSendingReplyTo($replyTo)
	{
		$this->sendingReplyTo = $replyTo;
	}
	
	/**
	 * @return string
	 */
	public function getSendingReplyTo()
	{
		return $this->sendingReplyTo;
	}
	
	/**
	 * @var string
	 */
	private $sendingSenderEmail;
	
	/**
	 * @param string $senderEmail
	 */
	public function setSendingSenderEmail($senderEmail)
	{
		$this->sendingSenderEmail = $senderEmail;
	}
	
	/**
	 * @return string
	 */
	public function getSendingSenderEmail()
	{
		return $this->sendingSenderEmail;
	}
	
	/**
	 * @var string
	 */
	private $sendingSenderName;
	
	/**
	 * @param string $senderName
	 */
	public function setSendingSenderName($senderName)
	{
		$this->sendingSenderName = $senderName;
	}
	
	/**
	 * @return string
	 */
	public function getSendingSenderName()
	{
		return $this->sendingSenderName;
	}
}