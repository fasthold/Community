<?php

/**
 * @version $Id: Action.php 15 2011-03-03 16:01:12Z wc5d@hotmail.com $
 */

/**
 * Base
 *
 */
class Base_Controller_Action extends Zend_Controller_Action {
	
	/**
	 * 包含若干 Zend_Session 的数组
	 *
	 * @var array
	 */
	protected $_session;
	
	protected $_options = null;

	/**
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger;
	
	protected function noRender() {
		$this->_helper->viewRenderer->setNoRender();
	}

	protected function noLayout() {
		$this->_helper->layout->disableLayout();
	}
	/**
	 * 关闭调试信息
	 */
	protected function noDebug() {
		Zend_Registry::set('DEBUG',-1);
	}
	/**
	 * 开启调试信息
	 */
	protected function enableDebug($level=1) {
		Zend_Registry::set('DEBUG',$level);
	}
	
	
	/**
	 * 从配置文件中取出所有配置选项
	 * 
	 * @return Array
	 */
	public function getAppOptions() {
		$args = $this->getInvokeArgs();
		$this->_options = $args['bootstrap']->getOptions();
		return $this->_options;
	}
	
	/**
	 * 取单个配置选项
	 *
	 * @param string $key
	 * @return Mix
	 */
	public function getAppOption($key) {
		if(null === $this->_options) {
			$this->getAppOptions();
		}
		return (isset($this->_options[$key])) ? $this->_options[$key] : null;
	}

	/**
	 * 初始化session
     * @todo 整理application.ini中相关的参数，使之与php默认的session参数一致。这样可以简化代码，配置起来也更容易
	 *
	 * @param string $namespace session namespace
	 * @param string $storage session storage engine name
	 * @param Zend_Config|array $config The engine's specified configuations.
	 */
	public function initSession($namespace='auth',$storage=null,$config=null) {
		$namespace = 'user';
		$namespace = trim($namespace);
		if(trim($namespace) === '') {
			$namespace = 'auth';
		}
		if(isset($this->_session[$namespace]) && $this->_session[$namespace] instanceof Zend_Session) {
			return $this->_session[$namespace];
		}

		if(null === $config) {
			$sessionConfig = $this->getAppOption('session');
		} else {
			$sessionConfig = $config;
		}

		if(null === $storage) { // 如果没有单独指定storage，则从config中取storage项。
			$storage = $sessionConfig['storage'];
		}
		$storage = strtolower($storage);
		
		// 根据 storage 的不同，设置不同的session存储方式及其参数选项
		switch ($storage) {
			default: // 默认采用文件存储方式(PHP原生)
			case 'file':
				Zend_Session::setOptions(array(
					'save_path' => $sessionConfig['file']['save_path'],
				));
				break;
			
			/**
			 * session 存储在数据库中
			 * @link http://framework.zend.com/manual/en/zend.session.savehandler.dbtable.html 查看详细的数据表结构
			 */
			case 'db':
				if(! Zend_Registry::isRegistered(REGKEY_DATABASE)) {
					throw new Zend_Session_SaveHandler_Exception("Please connect to database first.");
				}
				$db = Zend_Registry::get(REGKEY_DATABASE);
				$db->getConnection();
				Zend_Session::setSaveHandler(new Zend_Session_SaveHandler_DbTable($sessionConfig['database']));
				break;
				
			case 'memcached': // 存储memcached中
				Zend_Session::setSaveHandler(new Base_Session_SaveHandler_Memcached($sessionConfig));
				break;
			
			/**
			 * 存储在MongoDB中
			 */
			case 'mongodb':
				Zend_Session::setSaveHandler(new Base_Session_SaveHandler_MongoDb($sessionConfig));
				break;
		}
		Zend_Session::setOptions(array(
			'strict' => $sessionConfig['strict'],
			'remember_me_seconds' => $sessionConfig['remember_me_seconds'],
			'name' => $sessionConfig['name'],
			'cookie_httponly' => $sessionConfig['cookie_httponly'],
		));
        if(!empty($sessionConfig['cookie_domain'])) {
            Zend_Session::setOptions(array('cookie_domain'=>$sessionConfig['cookie_domain']));
        }
//        d(Zend_Session::getOptions());
		Zend_Session::start();
		$this->_session[$namespace] = new Zend_Session_Namespace($namespace);;
		return $this->_session[$namespace];
	}

	/**
	 * 取得session对象。先判断是否已经初始化，有，则直接取之。不会重复初始化对象
	 * 
	 * @param string $namespace
	 * @return Zend_Session_Namespace
	 */
	public function getSession($namespace) {
		if (isset($this->_session[$namespace]) && $this->_session[$namespace] instanceof Zend_Session_Namespace) {
			return $this->_session[$namespace];
		} else {
			/**
			 * @see Zend_Session_Exception
			 */
			require_once 'Zend/Session/Exception.php';
			throw new Zend_Session_Exception("The session hasn't initialized yet.");
			return false;
		}
	}
	/**
	 * 跳转页面
	 *
	 * @param string $message 提示信息
	 * @param string $url 要跳转到的地址
	 * @param string $tpl 跳转页模板。（可用 flash, error, warning 等等 ）
	 * @param int|string $stopTime 页面停留时间。若指定为 stop，则将一直停留，不自动跳转。
	 */
	public function flash($message,$url,$tpl=null,$stopTime=2) {
		$this->noRender();
		$this->noLayout();
		if(null === $tpl)
			$tpl = 'flash';
		$view = new Zend_View();
		$front = Zend_Controller_Front::getInstance();
		$defaultViewScriptsDirectory = $front->getModuleDirectory($front->getDefaultModule()) . '/views/scripts';
		$view->setScriptPath($defaultViewScriptsDirectory);
		$view->assign(array('flashMessage'=>$message,'url'=>$url,'stopTime'=>$stopTime));
		echo $view->render('flash/'.$tpl.'.phtml');
		return ;
	}
	
	protected function _getReferer($defaultUrl='/') {
		return empty($_SERVER['HTTP_REFERER'])?$this->getRequest()->getBaseUrl().$defaultUrl:$_SERVER['HTTP_REFERER'];
	}
	
	public function tip($message,$url,$option=array()) {
		$this->flash($message,$url);
	}
	
	/**
	 * @param string $defaultRedirectTo 当来路是登录/退出/注册页时，默认的跳转页
	 * @param string $paramName 指定登录后跳转返回至哪个页面的参数名称。其值需使用urlencode的格式。
	 * 	有如下常量(都以 __ 开头并且结尾，且全大写)可供使用：
	 * 		__REFERER__		HTTP Referer
	 *		__SITEHOME__	网站首页
	 * 		__CENTER__		个人中心首页
	 * 	其余各值均视为url
	 */	
	protected function _getReturnUrl($defaultRedirectTo = '/',$paramName='return') {
		$returnUrl = $this->_getParam($paramName);
		if(empty($returnUrl)) {
			// 如果外部参数中没有传值，则取referer
			$returnUrl = $this->_getReferer();
		}
		//如果来路是登录/退出页，则默认转到到中心首页
//		if($returnUrl==$Url->login || $returnUrl==$Url->logout) {
//			$returnUrl = $defaultRedirectTo;
//		}
//		return $returnUrl;
//		$Url = Config_Url::get();
//		switch ($returnUrl) {
//			case '':
//			case '__REFERER__':
//				$returnUrl = $this->getReferer();
//				break;
//			case '__SITEHOME__':
//				$returnUrl = $Url->home;
//				break;
//			case '__CENTER__':
//				$returnUrl = $Url->center;
//				break;
//			default: // 默认情况下，按传递进的return原值处理(即一律视为url)
//				$returnUrl = urldecode($returnUrl);
//				break;
//		}
		return $returnUrl;
	}
	
	/**
	 * 取得分页对象（分页后的）
	 * 如果$data为数组，则表示采用数组偏移分页法；如果是整型，则表示传递进的一个count总值；
	 * 如果$data为Zend_Db_Select对象，则表示采用数据库offet分页法
	 *
	 * @param array|int|Zend_Db_Select $data
	 * @param int $itemCountPerPage 每页记录数
	 * @param string $paramName 分页参数名
	 * @return Zend_Paginator
	 */
	public function getPaginator($data,$itemCountPerPage=10,$paramName='page') {
		$paginator = Zend_Paginator::factory($data);
		$paginator->setCurrentPageNumber($this->_getParam($paramName))->setItemCountPerPage($itemCountPerPage);
		return $paginator;
	}
	
	
	protected function intval($param,$allowZero=false,$renderError=true) {
		$value = $this->_getParam($param);
		$value = abs(intval($value));
		if(!$renderError) {
			return $value;
		}
		if(empty($value)) {
			$this->view->assign('message','Required parameter is missing: '.htmlspecialchars($param));
			echo $this->view->render('error/error.phtml');
			return false;
		}
	}
	
	/**
	 * 取得指定输出格式的代码字符串
	 * @todo xml格式的返回
	 * @example 外部参数使用： http://example.com/?format=json&callback=getSomething
	 *
	 * @param string|array $data 数据源
	 * @param string $format 返回格式。可选值： json, xml(未完)
	 * @package boolean $autoSendHeader 自动发送header (根据format)
	 * @return string
	 */
	protected function getOutput($data,$format = null,$autoSendHeader = true) {
		if(null === $format) {
			$format = strtolower($this->_getParam('format','json'));
		}
		switch ($format) {
			default:
			case 'json':
				$assign = trim($this->_getParam('assign'));
				$callback = trim($this->_getParam('callback'));
				$output = Zend_Json::encode($data);
				if($callback=='?') {
					$output = '('.$output.');';
				} elseif($callback!='') {
					$output = $callback . '('.$output.');';
				}
				break;
			case 'xml':
				throw new Exception('Unimplemented yet.');
				break;
		}
		return $output;
	}
	
	/**
	 * 将所有外部参数赋值进view
	 *
	 * @param $assignVarName 要赋到view中的变量名称。默认为 params
	 * @return void
	 */
	protected function assignExternalParams($assignVarName=null) {
		$params = $this->_getAllParams();
		unset($params['action'],$params['controller'],$params['module']);
		if(is_null($assignVarName)) {
			$assignVarName = 'params';
		}
		$this->view->assign($assignVarName,$params);
	}
}