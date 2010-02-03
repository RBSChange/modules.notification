<?php
/**
 * notification_SitenotificationService
 * @package notification
 */
class notification_SitenotificationService extends notification_NotificationService
{
	/**
	 * @var notification_SitenotificationService
	 */
	private static $instance;

	/**
	 * @return notification_SitenotificationService
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
	 * @return notification_persistentdocument_sitenotification
	 */
	public function getNewDocumentInstance()
	{
		return $this->getNewDocumentInstanceByModelName('modules_notification/sitenotification');
	}

	/**
	 * Create a query based on 'modules_notification/sitenotification' model
	 * @return f_persistentdocument_criteria_Query
	 */
	public function createQuery()
	{
		return $this->pp->createQuery('modules_notification/sitenotification');
	}
	
	/**
	 * @param notification_persistentdocument_notification $notification
	 */
	public function refreshRelatedNotificationsByNotification($notification)
	{
		$pp = $this->getPersistentProvider();
		$siteNotifications = $this->createQuery()->add(Restrictions::childOf($notification->getId()))->find();
		foreach ($siteNotifications as $siteNotification)
		{
			$this->updateLabelAndCodename($siteNotification, $notification, $siteNotification->getWebsite());
			$pp->updateDocument($siteNotification);	
		}
	}
	
	/**
	 * @param website_persistentdocument_website $website
	 */
	public function refreshRelatedNotificationsByWebsite($website)
	{
		$pp = $this->getPersistentProvider();
		$siteNotifications = $this->createQuery()->add(Restrictions::eq('website', $website))->find();
		foreach ($siteNotifications as $siteNotification)
		{
			$this->updateLabelAndCodename($siteNotification, $this->getParentOf($siteNotification), $website);
			$pp->updateDocument($siteNotification);
		}
	}
	
	/**
	 * @param notification_persistentdocument_sitenotification $document
	 * @param Integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function preSave($document, $parentNodeId = null)
	{
		parent::preSave($document, $parentNodeId);
		
		if ($parentNodeId === null)
		{
			$parentNodeId = TreeService::getInstance()->getInstanceByDocument($document)->getParent()->getId();
		}
		$parent = DocumentHelper::getDocumentInstance($parentNodeId);
		$website = $document->getWebsite();
				
		// Check sitenotification unicity by parent and website.
		$query = $this->createQuery();
		$query->add(Restrictions::childOf($parentNodeId));
		$query->add(Restrictions::eq('website', $website));
		$query->add(Restrictions::ne('id', $document->getId()));
		if (count($query->find()) > 0)
		{
			throw new BaseException('Only one sitenotification per notification and website!', 'modules.notification.bo.general.Error-must-be-unique', array('parentLabel' => $parent->getLabel(), 'siteLabel' => $website->getLabel()));
		}
		
		// Update label and code.
		$this->updateLabelAndCodename($document, $parent, $website);
	}
	
	/**
	 * @param notification_persistentdocument_sitenotification $document
	 * @param notification_persistentdocument_notification $parent
	 * @param website_persistentdocument_website $website
	 */
	private function updateLabelAndCodename($document, $parent, $website)
	{
		$document->setLabel(f_Locale::translate('&modules.notification.bo.general.Sitenotification-label-template;', array('parentLabel' => $parent->getLabel(), 'siteLabel' => $website->getLabel())));
		$document->setCodename($parent->getCodename().'/'.$website->getId());
		if ($document->getAvailableparameters() == null)
		{
			$document->setAvailableparameters($parent->getAvailableparameters());
		}
		
		if ($document->getSubject() == null) {$document->setSubject($parent->getSubject());}
		if ($document->getBody() == null) {$document->setBody($parent->getBody());}
		if ($document->getHeader() == null) {$document->setHeader($parent->getHeader());}
		if ($document->getFooter() == null) {$document->setFooter($parent->getFooter());}
		if ($document->getTemplate() == null) {$document->setTemplate($parent->getTemplate());}
	}
}