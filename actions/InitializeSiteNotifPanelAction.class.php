<?php
/**
 * notification_InitializeSiteNotifPanelAction
 * @package modules.notification.actions
 */
class notification_InitializeSiteNotifPanelAction extends change_JSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$lang = $this->getLang();
		$result = array('notif' => array(), 'website' => array());
		$notification = $this->getDocumentInstanceFromRequest($request);
		$notifs = notification_SitenotificationService::getInstance()
			->createQuery()->add(Restrictions::childOf($notification->getId()))->find();
		
		$usedWebsite = array(0);
		
		$nodes = array();
		$ls = LocaleService::getInstance();
		foreach ($notifs as $notif) 
		{
			/* @var $notif notification_persistentdocument_sitenotification */
			$website = $notif->getWebsite();
			$usedWebsite[] = $website->getId();
			$info = array(
				'id' => $notif->getId(),
				'status' => $notif->getPublicationstatus(),
				'langs' => implode(', ', $notif->getI18nInfo()->getLangs()),
				'websiteid' => $website->getId(), 
				'website' => ($website->isLangAvailable($lang) ? $website->getLabel() : ($website->getVoLabel() . ' [' . $ls->transBO('m.uixul.bo.languages.' . $website->getLang(), array('ucf')) . ']')),
			);
			$nodes[] = $info;
		}
		$result['nodes'] = $nodes;

		foreach (website_WebsiteService::getInstance()->createQuery()->add(Restrictions::notin('id', $usedWebsite))->find() as $website)
		{
			/* @var $website website_persistentdocument_website */
			$result['website'][] = array('id' => $website->getId(), 'label' => ($website->isLangAvailable($lang) ? $website->getLabel() : $website->getVoLabel()));
		}
		return $this->sendJSON($result);
	}
}