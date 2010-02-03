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
	 * @param string $moduleName
	 * @param string $treeType
	 * @param array<string, string> $nodeAttributes
	 */	
	protected function addTreeAttributes($moduleName, $treeType, &$nodeAttributes)
	{
		$nodeAttributes['canBeDeleted'] = ($this->canBeDeleted() ? 'true' : 'false');
    	$nodeAttributes['publicationstatus'] = $this->getPublicationstatus();
    	$nodeAttributes['author'] = $this->getAuthor();
    	$nodeAttributes['codename'] = $this->getCodename();
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
}