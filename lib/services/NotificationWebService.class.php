<?php
class notification_NotificationWebService extends webservices_WebServiceBase
{
	/**
	 * @param string wstificationCode
	 * @param string wsmainName
	 * @param string wsng
	 * @param string[] wsstEmailArray
	 * @param string[] wsplacementArrayKeys
	 * @param string[] wsplacementArrayValues
	 * @param string wsnderModuleName
	 * @param string wsplyTo
	 * @param string wsnderEmail
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
		$ws = website_WebsiteService::getInstance();
		$website = $ws->getByUrl($domainName);
		if ($website !== null)
		{
			$ws->setCurrentWebsite($website);
		}
		
		$ns = notification_NotificationService::getInstance();
		$notification = $ns->getConfiguredByCodeName($notificationCode);
		if ($notification === null)
		{
			throw new Exception("Notification $notificationCode not found");
		}
		
		$notification->setSendingSenderEmail($senderEmail);
		$notification->setSendingModuleName($senderModuleName);
		$notification->setSendingReplyTo($replyTo);
		if (is_array($replacementArrayKeys) && is_array($replacementArrayValues) && count($replacementArrayKeys) === count($replacementArrayValues))
		{
			$replacementArray = array_combine($replacementArrayKeys, $replacementArrayValues);
			foreach ($replacementArray as $key => $value)
			{
				$notification->addGlobalParam($key, $value);
			}
		}
		$result = true;
		foreach ($destEmailArray as $to)
		{
			$result = $result && $notification->send($to);
		}		
		return $result;
	}
}