<?php
/**
 * notification_PreviewAction
 * @package modules.notification.actions
 */
class notification_PreviewAction extends change_Action
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$notif = $this->getDocumentInstanceFromRequest($request);
		$elements = $notif->getDocumentService()->generateBody($notif, array(), false);
		echo $elements['htmlBody'];
	}
}