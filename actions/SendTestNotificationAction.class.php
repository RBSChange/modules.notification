<?php
/**
 * notification_SendTestNotificationAction
 * @package modules.notification.actions
 */
class notification_SendTestNotificationAction extends change_JSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$result = array();

		$notification = $this->getDocumentInstanceFromRequest($request);
		$recipients = change_MailService::getInstance()->getRecipientsArray(explode(',', $request->getParameter('emails')));		
		if (!$notification->getDocumentService()->send($notification, $recipients, array(), 'notification', null, null, false))
		{
			return $this->sendJSONError(LocaleService::getInstance()->transBO('m.notification.bo.general.error-sending-mails'));
		}		
		return $this->sendJSON($result);
	}
}