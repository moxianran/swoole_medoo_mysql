<?php
/**
 * Created by IntelliJ IDEA.
 * User: yingrr
 * Date: 17-2-10
 * Time: 上午9:20
 */

include 'Medoo.php';

abstract class Base
{

    protected $_db_config = array();
    protected $_redis_config = array();
    protected $_db = null;

    public function __construct()
    {
        $this->set_config();
    }

    protected function only_get_config($name,$basename) {
        $_config = require 'config.php';
        return isset($_config[$name][$basename]) ? $_config[$name][$basename] : array();
    }

    private function get_config($name)
    {
        $_config = require 'config.php';
        return isset($_config[$name]) ? $_config[$name] : array();
    }

    private function set_config()
    {
        $this->_db_config = $this->get_config('mysql');
        $this->_redis_config = $this->get_config('redis');
    }

    protected function get_mysql_connect($database = 'master')
    {
        $this->_db = new Medoo($this->_db_config,$database);
    }

    protected function get_redis_connect($p = 'house')
    {
        $this->_redis = new Redis();

        $_redis_config = $this->_redis_config[$p];
        $this->_redis->connect($_redis_config['server'], $_redis_config['port']);
        if (isset($_redis_config['pwd']) && $_redis_config['pwd']) {
            $this->_redis->auth($_redis_config['pwd']);
        }
        $select_db =0;
        if(isset($_redis_config['db']) && is_int($_redis_config['db']) )
        {
            $select_db=$_redis_config['db'];
        }
        $this->_redis->select($select_db);

        return $this->_redis;
    }

    protected function get_table($table = null ,$database = 'master')
    {
        return $this->_db_config[$database]['prefix'] . $table;
    }
}
