<?php
/**
 * @author intportg
 * @package modules.notification
 */
class notification_SiteNotificationListener
{
	public function onPersistentDocumentUpdated($sender, $params)
	{
		if ($params['document'] instanceof website_persistentdocument_website)
		{
			notification_SitenotificationService::getInstance()->refreshRelatedNotificationsByWebsite($params['document']);
		}
	}	
}