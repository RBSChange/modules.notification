<?php
/**
 * @package modules.notification
 * @method notification_NotificationService getInstance()
 */
class notification_NotificationService extends f_persistentdocument_DocumentService
{
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
		return $this->getPersistentProvider()->createQuery('modules_notification/notification');
	}
	
	/**
	 * Create a query based on 'modules_notification/notification' model.
	 * Only documents that are strictly instance of modules_notification/notification
	 * (not children) will be retrieved
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createStrictQuery()
	{
		return $this->getPersistentProvider()->createQuery('modules_notification/notification', false);
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
	 * @return notification_persistentdocument_notification | null
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
			
		$sender = $this->getSender($notification);
		$replyTo = $notification->getSendingReplyTo();
		$senderModuleName = $notification->getSendingModuleName();
		$mailService = $notification->getSendingMailService();
	
		$mailMessage = $this->composeMailMessage($mailService, $sender, $replyTo, array($to), null, null,
			$subject, $htmlBody, $senderModuleName, $notification->getAttachments());
			
		if ($mailMessage instanceof \Zend\Mail\Message)
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
	 * @param \Zend\Mail\Address $sender
	 * @param string $replyTo
	 * @param string[] $toArray
	 * @param string[] $ccArray
	 * @param string[] $bccArray
	 * @param string $subject
	 * @param string $htmlBody
	 * @param string $senderModuleName
	 * @param mixed[] $attachments
	 * @return \Zend\Mail\Message
	 */
	protected function composeMailMessage($mailService, $sender, $replyTo, $toArray, $ccArray, $bccArray, $subject, $htmlBody, $senderModuleName, $attachments = array())
	{
		/* @var $mailMessage \Zend\Mail\Message */
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
			$mailMessage->setTo($toArray);
		}
		if (is_array($ccArray) && count($ccArray))
		{
			$mailMessage->setCc($ccArray);
		}
		if (is_array($bccArray) && count($bccArray))
		{
			$mailMessage->setBcc($bccArray);
		}
		$mimeMessage = new \Zend\Mime\Message();
		

		
		$htmlBody = new \Zend\Mime\Part($htmlBody);
		$htmlBody->type = "text/html";
		$mimeMessage->addPart($htmlBody);
		
		foreach ($attachments as $attachment)
		{
			list($filePath, $mimeType, $name) = $attachment;
			
			$stream = fopen($filePath, 'r');
			if ($stream)
			{
				$attachement = new \Zend\Mime\Part($stream);
				$attachement->type = $mimeType;
				$attachement->filename = $name;
				$attachement->encoding = \Zend\Mime\Mime::ENCODING_BASE64;
				$attachement->disposition = \Zend\Mime\Mime::DISPOSITION_ATTACHMENT;
				$mimeMessage->addPart($attachement);
			}
		}
		$mailMessage->setBody($mimeMessage);
		return $mailMessage;
	}
	
	/**
	 * @param change_MailService $mailService
	 * @param \Zend\Mail\Message $mailMessage
	 * @return boolean
	 */
	protected function sendMailMessage($mailService, $mailMessage)
	{
		$ret = $mailService->send($mailMessage);
		if ($ret !== true)
		{
			Framework::error(__METHOD__.": Unable to send mail (" . $mailMessage->getSubject() . ") to " . implode(PHP_EOL, $mailMessage->getTo()) . ".");
			return false;
		}
		return true;
	}
	
	/**
	 * @param notification_persistentdocument_notification $notification
	 * @return \Zend\Mail\Address
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
		if (!f_util_StringUtils::isEmpty($senderName))
		{
			$senderName = null;
		}
		return new \Zend\Mail\Address($senderEmail, $senderName);
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

		$htmlTemplate = change_TemplateLoader::getNewInstance()->setExtension('html')
			->load('modules', 'notification', 'templates', $notification->getTemplate());
		if ($htmlTemplate !== null)
		{
			$htmlTemplate->setAttribute('notification', $attributes);
			$htmlTemplate->setAttribute('replacement', $replacementArray);
			$htmlBody = $htmlTemplate->execute();
		}
		else
		{
			Framework::error(__METHOD__ . ' Template not found: ' . $notification->getTemplate());
			$htmlBody = '';
		}
		return array('subject' => $subject, 'htmlBody' => $htmlBody);
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
	 * @param integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
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
	 * @param integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
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
}