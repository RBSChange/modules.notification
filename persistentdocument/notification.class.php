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
	
	/**
	 * @var MailService
	 */
	private $sendingMailService;
	
	/**
	 * @param MailService $mailService
	 */
	public function setSendingMailService($mailService)
	{
		$this->sendingMailService = $mailService;
	}
	
	/**
	 * @return MailService
	 */
	public function getSendingMailService()
	{
		return $this->sendingMailService;
	}
	
	/**
	 * @var array
	 */
	private $callbackFunctions = array();
	
	/**
	 * @var array
	 */
	private $callbackParams = array();
	
	/**
	 * @var array
	 */
	private $globalParams = array();
	
	/**
	 * @param object|string $object
	 * @param string $methodName
	 * @param array<string, mixed> $params
	 * @return notification_persistentdocument_notification
	 */
	public function registerCallback($object, $methodName, $params)
	{
		$key = (is_string($object) ? $object : get_class($object)) . '::' . $methodName;
		$this->callbackFunctions[$key] = array($object, $methodName);
		$this->callbackParams[$key] = $params;
		return $this;
	}
	
	/**
	 * @param object|string $object
	 * @param string $methodName
	 */
	public function unregisterCallback($object, $methodName)
	{
		$key = (is_string($object) ? $object : get_class($object)) . '::' . $methodName;
		unset($this->callbackFunctions[$key]);
		unset($this->callbackParams[$key]);
	}
	
	public function unregisterAllCallback()
	{
		$this->callbackFunctions = array();
		$this->callbackParams = array();
	}
	
	/**
	 * @param string $name
	 * @param string $value
	 * @return notification_persistentdocument_notification
	 */
	public function addGlobalParam($name, $value)
	{
		$this->globalParams[$name] = $value;
		return $this;
	}
	
	/**
	 */
	public function removeAllGlobalParam()
	{
		$this->globalParams = array();
	}
	
	/**
	* @param string $to
	 * @return boolean
	 */
	public function send($to)
	{
		if (!empty($to))
		{
			$cf = $this->callbackFunctions;
			$cp = $this->callbackParams;
			$this->unregisterAllCallback();
			return $this->getDocumentService()->buildParamsAndSend($this, $to, $this->globalParams, $cf, $cp);
		}
		return false;
	}
	
	/**
	 * @param users_persistentdocument_user $user
	 * @return boolean
	 */
	public function sendToUser($user)
	{
		if ($user instanceof users_persistentdocument_user && $user->isPublished())
		{
			$user->getDocumentService()->registerNotificationCallback($this, $user);
			return $this->send($user->getEmail());
		}
		return false;
	}
	
	/**
	 * @param contactcard_persistentdocument_contact $contact
	 * @return boolean
	 */
	public function sendToContact($contact)
	{
		$result = false;
		if ($contact instanceof contactcard_persistentdocument_contact && $contact->isPublished())
		{
			$emails = $contact->getEmailAddresses();
			if (count($emails))
			{
				$contact->getDocumentService()->registerNotificationCallback($this, $contact);
				$result = true;
				foreach ($emails as $email)
				{
					$result = $result && $this->send($email);
				}
			}
		}
		return $result;
	}
}