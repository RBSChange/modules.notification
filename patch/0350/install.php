<?php
/**
 * notification_patch_0350
 * @package modules.notification
 */
class notification_patch_0350 extends patch_BasePatch
{
	/**
	 * Entry point of the patch execution.
	 */
	public function execute()
	{
		$newPath = f_util_FileUtils::buildWebeditPath('modules/notification/persistentdocument/notification.xml');
		$newModel = generator_PersistentModel::loadModelFromString(f_util_FileUtils::read($newPath), 'notification', 'notification');
		$newProp = $newModel->getPropertyByName('description');
		f_persistentdocument_PersistentProvider::getInstance()->addProperty('notification', 'notification', $newProp);
	}
}