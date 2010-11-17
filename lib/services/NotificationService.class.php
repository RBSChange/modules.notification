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
	 * @param Array<String=>String> $replacementArray
	 * @param String $senderModuleName
	 * @param String $replyTo
	 * @param String $overrideSenderEmail
	 * @return boolean
	 */
	public function send($notification, $recipients, $replacementArray, $senderModuleName, $replyTo = null, $overrideSenderEmail = null)
	{
		if ( is_null($this->mailService) )
		{
			$this->mailService = MailService::getInstance();
		}

		if ( is_null($notification) )
		{
			Framework::warn(__METHOD__.": notification does not exist or is not available: no notification sent.");
			return false;
		}
		else
		{
			// Complete replacements with global data.
			$replacementArray = $this->completeReplacements($replacementArray);

			if ($notification->isContextLangAvailable())
			{
				$subject = $this->applyReplacements($notification->getSubject(), $replacementArray);
				$body = $this->applyReplacements($notification->getBody(), $replacementArray);
				$header = $this->applyReplacements($notification->getHeader(), $replacementArray);
				$footer = $this->applyReplacements($notification->getFooter(), $replacementArray);
				$sender = $notification->getSenderEmail();
			}
			else
			{
				$subject = $this->applyReplacements($notification->getVoSubject(), $replacementArray);
				$body = $this->applyReplacements($notification->getVoBody(), $replacementArray);
				$header = $this->applyReplacements($notification->getVoHeader(), $replacementArray);
				$footer = $this->applyReplacements($notification->getVoFooter(), $replacementArray);
				$sender = $notification->getVoSenderEmail();
			}
			
			// If there is no sender set, get the default one.
			if (empty($sender))
			{
				$sender = $this->getDefaultSender();
			}
			
			if ($overrideSenderEmail !== null)
			{
				$sender = $overrideSenderEmail;
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
			
			$mailMessage = $this->mailService->getNewMailMessage();
			if ( ! is_null($senderModuleName) )
			{
				$mailMessage->setModuleName($senderModuleName);
			}			

			/** intessit 2008-04-28 : add replyTo feature, check if given parameters is valid mail(s) */
			if(!is_null($replyTo))
			{
				$errors = new validation_Errors();
				$validate = new validation_EmailsValidator();
				$validate->validate(new validation_Property(null, $replyTo), $errors);
				if($errors->isEmpty())
				{
					$mailMessage->setReplyTo($replyTo);
				}
			}
			
			$mailMessage->setSubject($subject);			
			$mailMessage->setSender($sender);
			$mailMessage->setRecipients($recipients);
					
			$mailMessage->setEncoding('utf-8');
			$mailMessage->setHtmlAndTextBody($htmlBody, $textBody);
			
			// Send mail and return the result
			$ret = $this->mailService->send($mailMessage);
			if (true !== $ret)
			{
				Framework::error(__METHOD__.": Unable to send mail (" . $subject . ") to " . implode(', ', $recipients->getTo()) . ".");
			}

			return $ret;
		}
	}


	/**
	 * Send the notification.
	 * @param notification_persistentdocument_notification $notification
	 * @param array<string> $receivers an array of email addresses.
	 * @param array $replacements an associative array with the word to replace as the key and the replacement as the value.
	 *
	 * @deprecated Use send() instead.
	 */
	public function sendMail($notification, $receivers, $replacements = array())
	{
		$recipients = new mail_MessageRecipients();
		$recipients->setTo($receivers);
		$this->send($notification, $recipients, $replacements, null);
	}


	/**
	 * Apply the remplacements to a given string.
	 * @param string $string
	 * @param array<string> $replacements
	 * @return string
	 */
	private function applyReplacements($string, $replacements)
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
					$string = str_replace('{' . $key . '}',	$value, $string);
				}
				else if (Framework::isDebugEnabled())
				{
					Framework::debug(__METHOD__ . ' following value has invalid type and is skipped: ' . var_export($value, true));			
				}
			}
		}

		// Remove the not-replaced elements.
		$string = preg_replace('#\{(.*)\}#', '-', $string);
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
		if (is_null($websiteId))
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
	 * Get the active notification matching a codename.
	 * @param String $codeName
	 * @param Integer $websiteId
	 * @return notification_persistentdocument_notification
	 * @deprecated use getByCodeName
	 */
	public function getNotificationByCodeName($codeName, $websiteId = null)
	{
		return $this->getByCodeName($codeName, $websiteId);
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
}
