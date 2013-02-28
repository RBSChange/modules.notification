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
		
		/* @param $notification notification_persistentdocument_notification */
		$notification = $this->getDocumentInstanceFromRequest($request);
		$notification->setSendingModuleName('notification');
		
		$error = false;
		foreach ($request->getParameter('emails') as $email)
		{
			$error = $error || !$notification->send($email);
		}
		
		if ($error)
		{
			return $this->sendJSONError(LocaleService::getInstance()->transBO('m.notification.bo.general.error-sending-mails'));
		}
		return $this->sendJSON($result);
	}
}