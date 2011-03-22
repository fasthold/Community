<?php

/**
 *
 * @version $Id: MongoDB.php 15 2011-03-03 16:01:12Z wc5d@hotmail.com $
 */

/**
 * Zend_Db 的 MongoDB实现
 *
 * @todo 移除继承自 Zend_Db_Adapter_Abstract，改为自身独立的结构
 */
class Base_Db_Adapter_MongoDB extends Zend_Db_Adapter_Abstract {
    /**
     * Creates a connection to the database.
     *
     * @return void
     * @throws Zend_Db_Adapter_Exception
     */
    protected function _connect() {
        if ($this->_connection) {
            return;
        }

        if (!extension_loaded('mongo')) {
            /**
             * @see Zend_Db_Adapter_Exception
             */
            require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception('The MongoDB extension is required for this adapter but the extension
				is not loaded');
        }
		// 组合 dsn
        $defaultConfig = array(
            'host' => '127.0.0.1:27017',
            'username'=>'',
            'password'=>'',
			'driver_options' => null
        );
		$config = $this->_config + $defaultConfig;
		if (!empty($config['dsn'])) {
			$dsn = $config['dsn'];
		} else {
			$dsn = $config['host'];
		}
		if (!empty($config['username'])) {
			$config['driver_options']['username'] = $config['username'];
			$config['driver_options']['password'] = $config['password'];
		}
        $m = new Mongo($dsn,$config['driver_options']);
        $this->_connection = $m->selectDB($config['dbname']);
        // Suppress connection warnings here.
        // Throw an exception instead.
        $_isConnected = (bool) ($this->_connection instanceof MongoDB);

        if ($_isConnected === false) {

            $this->closeConnection();
            /**
             * @see Zend_Db_Adapter_Exception
             */
            require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception($this->_connection->lastError());
        }
    }

    /**
     * Test if a connection is active
     *
     * @return boolean
     */
    public function isConnected()
    {
        return ((bool) ($this->_connection instanceof MongoDB));
    }

    /**
     * Force the connection to close.
     *
     * @return void
     */
    public function closeConnection()
    {
        if ($this->isConnected()) {
            $this->_connection->command(array("logout" => 1));
        }
        $this->_connection = null;
    }

    public function listTables() {
        return $this->_connection->listCollections();
    }

    public function describeTable($collectionName, $schemaName = null) {
        // @todo
    }

    public function prepare($sql) {
        // @todo
    }

    public function lastInsertId($collectionName = null, $primaryKey = null) {
        
    }

    protected function _beginTransaction() {
        parent::beginTransaction();
    }
    protected function _commit() {
        parent::commit();
    }
    protected function _rollBack() {
        parent::rollBack();
    }

    public function setFetchMode($mode) {
        $this->_fetchMode = $mode;
    }

    public function limit($sql, $count, $offset = 0) {
        // @todo
    }

    /**
     * Dosen't support any parameters.
     *
     * @param string $type
     * @return boolean
     */
    public function supportsParameters($type) {
        return false;
    }

    public function getServerVersion() {
        // @todo
    }

    public function fetchAll($collectionName = null, $bind = array(), $fetchMode = null) {
        if(null === $collectionName) {
            require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception("The collection name can't be empty");
        }
        $result = $this->_connection->selectCollection($collectionName)->find();
        $data = array();
        while ($row = $result->getNext()) {
            $data[] = $row;
        }
        return $data;
    }

	/**
	 * 往某个collection插入数据。如果成功，则返回新数据的MongoId对象，否则，返回false
	 *
	 * @param string $collectionName
	 * @param array $bind
	 * @return MongoId
	 */
    public function insert($collectionName, array $bind) {
        $collection = $this->_connection->selectCollection($collectionName);
        $result = $collection->insert($bind,array('safe'));
		if($result) {
			return $bind['_id'];
		} else {
			return false;
		}
    }
    
    public function fetchRow($collectionName = null, $bind = array(), $fetchMode = null) {
        if(null === $collectionName) {
            require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception("The collection name can't be empty");
        }
        $collection = $this->_connection->selectCollection($collectionName);
		$cursor = $collection->findOne();
		if($cursor->hasNext()) {
			return $cursor->getNext();
		}
		return array();
    }

	/**
	 * 删除某条记录
	 *
	 * @param string $collectionName collection名称
	 * @param array $condition 删除条件
	 * @param array $options 选项 @link http://cn2.php.net/manual/en/mongocollection.remove.php
	 * @return int 删除的条数
	 */
	public function delete($collectionName = null, $condition = array() ,$options = null) {
        $collection = $this->_connection->selectCollection($collectionName);
		// 处理 _id
		if(isset ($condition['_id'])) {
			if(! $condition['_id'] instanceof  MongoId) {
				$condition['_id'] = new MongoId($condition['_id']);
			}
		}
		$options = $options + array('safe'=>true);
		$result = $collection->remove($condition, $options);
		return $result['n'];
	}
}
