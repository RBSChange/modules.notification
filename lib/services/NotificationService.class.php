<?php
/**
 * @package modules.notification
 */
class notification_NotificationService extends f_persistentdocument_DocumentService
{
	/**
	 * @var notification_NotificationService
	 */
	private static $instance;

	/**
	 * @return notification_NotificationService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

	/**
	 * @return notification_persistentdocument_notification
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_notification/notification');
	}

	/**
	 * Create a query based on 'modules_notification/notification' model
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_notification/notification');
	}
	
	/**
	 * Create a query based on 'modules_notification/notification' model.
	 * Only documents that are strictly instance of modules_notification/notification
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->pp->createQuery('modules_notification/notification', false);
	}
	
	/**
	 * @param string $codeName
	 * @param integer $websiteId
	 * @param string $lang
	 * @return notification_persistentdocument_notification or null
	 */
	public function getConfiguredByCodeName($codeName, $websiteId = null, $lang = null)
	{
		// Get the website id.
		if ($websiteId === null)
		{
			$websiteId = website_WebsiteModuleService::getInstance()->getCurrentWebsite()->getId();
		}
	
		// Get the lang.
		if ($lang === null)
		{
			$lang = RequestContext::getInstance()->getLang();
		}
	
		return $this->doGetConfiguredByCodeName($codeName, $websiteId, $lang);
	}
	
	/**
	 * @param string $codeName
	 * @param integer $websiteId
	 * @param string $lang
	 * @return notification_persistentdocument_notification or null
	 */
	public function getConfiguredByCodeNameAndSuffix($codeName, $suffix, $websiteId = null, $lang = null)
	{
		// Get the website id.
		if ($websiteId === null)
		{
			$websiteId = website_WebsiteModuleService::getInstance()->getCurrentWebsite()->getId();
		}
	
		// Get the lang.
		if ($lang === null)
		{
			$lang = RequestContext::getInstance()->getLang();
		}
	
		// Get the notification.
		$notif = $this->doGetConfiguredByCodeName($codeName.'_'.$suffix, $websiteId, $lang);
		if ($notif === null)
		{
			$notif = $this->doGetConfiguredByCodeName($codeName, $websiteId, $lang);
		}
		return $notif;
	}
	
	/**
	 * @param string $codeName
	 * @param integer $websiteId
	 * @param string $lang
	 * @return notification_persistentdocument_notification or null
	 */
	protected function doGetConfiguredByCodeName($codeName, $websiteId, $lang)
	{
		$notifications = array();
		$rc = RequestContext::getInstance();
		try
		{
			$rc->beginI18nWork($lang);
	
			$query = $this->createQuery();
			$query->add(Restrictions::published());
			$query->add(Restrictions::orExp(
				Restrictions::eq('codename', $codeName.'/'.$websiteId),
				Restrictions::eq('codename', $codeName)
			));
			$query->addOrder(Order::desc('codename'));
			$notifications = $query->find();
				
			$rc->endI18nWork();
		}
		catch (Exception $e)
		{
			Framework::exception($e);
			$rc->endI18nWork($e);
		}
	
		/* @var $notif notification_persistentdocument_notification */
		$notif = f_util_ArrayUtils::firstElement($notifications);
		if ($notif !== null)
		{
			$notif->removeAllGlobalParam();
			$notif->unregisterAllCallback();
			$notif->setSendingWebsiteId($websiteId);
			$notif->setSendingLang($lang);
			$notif->setSendingMailService(MailService::getInstance());
		}
		else if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__ . '("' . $codeName . '", ' . $websiteId . ', ' . $lang . ') no notification found');
		}
		return $notif;
	}
	
	
	/**
	 * @internal use $notification->send()
	 * @param notification_persistentdocument_notification $notification
	 * @param string $to
	 * @param array<string, string> $globalParams
	 * @param array<object, string> $callbackFunctions
	 * @param array<string, mixed> $callbackParams
	 */
	public function buildParamsAndSend($notification, $to, $globalParams, $callbackFunctions, $callbackParams)
	{
		
		$result = false;
		$nms = notification_ModuleService::getInstance();
		
		$rc = RequestContext::getInstance();
		$lang = $notification->getSendingLang();
		if ($lang == null) 
		{
			$lang = $rc->getLang();
		}
		$nms->log($notification->getCodename() . ' -> ' . $to);
		
		$ws = website_WebsiteModuleService::getInstance();
		$websiteId = $notification->getSendingWebsiteId();
		$oldWebsiteId = $ws->getCurrentWebsite()->getId();
		if ($websiteId > 0)
		{
			$ws->setCurrentWebsiteId($websiteId);
		}
		
		try
		{
			$rc->beginI18nWork($lang);
		
			$replacements = $globalParams;
			foreach ($callbackFunctions as $key => $callback)
			{
				try 
				{
					$inParams = $callbackParams[$key];
					$res = call_user_func($callback, $inParams);
					if (is_array($res))
					{
						$replacements = array_merge($replacements, $res);
					}
				}
				catch (Exception $e)
				{
					Framework::exception($e);
				}
			}
			$replacements = $this->completeReplacements($replacements);
			
			if (Framework::inDevelopmentMode())
			{
				$nms->log($notification->getCodename() . ' DP ' . $notification->getAvailableparameters());
				$nms->log($notification->getCodename() . ' GP {' . implode('}, {', array_keys($replacements)). '}');
			}
			
			//Fix parameters
			foreach ($replacements as $key => $value)
			{
				$notification->addGlobalParam($key, $value);
			}
			
			//For compatibility
			if ($notification->getSendingMailService() === null)
			{
				$notification->setSendingMailService(MailService::getInstance());
			}
						
			if (!$this->doSend($notification, $to, $replacements))
			{
				Framework::error(__METHOD__ . ' Can\'t send notification: ' . $notification->getCodename() . ' TO ' . $to);
			}
			else
			{
				$result = true;
			}
	
			if ($oldWebsiteId > 0)
			{
				$ws->setCurrentWebsiteId($oldWebsiteId);
			}
			$rc->endI18nWork();
		}
		catch (Exception $e)
		{
			if ($oldWebsiteId > 0)
			{
				$ws->setCurrentWebsiteId($oldWebsiteId);
			}
			$rc->endI18nWork($e);
		}
		return $result;		
	}
	
	/**
	 * 
	 * @param notification_persistentdocument_notification $notification
	 * @param string $to
	 * @param array $replacements
	 * @param boolean|null $replaceUnkownKeys
	 */
	protected function doSend($notification, $to, $replacements, $replaceUnkownKeys = null)
	{	
		// Render contents.
		if ($replaceUnkownKeys === null) 
		{
			$replaceUnkownKeys = !Framework::inDevelopmentMode();
		}
		$contents = $this->generateBody($notification, $replacements, $replaceUnkownKeys);
		$subject = $contents['subject'];
		$htmlBody = $contents['htmlBody'];
		$textBody = $contents['textBody'];
		
		$sender = $this->getSender($notification);
		$replyTo = $notification->getSendingReplyTo();
		$senderModuleName = $notification->getSendingModuleName();
		$mailService = $notification->getSendingMailService();
				
		$mailMessage = $this->composeMailMessage($mailService, $sender, $replyTo, array($to), null, null, 
			$subject, $htmlBody, $textBody, $senderModuleName, $notification->getAttachments());
			
		if ($mailMessage instanceof MailMessage)
		{
			return $this->sendMailMessage($mailService, $mailMessage);
		}
		elseif (is_bool($mailMessage))
		{
			return $mailMessage;
		}
		return false;
	
	}
		
	/**
	 * 
	 * @param MailService $mailService
	 * @param string $sender
	 * @param string $replyTo
	 * @param string[] $toArray
	 * @param string[] $ccArray
	 * @param string[] $bccArray
	 * @param string $subject
	 * @param string $htmlBody
	 * @param string $textBody
	 * @param string $senderModuleName
	 * @return MailMessage|boolean
	 */
	protected function composeMailMessage($mailService, $sender, $replyTo, $toArray, $ccArray, $bccArray, $subject, $htmlBody, $textBody, $senderModuleName, $attachments = array())
	{
		$mailMessage = $mailService->getNewMailMessage();
		$mailMessage->setModuleName($senderModuleName);	
		if (!empty($replyTo))
		{
			$replyToOk = true;
			foreach (explode(',', $replyTo) as $address)
			{
				if (!validation_EmailValidator::isEmail(trim($address)))
				{
					$replyToOk = false;
					break;
				}
			}
			if ($replyToOk)
			{
				$mailMessage->setReplyTo($replyTo);
			}
		}
			
		$mailMessage->setSubject($subject);
		$mailMessage->setSender($sender);
		if (is_array($toArray) && count($toArray))
		{
			$mailMessage->setReceiver(implode(',', $toArray));
		}
		if (is_array($ccArray) && count($ccArray))
		{
			$mailMessage->setCc(implode(',', $ccArray));
		}
		if (is_array($bccArray) && count($bccArray))
		{
			$mailMessage->setBcc(implode(',', $bccArray));
		}
		$mailMessage->setEncoding('utf-8');
		$mailMessage->setHtmlAndTextBody($htmlBody, $textBody);
		foreach ($attachments as $attachment)
		{
			list($filePath, $mimeType, $name) = $attachment;
			$mailMessage->addAttachment($filePath, $mimeType, $name);
		}
		return $mailMessage;
	}

	/**
	 * @param MailService $mailService
	 * @param MailMessage $mailMessage
	 * @return boolean
	 */
	protected function sendMailMessage($mailService, $mailMessage)
	{
		$ret = $mailService->send($mailMessage);
		if ($ret !== true)
		{
			Framework::error(__METHOD__.": Unable to send mail (" . $mailMessage->getSubject() . ") to " .$mailMessage->getReceiver() . ".");
			return false;
		}
		return true;		
	}
	
	/**
	 * @param notification_persistentdocument_notification $notification
	 * @return string
	 */
	protected function getSender($notification)
	{
		// Get the sender email...
		$senderEmail = $notification->getSendingSenderEmail();
		if (empty($senderEmail))
		{
			$senderEmail = $notification->getSenderEmail();
		}			
		if (empty($senderEmail))
		{
			$senderEmail = defined('MOD_NOTIFICATION_SENDER') ? MOD_NOTIFICATION_SENDER : Framework::getDefaultNoReplySender();
		}
		
		// Get the sender name...
		$senderName = $notification->getSendingSenderName();
		if (empty($senderName))
		{
			$senderName = $notification->getSenderName();
		}			
		if (empty($senderName))
		{
			$senderName = Framework::getConfigurationValue('modules/notification/defaultSenderName');
		}
		
		// Construct the sender.
		if (!empty($senderName))
		{
			return '"' . $senderName . '" < ' . $senderEmail . ' >';
		}
		return $senderEmail;
	}
	
	/**
	 * @param notification_persistentdocument_notification $notification
	 * @param string[] $replacementArray
	 * @param boolean $replaceUnkownKeys
	 */
	public function generateBody($notification, $replacementArray, $replaceUnkownKeys = true)
	{
		// Complete replacements with global data.
		$replacementArray = $this->completeReplacements($replacementArray);

		if ($notification->isContextLangAvailable())
		{
			$subject = $this->applyReplacements($notification->getSubject(), $replacementArray, $replaceUnkownKeys);
			$body = $this->applyReplacements($notification->getBody(), $replacementArray, $replaceUnkownKeys);
			$header = $this->applyReplacements($notification->getHeader(), $replacementArray, $replaceUnkownKeys);
			$footer = $this->applyReplacements($notification->getFooter(), $replacementArray, $replaceUnkownKeys);
		}
		else
		{
			$subject = $this->applyReplacements($notification->getVoSubject(), $replacementArray, $replaceUnkownKeys);
			$body = $this->applyReplacements($notification->getVoBody(), $replacementArray, $replaceUnkownKeys);
			$header = $this->applyReplacements($notification->getVoHeader(), $replacementArray, $replaceUnkownKeys);
			$footer = $this->applyReplacements($notification->getVoFooter(), $replacementArray, $replaceUnkownKeys);
		}
		
		$attributes = $replacementArray;
		$attributes['subject'] = $subject;
		$attributes['header'] = f_util_HtmlUtils::renderHtmlFragment($header);
		$attributes['footer'] = f_util_HtmlUtils::renderHtmlFragment($footer);
		$attributes['body'] = f_util_HtmlUtils::renderHtmlFragment($body);

		$htmlTemplate = TemplateLoader::getInstance()->setPackageName('modules_notification')->setMimeContentType(K::HTML)->load($notification->getTemplate());
		$htmlTemplate->setAttribute('notification', $attributes);
		$htmlTemplate->setAttribute('replacement', $replacementArray);
		$htmlBody = $htmlTemplate->execute();

		try
		{
			$textTemplate = TemplateLoader::getInstance()->setPackageName('modules_notification')->setMimeContentType('txt')->load($notification->getTemplate());
			$textTemplate->setAttribute('notification', $attributes);
			$textTemplate->setAttribute('replacement', $replacementArray);
			$textBody = f_util_StringUtils::htmlToText($textTemplate->execute());
		}
		catch (TemplateNotFoundException $e)
		{
			Framework::warn(__METHOD__ . " no plain text template found: " . $e->getMessage());
			$textBody = '';
		}
		
		return array('subject' => $subject, 'htmlBody' => $htmlBody, 'textBody' => $textBody);
	}

	/**
	 * Apply the remplacements to a given string.
	 * @param string $string
	 * @param array<string> $replacements
	 * @return string
	 */
	private function applyReplacements($string, $replacements, $replaceUnkownKeys = true)
	{
		if (!$string)
		{
			return '';
		}

		if (is_array($replacements))
		{
			foreach ($replacements as $key => $value)
			{
				if (!is_array($value))
				{
					$string = str_replace(array('{' . $key . '}', '%7B' . $key . '%7D'), $value, $string);
				}
				else if (Framework::isDebugEnabled())
				{
					Framework::debug(__METHOD__ . ' following value has invalid type and is skipped: ' . var_export($value, true));			
				}
			}
		}

		// Remove the not-replaced elements.
		if ($replaceUnkownKeys)
		{
			$string = preg_replace('#\{(.*?)\}#', '', $string);
		}
		return $string;
	}
		
	/**
	 * Complete the replacements.
	 * @param Array<String, String> $replacements
	 * @return Array<String, String>
	 */
	public function completeReplacements($replacements)
	{
		if (!isset($replacements['website-url']))
		{
			$replacements['website-url'] = website_WebsiteModuleService::getInstance()->getCurrentWebsite()->getUrl();
		}
		return $replacements;
	}
	
	/**
	 * @param notification_persistentdocument_notification $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function preSave($document, $parentNodeId = null)
	{
		parent::preSave($document, $parentNodeId);
		
		if ($document->isPropertyModified('label') || $document->isPropertyModified('codeName'))
		{
			$document->refreshChildren = true;
		}
	}
	
	/**
	 * @param notification_persistentdocument_notification $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function postSave($document, $parentNodeId = null)
	{
		parent::postSave($document, $parentNodeId);
		
		if ($document->refreshChildren)
		{
			notification_SitenotificationService::getInstance()->refreshRelatedNotificationsByNotification($document);
		}
	}
	
	/**
	 * @param notification_persistentdocument_notification $notification
	 * @param string[] $parameterNames eg: array('toto', 'tata', 'titi')
	 */
	public function addAvailableParameters($notification, $parameterNames)
	{
		$parameters = $notification->getAvailableparameters();
		$subst = explode(',', str_replace(array(' ', '{', '}'), '', $parameters));
		foreach ($parameterNames as $name)
		{
			if (!in_array($name, $subst))
			{
				$parameters .= ', {' . $name . '}';
			}
		}
		$notification->setAvailableparameters($parameters);
	}
	
	/**
	 * @param notification_persistentdocument_notification $notification
	 * @param string $parameterName
	 * @return boolean
	 */
	public function hasAvailableParameters($notification, $parameterName)
	{
		$parameters = $notification->getAvailableparameters();
		$subst = explode(',', str_replace(array(' ', '{', '}'), '', $parameters));
		$name = str_replace(array(' ', '{', '}'), '', $parameterName);
		return in_array($name, $subst);
	}
	
	/**
	 * @param notification_persistentdocument_notification $document
	 * @param string $moduleName
	 * @param string $treeType
	 * @param array<string, string> $nodeAttributes
	 */	
	public function addTreeAttributes($document, $moduleName, $treeType, &$nodeAttributes)
	{
		$nodeAttributes['canBeDeleted'] = ($document->canBeDeleted() ? 'true' : 'false');
    	$nodeAttributes['publicationstatus'] = $document->getPublicationstatus();
    	$nodeAttributes['author'] = $document->getAuthor();
    	$nodeAttributes['codename'] = $document->getCodename();
	}
	
	/**
	 * @param notification_persistentdocument_notification $document
	 * @param string $forModuleName
	 * @param array $allowedSections
	 * @return array
	 */
	public function getResume($document, $forModuleName, $allowedSections = null)
	{
		$data = parent::getResume($document, $forModuleName, $allowedSections);
		
		$lang = RequestContext::getInstance()->getUILang();
		$data['description']['description'] = $document->isLangAvailable($lang) ? $document->getDescriptionForLang($lang) : $document->getVoDescription();
		
		return $data;
	}
	
	// Deprecated.
	
	/**
	 * @deprecated (will be removed in 4.0) use $notification->registerCallback() and $notification->send()
	 */
	public function sendNotificationCallback($notification, $recipients, $callback = null, $callbackParameter = null)
	{
		if (Framework::isInfoEnabled() && Framework::inDevelopmentMode())
		{
			Framework::info(__METHOD__ . ' Deprecated call');
			Framework::info(f_util_ProcessUtils::getBackTrace());
		}
		
		if ($notification === null)
		{
			if (Framework::isInfoEnabled())
			{
				Framework::info(__METHOD__ . ' No notification to send.');
			}
			return false;
		}
		
		if (is_array($callback))
		{
			$notification->registerCallback($callback[0], $callback[1], $callbackParameter);
		}
		$result = true;
		foreach ($recipients->getTo() as $to)
		{
			$result = $result && $notification->send($to);
		}
		return $result;
	}
	
	/**
	 * @deprecated (will be removed in 4.0) use $notification->send()
	 */
	public function send($notification, $recipients, $replacementArray, $senderModuleName, $replyTo = null, $overrideSenderEmail = null, $replaceUnkownKeys = null)
	{
		if (Framework::isInfoEnabled() && Framework::inDevelopmentMode())
		{
			Framework::info(__METHOD__ . ' Deprecated call');
			Framework::info(f_util_ProcessUtils::getBackTrace());
		}
		
		if ($notification === null || !$recipients->hasTo())
		{
			Framework::warn(__METHOD__.": notification does not exist or is not available or has not recipient: no notification sent.");
			return false;
		}
	
		if (is_array($replacementArray))
		{
			foreach ($replacementArray as $name => $value)
			{
				$notification->addGlobalParam($name, $value);
			}
		}
	
		if ($senderModuleName !== null)
		{
			$notification->setSendingModuleName($senderModuleName);
		}
	
		if ($replyTo !== null)
		{
			$notification->setSendingReplyTo($replyTo);
		}
			
		if ($overrideSenderEmail !== null)
		{
			$notification->setSendingSenderEmail($overrideSenderEmail);
		}
			
		$result = true;
		foreach ($recipients->getTo() as $to)
		{
			$result = $result && $notification->send($to);
		}
		return $result;
	}
	
	/**
	 * @deprecated (will be removed in 4.0) use getConfiguredByCodeName
	 */
	public function getByCodeName($codeName, $websiteId = null)
	{
		if (Framework::isInfoEnabled() && Framework::inDevelopmentMode())
		{
			Framework::info(__METHOD__ . ' Deprecated call');
			Framework::info(f_util_ProcessUtils::getBackTrace());
		}
		
		// Get the website id.
		if ($websiteId === null)
		{		
			$currentWebsite = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
			if ($currentWebsite !== null)
			{
				$websiteId = $currentWebsite->getId();
			}
		}
		
		$query = $this->createQuery();
		$query->add(Restrictions::published());
				
		// Get the specialized notification if exists, else get the base one.
		if ($websiteId !== null)
		{
			$query->add(Restrictions::orExp(Restrictions::eq('codename', $codeName.'/'.$websiteId), Restrictions::eq('codename', $codeName)));
		}
		else 
		{
			$query->add(Restrictions::eq('codename', $codeName));
		}
		$query->addOrder(Order::desc('codename'));
		$notifications = $query->find();
			
		return f_util_ArrayUtils::firstElement($notifications);
	}
	
	/**
	 * @deprecated (will be removed in 4.0) use send() instead.
	 */
	public function sendMail($notification, $receivers, $replacements = array())
	{
		$recipients = new mail_MessageRecipients();
		$recipients->setTo($receivers);
		$this->send($notification, $recipients, $replacements, null);
	}

	/**
	 * @deprecated (will be removed in 4.0) use getByCodeName
	 */
	public function getNotificationByCodeName($codeName, $websiteId = null)
	{
		return $this->getByCodeName($codeName, $websiteId);
	}
	
	
	/**
	 * @deprecated (will be removed in 4.0) use setSendingMailService on notification.
	 */
	public function setMessageService($mailService)
	{
		
	}
}
