<?php
/**
 * @author intbonjf
 * @date Wed Jul 18 10:10:07 CEST 2007
 */
class notification_ListTemplatesService extends BaseService implements list_ListItemsService
{
	/**
	 * @var notification_ListTemplatesService
	 */
	private static $instance;

	/**
	 * @return notification_ListTemplatesService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}


	/**
	 * Returns an array of available templates for the notification module.
	 *
	 * @return array
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