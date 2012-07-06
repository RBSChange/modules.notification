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
		$paths = change_FileResolver::getNewInstance()->getPaths('modules', 'notification', 'templates');
		$itemArray = array();
		foreach ($paths as $path)
		{
			/* @var $path string */
			$dir = new DirectoryIterator($path);
			foreach ($dir as $file)
			{
				/* @var $file SplFileInfo */
				if ($file->isFile() && $file->isReadable())
				{
					$template = null;
					$fileName = $file->getFileName();
					if (f_util_StringUtils::endsWith($fileName, '.html'))
					{
						$template = substr($fileName, 0, -5);
						if (!isset($itemArray[$template]))
						{
							$itemArray[$template] = new list_Item($template, $template);
						}
					}
				}
			}
		}
		return array_values($itemArray);
	}
}