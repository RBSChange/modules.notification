<?php
/**
 * @package modules.notification
 * @method notification_SitenotificationService getInstance()
 */
class notification_SitenotificationService extends notification_NotificationService
{
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
		return $this->getPersistentProvider()->createQuery('modules_notification/sitenotification');
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
	 * @param integer $parentNodeId Parent node ID where to save the document (optionnal => can be null !).
	 * @return void
	 */
	protected function preSave($document, $parentNodeId = null)
	{
		parent::preSave($document, $parentNodeId);
		
		if ($parentNodeId === null)
		{
			$parentNodeId = TreeService::getInstance()->getInstanceByDocument($document)->getParent()->getId();
		}
		$parent = notification_persistentdocument_notification::getInstanceById($parentNodeId);
		$website = $document->getWebsite();
				
		// Check sitenotification unicity by parent and website.
		$query = $this->createQuery();
		$query->add(Restrictions::childOf($parentNodeId));
		$query->add(Restrictions::eq('website', $website));
		$query->add(Restrictions::ne('id', $document->getId()));
		if (count($query->find()) > 0)
		{
			$subst = array(
				'parentLabel' => ($parent->isContextLangAvailable() ? $parent->getLabel() : $parent->getVoLabel()),
				'siteLabel' => ($website->isContextLangAvailable() ? $website->getLabel() : $website->getVoLabel())
			);
			throw new BaseException('Only one sitenotification per notification and website!', 'modules.notification.bo.general.Error-must-be-unique', $subst);
		}
		
		// Update label and code.
		$this->updateLabelAndCodename($document, $parent, $website);
	}
	
	/**
	 * @param notification_persistentdocument_sitenotification $document
	 * @param string $forModuleName
	 * @param array $allowedSections
	 * @return array
	 */
	public function getResume($document, $forModuleName, $allowedSections = null)
	{
		$data = parent::getResume($document, $forModuleName, $allowedSections);
		
		$lang = RequestContext::getInstance()->getUILang();
		$notif = $this->getParentOf($document);
		$data['description']['description'] = $notif->isLangAvailable($lang) ? $notif->getDescriptionForLang($lang) : $notif->getVoDescription();
		
		return $data;
	}
	
	/**
	 * @param notification_persistentdocument_sitenotification $document
	 * @param notification_persistentdocument_notification $parent
	 * @param website_persistentdocument_website $website
	 */
	private function updateLabelAndCodename($document, $parent, $website)
	{
		$subst = array(
			'parentLabel' => ($parent->isContextLangAvailable() ? $parent->getLabel() : $parent->getVoLabel()),
			'siteLabel' => ($website->isContextLangAvailable() ? $website->getLabel() : $website->getVoLabel())
		);
		$document->setLabel(LocaleService::getInstance()->trans('m.notification.bo.general.sitenotification-label-template', array('ucf'), $subst));
		$document->setCodename($parent->getCodename().'/'.$website->getId());
		if ($document->getAvailableparameters() == null)
		{
			$document->setAvailableparameters($parent->getAvailableparameters());
		}
	}
}