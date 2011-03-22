<?php

class ThreadController extends Base_Controller_Action
{

	public function viewAction()
	{
		$this->noRender();
		debug($this->getRequest()->getParams());
		debug($this->_getAllParams());
	}


}

