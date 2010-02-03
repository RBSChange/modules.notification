<?php
/**
 * notification_InitializeSiteNotifPanelAction
 * @package modules.notification.actions
 */
class notification_InitializeSiteNotifPanelAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$result = array('notif' => array(), 'website' => array());
		$notification = $this->getDocumentInstanceFromRequest($request);
		$notifs = notification_SitenotificationService::getInstance()
			->createQuery()->add(Restrictions::childOf($notification->getId()))->find();
			
		$usedWebsite = array(0);
		
		foreach ($notifs as $notif) 
		{
			$website = $notif->getWebsite();
			$usedWebsite[] = $website->getId();
			$info = array('id' => $notif->getId(), 
						'websiteid' => $website->getId(), 
						'website' => $website->getLabel(),
						'status' => f_Locale::translateUI(DocumentHelper::getPublicationstatusLocaleKey($notif))
			);
			$result['notif'][] = $info;
		}
				
		$result['website'] = website_WebsiteService::getInstance()->createQuery()
			->add(Restrictions::notin('id', $usedWebsite))
			->setProjection(Projections::property('id', 'id'), Projections::property('label', 'label'))
			->find();
				
		return $this->sendJSON($result);
	}
}