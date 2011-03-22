<?php
/**
 * Bootstrap class.
 * 
 * @version    $Id: Bootstrap.php 15 2011-03-03 16:01:12Z wc5d@hotmail.com $
 *
 */
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap {

//	protected $_appNamespace = 'Application';
	/**
	 * Application options
	 *
	 * @var Array
	 */
//	protected $_resources;
	

	/**
	 * This init debug functions must be placed before other methods.
	 *
	 */
	protected function _initDebug() {
		require_once 'debug.php';
	}
	protected function _initOptions() {
		$this->_options = $this->getOptions();
		$this->_appNamespace = $this->_options['appnamespace'];
		Zend_Registry::set('app.options', $this->_options);
	}
		/**
	 * Initialize view
	 * 
	 * @return Zend_View
	 */
	protected function _initView() {
//		$view = new Zend_View();
//		$view->setEncoding($this->_options['resources']['view']['encoding']);
////		$view->doctype('XHTML1_TRANSITIONAL');
//		$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
//		$viewRenderer->setView($view);
//		return $view;
	}
	

	protected function _initAutoloader() {
		$autoloader = Zend_Loader_Autoloader::getInstance();
		// Register application base class.
		$autoloader->registerNamespace('Base_');
		$autoloader->setFallbackAutoloader(true);
		return $autoloader;
	}
	
	protected function _initModuleAutoloader() {
		/**
		 * 加载默认模块
		 */
		$defaultModuleLoader = new Zend_Application_Module_Autoloader(array(
				'namespace' => '',
				'basePath' => APPLICATION_PATH ,
			));
			
		/**
		 * 加载resource
		 */
		$resourceLoader = new Zend_Loader_Autoloader_Resource(array(
				'basePath'  => APPLICATION_PATH,
				'namespace' => '',
			));
		
		$resourceLoader->addResourceType('acl', 'acls/', 'Acl')
			->addResourceType('form', 'forms/', 'Form')
			->addResourceType('model', 'models/','Model')
			->addResourceType('plugin', 'plugins','Plugin');
		return $resourceLoader;
	}
	
	protected function _initSession() {
		
	}
	
  	/**
	 * init database
	 *
	 * @return Zend_Db_Adapter_Pdo_Mysql
	 */
//	protected function _initDB() {
//		/* 连接 mysql
//        $resources = $this->getPluginResource('db');
//		$db = $resources->getDbAdapter();
//        */
//
//        // 连接 MongoDB
//        $connection = new Mongo("mongodb://localhost:27017");
//        /* select some DB (and create if it doesn't exits yet) */
//        $db = $connection->selectDB("firv");
//
//		Zend_Registry::set(REGKEY_DATABASE,$db);
//		return $db;
//	}
	
	protected function _initDB() {
        $resources = $this->getPluginResource('db');
		$db = $resources->getDbAdapter();
		Zend_Registry::set(REGKEY_DATABASE,$db);
		return $db;
	}

//	protected function _initRouter() {
//		$controller = Zend_Controller_Front::getInstance();
//
////		$route = new Zend_Controller_Router_Route(
////			'@archive',
////			array(
////			'controller' => 'archive',
////			'action'     => 'index'
////			)
////		);
////		$itemRoute = new Zend_Controller_Router_Route(
////			'e/:category/:code',
////			array(
////				'code' => null, // 给一个默认值。当没有传入code时，默认进入该category的index页
////				'controller' => 'item',
////				'action' => 'view',
////			)
////		);
//		// 最终的url
//		// http://domain.com/e/http-503.html
//		// view 中使用 $this->url(array('code'=>123456,'category'=>'linux'),'item'); 来生成url地址
//		$itemRoute = new Zend_Controller_Router_Route_Regex(
//			'e/(.+)-(.+)\.[s]{0,}html',
//			array(
//				'controller' => 'item',
//				'action'     => 'view'
//			),
//			array(
//				1 => 'category',
//				2 => 'code'
//			),
//			'e/%s-%s.shtml'
//		);
//		$controller->getRouter()->addRoute('item',$itemRoute)
//			->addRoute('defaultEPage',new Zend_Controller_Router_Route_Static(
//				'e',
//			array('controller' => 'item', 'action' => 'index')
//		));
//	}

	protected function _initRoute() {
		$threadRoute = new Zend_Controller_Router_Route_Regex(
			'thread/[0-9a-z]{9}/.*',
			array(
				'controller' => 'thread',
				'action'     => 'view',
			),
			array(
				1 => 'uuid',
				2 => 'title'
			)
		);
		
		// View thread route
		$threadRoute = new Zend_Controller_Router_Route('thread/:uuid/:title',
                                     array('controller' => 'thread',
                                           'action' => 'view',
										   'title' => null));
		// View tags route				   
		$tagRoute = new Zend_Controller_Router_Route('tags/:tagName/:page',
                                     array('controller' => 'thread',
                                           'action' => 'view',
										   'page' => null));
		$controller = Zend_Controller_Front::getInstance();
		$controller->getRouter()
			->addRoute('view_thread',$threadRoute)
			->addRoute('view_tag', $tagRoute);
	}



	protected function _initZFDebug() {
		if(!@$_GET['ZFDEBUG']) {
			return;
		}

		$autoloader = Zend_Loader_Autoloader::getInstance();
		$autoloader->registerNamespace('ZFDebug');

		$options = array(
			'plugins' => array('Variables',
			//'File' => array('base_path' => "E:\sammylau\svn_online\challenge\trunk"),
			'Memory',
			'Time',
			'Registry',
			'Exception')
		);

		# Instantiate the database adapter and setup the plugin.
		# Alternatively just add the plugin like above and rely on the autodiscovery feature.
		if ($this->hasPluginResource('db')) {
			$this->bootstrap('db');
			$db = $this->getPluginResource('db')->getDbAdapter();
			$options['plugins']['Database']['adapter'] = $db;
		}

		# Setup the cache plugin
		if ($this->hasPluginResource('cache')) {
			$this->bootstrap('cache');
			$cache = $this->getPluginResource('cache')->getDbAdapter();
			$options['plugins']['Cache']['backend'] = $cache->getBackend();
		}

		$debug = new ZFDebug_Controller_Plugin_Debug($options);

		$this->bootstrap('frontController');
		$frontController = $this->getResource('frontController');
		$frontController->registerPlugin($debug);
	}
	
}

