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
			self::$instance = new self();
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
	 * @param integer $accessorId
	 * @return notification_persistentdocument_notification or null
	 */
	public function getConfiguredByCodeName($codeName, $websiteId = null, $lang = null, $accessorId = null)
	{
		// Get the website id.
		if ($websiteId === null)
		{		
			$websiteId = website_WebsiteService::getInstance()->getCurrentWebsite()->getId();
		}
		
		// Get the lang.
		if ($lang === null && $accessorId !== null)
		{
			$p = users_UsersprofileService::getInstance()->getByAccessorId($accessorId);
			if ($p && $p->getLcid())
			{
				$lang = LocaleService::getInstance()->getCode($p->getLcid());
			}
		}		
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
	 * @param integer $accessorId
	 * @return notification_persistentdocument_notification or null
	 */
	public function getConfiguredByCodeNameAndSuffix($codeName, $suffix, $websiteId = null, $lang = null, $accessorId = null)
	{
		// Get the website id.
		if ($websiteId === null)
		{
			$websiteId = website_WebsiteService::getInstance()->getCurrentWebsite()->getId();
		}
	
		// Get the lang.
		if ($lang === null && $accessorId !== null)
		{
			$p = users_UsersprofileService::getInstance()->getByAccessorId($accessorId);
			if ($p && $p->getLcid())
			{
				$lang = LocaleService::getInstance()->getCode($p->getLcid());
			}
		}
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
			$notif->setSendingMailService(change_MailService::getInstance());
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
	
		$ws = website_WebsiteService::getInstance();
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
				
			// Fix parameters.
			foreach ($replacements as $key => $value)
			{
				$notification->addGlobalParam($key, $value);
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
			$subject, $htmlBody, $textBody, $senderModuleName);
			
		if ($mailMessage instanceof Zend_Mail)
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
	 * @param change_MailService $mailService
	 * @param string $sender
	 * @param string $replyTo
	 * @param string[] $toArray
	 * @param string[] $ccArray
	 * @param string[] $bccArray
	 * @param string $subject
	 * @param string $htmlBody
	 * @param string $textBody
	 * @param string $senderModuleName
	 * @return Zend_Mail
	 */
	protected function composeMailMessage($mailService, $sender, $replyTo, $toArray, $ccArray, $bccArray, $subject, $htmlBody, $textBody, $senderModuleName)
	{
		/* @var $mailMessage Zend_Mail */
		$mailMessage = $mailService->getNewMessage();
		if ($replyTo !== null)
		{
			$errors = new validation_Errors();
			$validate = new validation_EmailsValidator();
			$validate->validate(new validation_Property(null, $replyTo), $errors);
			if ($errors->isEmpty())
			{
				$mailMessage->setReplyTo($replyTo);
			}
		}
		$mailMessage->setSubject($subject);
		$mailMessage->setFrom($sender);
		if (is_array($toArray) && count($toArray))
		{
			$mailMessage->addTo(implode(',', $toArray));
		}
		if (is_array($ccArray) && count($ccArray))
		{
			$mailMessage->addCc(implode(',', $ccArray));
		}
		if (is_array($bccArray) && count($bccArray))
		{
			$mailMessage->addBcc(implode(',', $bccArray));
		}
		$mailMessage->setBodyHtml($htmlBody);
		$mailMessage->setBodyText($textBody);
		return $mailMessage;
	}
	
	/**
	 * @param change_MailService $mailService
	 * @param Zend_Mail $mailMessage
	 * @return boolean
	 */
	protected function sendMailMessage($mailService, $mailMessage)
	{
		$ret = $mailService->send($mailMessage);
		if ($ret !== true)
		{
			Framework::error(__METHOD__.": Unable to send mail (" . $mailMessage->getSubject() . ") to " . $mailMessage->getReceiver() . ".");
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
			$senderEmail = Framework::getDefaultNoReplySender();
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

		$htmlTemplate = TemplateLoader::getInstance()->setPackageName('modules_notification')->setMimeContentType('html')->load($notification->getTemplate());
		$htmlTemplate->setAttribute('notification', $attributes);
		$htmlTemplate->setAttribute('replacement', $replacementArray);
		$htmlBody = $htmlTemplate->execute();

		try
		{
			$textTemplate = TemplateLoader::getInstance()->setPackageName('modules_notification')->setMimeContentType('txt')->load($notification->getTemplate());
			$textTemplate->setAttribute('notification', $attributes);
			$textTemplate->setAttribute('replacement', $replacementArray);
			$textBody = f_util_HtmlUtils::htmlToText($textTemplate->execute());
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
			$replacements['website-url'] = website_WebsiteService::getInstance()->getCurrentWebsite()->getUrl();
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
	 * @param array<string, string> $attributes
	 * @param integer $mode
	 * @param string $moduleName
	 */
	public function completeBOAttributes($document, &$attributes, $mode, $moduleName)
	{
		$attributes['canBeDeleted'] = $document->canBeDeleted() ? 'true' : 'false';
		if ($mode & DocumentHelper::MODE_CUSTOM)
		{
			$attributes['publicationstatus'] = $document->getPublicationstatus();
	    	$attributes['codename'] = $document->getCodename();
		}
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
	public function sendNotificationCallback($notification, $recipients, $callback = null, $callbackParameter = array())
	{
		if ($notification === null)
		{
			if (Framework::isInfoEnabled())
			{
				Framework::info(__METHOD__ . ' No notification to send.');
			}
			return false;
		}
	
		$websiteId = $notification->getSendingWebsiteId();
		$lang = $notification->getSendingLang();
	
		$result = false;
		$rc = RequestContext::getInstance();
		$ws = website_WebsiteService::getInstance();
		$oldWebsiteId = $ws->getCurrentWebsite()->getId();
		if ($websiteId > 0)
		{
			$ws->setCurrentWebsiteId($websiteId);
		}
		try
		{
			$rc->beginI18nWork($lang);
	
			$replacements = ($callback !== null) ? call_user_func($callback, $callbackParameter) : $callbackParameter;
			$ns = $notification->getDocumentService();
			$senderModuleName = $notification->getSendingModuleName();
			$replyTo = $notification->getSendingReplyTo();
			if (!$ns->send($notification, $recipients, $replacements, $senderModuleName, $replyTo))
			{
				Framework::error(__METHOD__ . ' Can\'t send notification: ' . $notification->getCodename() . ' TO' . print_r($recipients[change_MailService::TO], true));
				$result = false;
			}
			$result = true;
				
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
	 * @deprecated (will be removed in 4.0) use $notification->send()
	 */
	public function send($notification, $recipients, $replacementArray, $senderModuleName, $replyTo = null, $overrideSenderEmail = null, $replaceUnkownKeys = null)
	{
		if ($replaceUnkownKeys === null) {
			$replaceUnkownKeys = !Framework::inDevelopmentMode();
		}
	
		if ($notification === null)
		{
			Framework::warn(__METHOD__.": notification does not exist or is not available: no notification sent.");
			return false;
		}
		else
		{
			// Complete replacements with global data.
			$replacementArray = $this->completeReplacements($replacementArray);
	
			// Render contents.
			$contents = $this->generateBody($notification, $replacementArray, $replaceUnkownKeys);
			$subject = $contents['subject'];
			$htmlBody = $contents['htmlBody'];
			$textBody = $contents['textBody'];
	
			// Get the sender...
			if ($overrideSenderEmail !== null)
			{
				$notification->setSendingSenderEmail($overrideSenderEmail);
			}
			$sender = $this->getSender($notification);
				
			$mailService = $notification->getSendingMailService();
			if ($mailService === null)
			{
				$mailService = change_MailService::getInstance();
			}
	
			$mailMessage = $this->composeMailMessage($mailService, $sender, $replyTo,
				$recipients[change_MailService::TO], $recipients[change_MailService::CC],
				$recipients[change_MailService::BCC], $subject, $htmlBody, $textBody, $senderModuleName);
	
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
			$currentWebsite = website_WebsiteService::getInstance()->getCurrentWebsite();
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