<?php
class notification_NotificationWebService extends webservices_WebServiceBase
{
	/**
	 * @param string $notificationCode
	 * @param string $domainName
	 * @param string $lang
	 * @param string[] $destEmailArray
	 * @param string[] $replacementArrayKeys
	 * @param string[] $replacementArrayValues
	 * @param string $senderModuleName
	 * @param string $replyTo
	 * @param string $senderEmail
	 * @return boolean
	 */
	public function send($notificationCode, $domainName, $lang, $destEmailArray, $replacementArrayKeys, $replacementArrayValues , $senderModuleName, $replyTo, $senderEmail)
	{
		$destEmailArray = $this->getWsdlTypes()->getType('ArrayOfstring')->formatPhpValue($destEmailArray);
		$replacementArrayKeys = $this->getWsdlTypes()->getType('ArrayOfstring')->formatPhpValue($replacementArrayKeys);
		$replacementArrayValues = $this->getWsdlTypes()->getType('ArrayOfstring')->formatPhpValue($replacementArrayValues);
		if (!is_array($destEmailArray) || count($destEmailArray) === 0)
		{
			throw new Exception("Can not send mail to nobody");
		}
		$this->setLang($lang);
		$websiteId = null;
		$wsm = website_WebsiteModuleService::getInstance();
		$website = $wsm->getWebsiteByUrl($domainName);
		if ($website !== null)
		{
			$wsm->setCurrentWebsite($website);
			$websiteId = $website->getId();
		}
		$ns = notification_NotificationService::getInstance();
		$notification = $ns->getByCodeName($notificationCode, $websiteId);
		if ($notification === null)
		{
			return false;
		}
		if (is_array($replacementArrayKeys) && is_array($replacementArrayValues) && count($replacementArrayKeys) === count($replacementArrayValues))
		{
			$replacementArray = array_combine($replacementArrayKeys, $replacementArrayValues);
		}
		else 
		{
			$replacementArray = array();
		}
		$recipients = new mail_MessageRecipients();
		$recipients->setTo($destEmailArray);
		return $ns->send($notification, $recipients, $replacementArray, $senderModuleName, $replyTo, $senderEmail);
	}
}