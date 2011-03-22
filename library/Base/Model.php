<?php
/**
 * @version $Id: Model.php 15 2011-03-03 16:01:12Z wc5d@hotmail.com $
 */

/**
 * Base_Model
 */
class Base_Model {
	/**
	 * @var MongoDb
	 */
	protected $_db;
	/**
	 * Collection Name
	 */
	protected $_instanceName;
    /**
     *
     * @var MongoId 
     */
    protected $_id = null;

	/**
	 *
	 * @var MongoCollection
	 */
	protected $_collection;

	/**
	 *
	 * @var 字段数据
	 */
	protected $_fieldData;
	
	/**
	 * Connect to dabase.
	 *
	 * @return Zend_Db_Adapter_Abstract
	 */
	protected function getDbConnection() {
		if($this->_db instanceof Zend_Db_Adapter) {
			$this->_db->getConnection();
		} else {
			$appOptions = Zend_Registry::get('app.options');
			
			switch (strtolower($appOptions['resources']['db']['adapter'])) {
				default:
					$dbOptions = $appOptions['resources']['db']['mongodb'];
					$db = new Base_Db_Adapter_MongoDB($dbOptions);
					$db->getConnection();
					break;
				case 'mongodb':
					if(Zend_Registry::isRegistered('db')) {
						$db = Zend_Registry::get('db');
					}
					break;
			}
            $this->_db = $db;
		}
		//$this->_db->query('set names "utf8"');
		return $this->_db;
	}

	/**
	 * Short-hand for $this->getDbConnection();
	 *
	 * @return Zend_Db_Adapter_Abstract
	 */
    public function db() {
        return $this->getDbConnection();
    }

	/**
	 * 取当前Model所对应的 MongoCollection 对象
	 *
	 * @return MongoCollection
	 */
    public function collection() {
        return $this->getDbConnection()->getConnection()->selectCollection($this->_collectionName);
    }

	/**
	 * 指定collection的MongoId, 当传递string时，将自动转为MongoId
	 *
	 * @param MongoId $id string
	 * @return Base_Model
	 */
	public function setId($id) {
		if($id instanceof MongoId) {
			$this->_id = $id;
		} else {
			$this->_id = new MongoId($id);
		}
		return $this;
	}

	/**
	 * 取得MongoId
	 *
	 * @return MongoId
	 */
	public function getId() {
		return $this->_id;
	}

	protected function _getNextId() {
		
	}

	public function command($command) {
		return $this->db()->getConnection()->command($command);
	}

	/**
	 *
	 * @param string $name
	 * @return mixed
	 * @method __get
	 */
	public function __get($name) {
		if(isset($this->_fieldData[$name])) {
			return $this->_fieldData[$name];
		}
		return null;
	}

	/**
	 *
	 * @param string $name
	 * @return Base_Model
	 * @method __set
	 */
	public function __set($name,$value) {
		$this->_fieldData[$name] = $value;
		return $this;
	}

	/**
	 *
	 * @param array $condition
	 * @param int $sort
	 * @param int $limit
	 * @param int $skip
	 * @return array
	 */
	public function find(array $condition,$sort = null,$limit = null,$skip = null) {
		$rs = $this->collection()->find($condition);
		if(null !== $sort)
			$rs->sort($sort);

		if(null !== $limit)
			$rs->limit($limit);

		if(null !== $skip)
			$rs->skip($skip);

		if($rs) {
			$data = array();
			while ($rs->hasNext()) {
				$data[] = $rs->getNext();
			}
			return $data;
		} else {
			return array();
		}
	}
}
