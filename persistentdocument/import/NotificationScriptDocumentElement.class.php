<?php
class notification_NotificationScriptDocumentElement extends import_ScriptDocumentElement
{
    /**
     * @return notification_persistentdocument_notification
     */
    protected function initPersistentDocument()
    {
    	$codename = $this->attributes['codename'];
    	
    	$notification = notification_NotificationService::getInstance()
    		->createQuery()->add(Restrictions::eq('codename', $codename))->findUnique();
    		
    	if ($notification === null)
    	{
        	return notification_NotificationService::getInstance()->getNewDocumentInstance();
    	}
    	else
    	{
    		return $notification;
    	}
    }
}