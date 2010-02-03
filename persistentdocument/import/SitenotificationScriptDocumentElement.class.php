<?php
/**
 * notification_SitenotificationScriptDocumentElement
 * @package modules.notification.persistentdocument.import
 */
class notification_SitenotificationScriptDocumentElement extends import_ScriptDocumentElement
{
    /**
     * @return notification_persistentdocument_sitenotification
     */
    protected function initPersistentDocument()
    {
    	return notification_SitenotificationService::getInstance()->getNewDocumentInstance();
    }
}