<?php
/**
 * notification_DefaultValuesJSONAction
 * @package modules.notification.actions
 */
class notification_DefaultValuesJSONAction extends generic_DefaultValuesJSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		if (!$request->hasParameter('duplicate') || $request->hasParameter('duplicate') != 'true')
		{
			return parent::_execute($context, $request);
		}
		
		$document = $this->getDocumentInstanceFromRequest($request);
		if (!$document instanceof notification_persistentdocument_notification) 
		{
			throw new Exception('Not valid type (notification) for parent node: ' . get_class($document));
		}
		
		$allowedProperties = explode(',', $request->getParameter('documentproperties', ''));
		$data = uixul_DocumentEditorService::getInstance()->exportFieldsData($document, $allowedProperties);

		unset($data['author']);
		return $this->sendJSON($data);
	}
}