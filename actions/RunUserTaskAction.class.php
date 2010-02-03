<?php
/**
 * @date Wed Apr 25 12:17:33 CEST 2007
 * @author intportg
 * This class is used to execute a user task.
 * It has to be called after a commentary/decision form submit to perform the task.
 */
class task_RunUserTaskAction extends f_action_BaseAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		// Get the parameters.
		$task = $this->getDocumentInstanceFromRequest($request);
		$decision   = $request->getParameter('decision');
		$commentary   = $request->getParameter('commentary');

		// Perform the task.
		if ($task)
		{
			TaskHelper::getUsertaskService()->execute($task, $decision, $commentary);
		}

		return self::getSuccessView();
	}
}