<?php
/**
 * @package modules.notification
 * @method notification_ListTemplatesService getInstance()
 */
class notification_ListTemplatesService extends change_BaseService implements list_ListItemsService
{
	/**
	 * Returns an array of available templates for the notification module.
	 * @return list_Item[]
	 */
	public function getItems()
	{
		$dir = new DirectoryIterator(FileResolver::getInstance()->setPackageName('modules_notification')->getPath('templates'));

		$templateArray = array();
		foreach ($dir as $file)
		{
			if ($file->isReadable() && $file->isFile())
			{
				$fileName = $file->getFileName();
				if (f_util_StringUtils::endsWith($fileName, '.all.all.html'))
				{
					$template = substr($fileName, 0, -13);
				}
				else if (f_util_StringUtils::endsWith($fileName, '.all.all.txt'))
				{
					$template = substr($fileName, 0, -12);
				}
				if (!in_array($template, $templateArray))
				{
					$templateArray[] = $template;
				}
			}
		}

		$itemArray = array();
		foreach ($templateArray as $template)
		{
			$itemArray[] = new list_Item($template, $template);
		}

		return $itemArray;
	}
}