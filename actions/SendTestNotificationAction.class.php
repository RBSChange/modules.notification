<?php
/**
 * notification_SendTestNotificationAction
 * @package modules.notification.actions
 */
class notification_SendTestNotificationAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$result = array();

		$notification = $this->getDocumentInstanceFromRequest($request);
		$recipients = new mail_MessageRecipients();
		$recipients->setTo($request->getParameter('emails'));
		
		if (!$notification->getDocumentService()->send($notification, $recipients, array(), 'notification', null, null, false))
		{
			return $this->sendJSONError(LocaleService::getInstance()->transBO('m.notification.bo.general.error-sending-mails'));
		}		
		return $this->sendJSON($result);
	}
}