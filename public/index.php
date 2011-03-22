<?php

/**
 *@version $Id: index.php 17 2011-03-22 07:34:19Z wc5d@hotmail.com $
 */

$start = microtime(true);
// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Application configuration file
defined('APPLICATION_CONFIG_FILE')
    || define('APPLICATION_CONFIG_FILE', APPLICATION_PATH . '/configs/application.ini');

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
)));

require_once APPLICATION_PATH . '/const.php';

/** Zend_Application */
require_once 'Zend/Application.php';  

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV, 
    APPLICATION_CONFIG_FILE
);

try {
	$application->bootstrap()
		->run();
} catch (Exception $e) {
	debug('<h3>'.$e->getMessage().'</h3>');
	debug($e->__toString());
}

/**
 * Output execution time, if it's not ajax or flash request.
 */
$controller = Zend_Controller_Front::getInstance();
$request = $controller->getRequest();
if(! $request->isXmlHttpRequest() && !$request->isFlashRequest()) {
	echo '<hr><p style="font-size:12px;font-family:consolas,monospace;">'
		.number_format((microtime(true) - $start),4),"s</p>";
}
