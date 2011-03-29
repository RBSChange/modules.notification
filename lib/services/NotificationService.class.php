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
	 * @var MailService
	 */
	private $mailService = null;

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
	 * Sets the MailService to use. May be a MailService instance or a
	 * mailbox_MessageService instance.
	 *
	 * @param MailService | mailbox_MessageService $mailService
	 */
	public function setMessageService($mailService)
	{
		$this->mailService = $mailService;
	}

	/**
	 * @return String
	 */
	private function getDefaultSender()
	{
		if (defined('MOD_NOTIFICATION_SENDER'))
		{
			return MOD_NOTIFICATION_SENDER;
		}
		return Framework::getDefaultNoReplySender();
	}

	/**
	 * @param notification_persistentdocument_notification $notification
	 * @param mail_MessageRecipients $recipients
	 * @param string[] $replacementArray
	 * @param string $senderModuleName
	 * @param string $replyTo
	 * @param string $overrideSenderEmail
	 * @param boolean $replaceUnkownKeys
	 * @return boolean
	 */
	public function send($notification, $recipients, $replacementArray, $senderModuleName, $replyTo = null, $overrideSenderEmail = null, $replaceUnkownKeys = true)
	{
		if ($this->mailService === null)
		{
			$this->mailService = MailService::getInstance();
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
				$sender = $overrideSenderEmail;
			}
			else if ($notification->isContextLangAvailable())
			{
				$sender = $notification->getSenderEmail();
			}
			else
			{
				$sender = $notification->getVoSenderEmail();
			}
			
			// If there is no sender set, get the default one.
			if (empty($sender))
			{
				$sender = $this->getDefaultSender();
			}
			
			$mailMessage = $this->mailService->getNewMailMessage();
			$mailMessage->setModuleName($senderModuleName);

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
			$mailMessage->setSender($sender);
			$mailMessage->setRecipients($recipients);					
			$mailMessage->setEncoding('utf-8');
			$mailMessage->setHtmlAndTextBody($htmlBody, $textBody);
			
			// Send mail and return the result.
			$ret = $this->mailService->send($mailMessage);
			if ($ret !== true)
			{
				Framework::error(__METHOD__.": Unable to send mail (" . $subject . ") to " . implode(', ', $recipients->getTo()) . ".");
			}

			return $ret;
		}
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

		if(is_array($replacements))
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
			$string = preg_replace('#\{(.*?)\}#', '-', $string);
		}
		return $string;
	}

	/**
	 * Get the active notification matching a codename.
	 * @param String $codeName
	 * @param Integer $websiteId
	 * @return notification_persistentdocument_notification
	 */
	public function getByCodeName($codeName, $websiteId = null)
	{
		// Get the website id.
		if ($websiteId === null)
		{		
			$currentWebsite = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
			if ($currentWebsite !== null)
			{
				$websiteId = $currentWebsite->getId();
			}
		}
		
		return $this->doGetByCodeName($codeName, $websiteId);
	}

	/**
	 * Get the active notification matching a codename.
	 * @param String $codeName
	 * @param Integer $websiteId
	 * @return notification_persistentdocument_notification
	 */
	public function getByCodeNameAndSuffix($codeName, $suffix, $websiteId = null)
	{
		// Get the website id.
		if ($websiteId === null)
		{		
			$currentWebsite = website_WebsiteModuleService::getInstance()->getCurrentWebsite();
			if ($currentWebsite !== null)
			{
				$websiteId = $currentWebsite->getId();
			}
		}
		
		$notif = $this->doGetByCodeName($codeName.'_'.$suffix, $websiteId);
		if ($notif === null)
		{
			$notif = $this->doGetByCodeName($codeName, $websiteId);
		}
		return $notif;
	}

	/**
	 * @param String $codeName
	 * @param Integer $websiteId
	 * @return notification_persistentdocument_notification or null
	 */
	protected function doGetByCodeName($codeName, $websiteId)
	{
		$query = $this->createQuery();
		$query->add(Restrictions::published());
		$query->add(Restrictions::orExp(
			Restrictions::eq('codename', $codeName.'/'.$websiteId),
			Restrictions::eq('codename', $codeName)
		));
		$query->addOrder(Order::desc('codename'));
		$notifications = $query->find();
		return f_util_ArrayUtils::firstElement($notifications);
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
		if (!isset($replacements['company-name']))
		{
			// Temporary test to manage framework with and without this method.
			// In the future, only the first case should be conserved.
			if (f_util_ClassUtils::methodExists('Framework', 'getCompanyName'))
			{
				$replacements['company-name'] = Framework::getCompanyName();
			}
			else
			{
				$replacements['company-name'] = AG_WEBAPP_NAME;
			}
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
	 * @deprecated (will be removed in 4.0) Use send() instead.
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
}
