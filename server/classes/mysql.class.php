<?php
require 'Base2.php';

class Mysql extends Base2
{
    protected $_db = null;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 处理sql
     * @param $sql
     * @param $post
     * @return mixed
     */
    public function processSql($post)
    {

        $post = $this->object_array($post);

        //var_dump($post);
        $sql = 1;
        $this->get_mysql_connect();
//        $type = $post['type'];
//
//        if($type == 'insert') {
//            $result = $this->_db->newInsert($sql);
//        } else if($type == 'delete') {
//            $result = $this->_db->newDelete($sql);
//        } else if($type == 'update') {
//            $result = $this->_db->newUpdate($sql);
//        } else if($type == 'count') {
//            $result = $this->_db->newCount($sql);
//        } else if($type == 'has') {
//            $result = $this->_db->newHas($sql);
//        } else if($type == 'sum') {
//            $result = $this->_db->newSum($sql);
//        } else if($type == 'get'){
//            $result = $this->_db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
//            $result = $result['0'];
//            $result = json_encode($result);
//        } else if($type == 'select'){

//        $columns = isset($post['columns']) ? $post['columns'] : '';
//        $where = isset($post['where']) ? $post['where'] : '';
//        $table = isset($post['table']) ? $post['table'] : '';
//
//        var_dump($columns);
//        var_dump($where);
//        var_dump($table);
//        die;


        $columns = isset($post['columns']) && $post['columns'] ? $post['columns'] : '*';
        $where = isset($post['where']) && $post['where'] ? $post['where'] : [];
        $table = $post['table'];

        $result = $this->_db->newSelect($columns, $where, $table);

            var_dump($result);
            $result = json_encode($result);
        //}

        return $result;
    }

    function object_array($array) {
        if(is_object($array)) {
            $array = (array)$array;
        } if(is_array($array)) {
            foreach($array as $key=>$value) {
                $array[$key] = object_array($value);
            }
        }
        return $array;
    }


}