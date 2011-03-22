<?php

/**
 *
 * @category   Base
 * @package    Base_Session
 * @version    $Id: MongoDb.php 1 2010-12-07 13:01:02Z wc5d@hotmail.com $
 */

/**
 * Base_Session_SaveHandler_MongoDb
 *
 * Session handler. Store session data in MongoDB
 *
 * @category   Base
 * @package    Base_Session
 * @subpackage SaveHandler
 *
 * @todo session的lifetime处理好像有问题，使得gc没能生效，需复查。
 * @todo 将 data 字段改为对象型的。现在是 serialize后的字符串。改成对象型之后查询数据将变得很方便。
 */
class Base_Session_SaveHandler_MongoDb implements Zend_Session_SaveHandler_Interface
{
    const PRIMARY_ASSIGNMENT                   = 'primaryAssignment';
    const PRIMARY_ASSIGNMENT_SESSION_SAVE_PATH = 'session_save_path';
    const PRIMARY_ASSIGNMENT_SESSION_NAME      = 'session_name';
    const PRIMARY_ASSIGNMENT_SESSION_ID        = 'session_id';

    const MODIFIED_COLUMN   = 'modifiedColumn';
    const LIFETIME_COLUMN   = 'lifetimeColumn';
    const DATA_COLUMN       = 'dataColumn';

    const LIFETIME          = 'lifetime';
    const OVERRIDE_LIFETIME = 'overrideLifetime';

	const MONGODB         = 'mongodb';
	/**
	 * MongoDB 连接对象
	 *
	 * @var Mongo
	 */
	protected $_mongo;
	/**
	 * MongoDB Collection 对象
	 *
	 * @var MongoCollection
	 */
	protected $_collection;
    /**
     * Session table primary key value assignment
     *
     * @var array
     */
    protected $_primaryAssignment = null;

    /**
     * Session table last modification time column
     *
     * @var string
     */
    protected $_modifiedColumn = null;

    /**
     * Session table lifetime column
     *
     * @var string
     */
    protected $_lifetimeColumn = null;

    /**
     * Session table data column
     *
     * @var string
     */
    protected $_dataColumn = null;

    /**
     * Session lifetime
     *
     * @var int
     */
    protected $_lifetime = false;

    /**
     * Whether or not the lifetime of an existing session should be overridden
     *
     * @var boolean
     */
    protected $_overrideLifetime = false;

    /**
     * Session save path
     *
     * @var string
     */
    protected $_sessionSavePath;

    /**
     * Session name
     *
     * @var string
     */
    protected $_sessionName;

    /**
     * Constructor
     *
     * $config is an instance of Zend_Config or an array of key/value pairs containing configuration options for
     * Zend_Session_SaveHandler_DbTable and Zend_Db_Table_Abstract. These are the configuration options for
     * Zend_Session_SaveHandler_DbTable:
     *
     * primaryAssignment => (string|array) Session table primary key value assignment
     *      (optional; default: 1 => sessionId) You have to assign a value to each primary key of your session table.
     *      The value of this configuration option is either a string if you have only one primary key or an array if
     *      you have multiple primary keys. The array consists of numeric keys starting at 1 and string values. There
     *      are some values which will be replaced by session information:
     *
     *      sessionId       => The id of the current session
     *      sessionName     => The name of the current session
     *      sessionSavePath => The save path of the current session
     *
     *      NOTE: One of your assignments MUST contain 'sessionId' as value!
     *
     * modifiedColumn    => (string) Session table last modification time column
     *
     * lifetimeColumn    => (string) Session table lifetime column
     *
     * dataColumn        => (string) Session table data column
     *
     * lifetime          => (integer) Session lifetime (optional; default: ini_get('session.gc_maxlifetime'))
     *
     * overrideLifetime  => (boolean) Whether or not the lifetime of an existing session should be overridden
     *      (optional; default: false)
     *
     * @param  Zend_Config|array $config      User-provided configuration
     * @return void
     * @throws Zend_Session_SaveHandler_Exception
     */
    public function __construct($config)
    {
        if ($config instanceof Zend_Config) {
            $config = $config->toArray();
        } else if (!is_array($config)) {
            /**
             * @see Zend_Session_SaveHandler_Exception
             */
            require_once 'Zend/Session/SaveHandler/Exception.php';

            throw new Zend_Session_SaveHandler_Exception(
                '$config must be an instance of Zend_Config or array of key/value pairs containing '
              . 'configuration options for Zend_Session_SaveHandler_DbTable and Zend_Db_Table_Abstract.');
        }
        foreach ($config as $key => $value) {
            do {
                switch ($key) {
					case self::MONGODB:
						$this->_connect($value);
						break;
                    case self::MODIFIED_COLUMN:
                        $this->_modifiedColumn = (string) $value;
                        break;
                    case self::LIFETIME_COLUMN:
                        $this->_lifetimeColumn = (string) $value;
                        break;
                    case self::DATA_COLUMN:
                        $this->_dataColumn = (string) $value;
                        break;
                    case self::LIFETIME:
                        $this->setLifetime($value);
                        break;
                    case self::OVERRIDE_LIFETIME:
                        $this->setOverrideLifetime($value);
                        break;
                    default:
                        // unrecognized options passed to parent::__construct()
                        break 2;
                }
                unset($config[$key]);
            } while (false);
        }

    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        Zend_Session::writeClose();
    }

	/**
	 * 创建 MongoDB 连接对象
	 *
	 * @return void
	 */
    protected function _connect($config) {
		if (isset($config['connection']))
			$_connection = $config['connectin'];
		else {
			$_connection = new Mongo($config['connectionString'], array("connect" => false));
			$_connection->connect();
		}
		$_collection = $_connection->selectDb($config['db'])->selectCollection($config['collection']);
		$this->_mongo = $_connection;
		$this->_collection = $_collection;
	}

    /**
     * Set session lifetime and optional whether or not the lifetime of an existing session should be overridden
     *
     * $lifetime === false resets lifetime to session.gc_maxlifetime
     *
     * @param int $lifetime
     * @param boolean $overrideLifetime (optional)
     * @return Zend_Session_SaveHandler_DbTable
     */
    public function setLifetime($lifetime, $overrideLifetime = null)
    {
        if ($lifetime < 0) {
            /**
             * @see Zend_Session_SaveHandler_Exception
             */
            require_once 'Zend/Session/SaveHandler/Exception.php';
            throw new Zend_Session_SaveHandler_Exception();
        } else if (empty($lifetime)) {
            $this->_lifetime = (int) ini_get('session.gc_maxlifetime');
        } else {
            $this->_lifetime = (int) $lifetime;
        }

        if ($overrideLifetime != null) {
            $this->setOverrideLifetime($overrideLifetime);
        }

        return $this;
    }

    /**
     * Retrieve session lifetime
     *
     * @return int
     */
    public function getLifetime()
    {
        return $this->_lifetime;
    }

    /**
     * Set whether or not the lifetime of an existing session should be overridden
     *
     * @param boolean $overrideLifetime
     * @return Zend_Session_SaveHandler_DbTable
     */
    public function setOverrideLifetime($overrideLifetime)
    {
        $this->_overrideLifetime = (boolean) $overrideLifetime;

        return $this;
    }

    /**
     * Retrieve whether or not the lifetime of an existing session should be overridden
     *
     * @return boolean
     */
    public function getOverrideLifetime()
    {
        return $this->_overrideLifetime;
    }

    /**
     * Open Session
     *
     * @param string $save_path
     * @param string $name
     * @return boolean
     */
    public function open($save_path, $name)
    {
        $this->_sessionSavePath = $save_path;
        $this->_sessionName     = $name;

        return true;
    }

    /**
     * Close session
     *
     * @return boolean
     */
    public function close()
    {
        return true;
    }

    /**
     * Read session data
     *
     * @param string $id
     * @return string
     */
    public function read($id)
    {
        $return = '';

		$row = $this->_collection->findOne(array(self::PRIMARY_ASSIGNMENT_SESSION_ID => $id)); //获取数据
		if($this->_getExpirationTime($row) > time()) {
			$return = $row[$this->_dataColumn];
		} else {
			$this->destroy($id);
		}
        return $return;
    }

    /**
     * Write session data
	 *
	 * @todo 将 data 以 object 的形式保存，便于查询
     *
     * @param string $id
     * @param string $data
     * @return boolean
     */
    public function write($id, $sessionData) {
        $return = false;
        $data = array($this->_modifiedColumn => time(),
//                      $this->_dataColumn     => (string) $sessionData);
                      $this->_dataColumn     => $sessionData);

		$row = $this->_collection->findOne(array(self::PRIMARY_ASSIGNMENT_SESSION_ID => $id));
		if($row) {
			$data[$this->_lifetimeColumn] = $this->_getLifetime($row);
			$data = $data + $row;
//			echo(json_encode($row['data']));
//			echo($data);
//			exit();
		} else {
			$data[$this->_lifetimeColumn] = $this->_lifetime;
		}
		$data[self::PRIMARY_ASSIGNMENT_SESSION_ID] = $id;
		$result = $this->_collection->update(
				array(self::PRIMARY_ASSIGNMENT_SESSION_ID => $id), $data, array('upsert' => true));
		if ($result) {
			$return = true;
		}

        return $return;
    }

    /**
     * Destroy session
     *
     * @param string $id
     * @return boolean
     */
	public function destroy($id) {
		$return = false;

		if ($this->_collection->remove(array(self::PRIMARY_ASSIGNMENT_SESSION_ID =>$id))) {
			$return = true;
		}

		return $return;
	}

    /**
     * Garbage Collection
     *
     * @param int $maxlifetime
     * @return true
     */

	public function gc($maxlifetime) {
		$criteria = array(self::LIFETIME => array('$lt' => $maxlifetime));
		$this->_collection->remove($criteria);
		return true;
	}

    /**
     * Retrieve session lifetime considering Zend_Session_SaveHandler_DbTable::OVERRIDE_LIFETIME
     *
     * @param Zend_Db_Table_Row_Abstract $row
     * @return int
     */
    protected function _getLifetime($row)
    {
        $return = $this->_lifetime;
        if (!$this->_overrideLifetime) {
            $return = (int) $row[$this->_lifetimeColumn];
        }

        return $return;
    }

    /**
     * Retrieve session expiration time
     *
     * @param Zend_Db_Table_Row_Abstract $row
     * @return int
     */
    protected function _getExpirationTime($row)
    {
        return (int) $row[$this->_modifiedColumn] + $this->_getLifetime($row);
    }
}
