<?php

define("APP", __DIR__);
define("LOG", 'log.log');
error_reporting(0);
class HttpServer {

    public static $instance;
    public $http;
    public static $server;
    public $exec_type = ['insert','update','delete'];

    public function __construct() {
        swoole_set_process_name("CrmMysqlServer");
        $http = new Swoole\Http\Server("0.0.0.0", 9504, SWOOLE_PROCESS);
        $http->set(
            array(
                'worker_num' => 4,  //worker进程数
                'open_cpu_affinity' => 4,   //CPU亲和设置
                'daemonize' => FALSE,   //守护进程化
                'max_request' => 100000000,   //进程的最大任务数
                'task_worker_num' => 20,    //Task进程的数量
                'log_file' => APP . '/log', //swoole错误日志文件
                'backlog' => 1024,  //Listen队列长度
                'dbServer' => array(
                    'database_type' => 'mysql',
                    'server' => '192.168.1.199',
                    'user' => 'root',
                    'passwd' => '123456',
                    'dbName' => 'crm4',
                    'port' => 3306
                ),
            )
        );
        $http->on('request', array($this, 'onRequest'));
        $http->on("task", array($this, "onTask"));
        $http->on("finish", array($this, "onFinish"));
        $this->http = $http;
        $http->start();
    }

    public function onRequest(Swoole\Http\Request $request, Swoole\Http\Response $response) {

        //ip
        $ip = $request->server['remote_addr'];
        //数据库语句
        $post = $request->post;
        $sql = $post['sql'];
        //时间
        $date_time = date("Y-m-d H:i:s");

        //投递一个异步的任务
        $d = $this->http->taskwait($post,200);

        //记录数据
        if ($d['ret'] == 1) {
            $filename = APP . "/" . LOG;
            Swoole\Async::write($filename, $ip . " " . $date_time . $sql . " success \n", -1, function() {
                //echo "数据写入成功\n";
            });
        } else {
            $filename = APP . "/" . LOG;
            Swoole\Async::write($filename, $ip . " " . $date_time . $sql . " fail \n", -1, function() {
                //echo "数据写入成功\n";
            });
        }

        $response->status(200);
        $response->write(json_encode($d));
        $response->end();
        return TRUE;

    }

    public function onTask(Swoole\Server $serv, $taskId, $fromId, $post) {

        $sql = $post['sql'];
        $select_type = $post['select_type'];
        $param = $post['param'];

        $_config = $serv->setting['dbServer'];
        $type = strtolower($_config['database_type']);
        $dsn = $type . ':host=' . $_config['server'] . ';port=' . $_config['port'] . ';dbname=' . $_config['dbName'];

        static $link = null;
        if ($link == null) {
            try {
                $link = new PDO($dsn, $_config['user'], $_config['passwd'], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                    PDO::ATTR_CASE => PDO::CASE_NATURAL, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            } catch (PDOException $exc) {
                $link = null;
                return array("data" => array(), 'ret' => 0);
            }
        }
        try {

            if(in_array($select_type,$this->exec_type)) {
                $exec = $link->exec($sql);
            } else {
                $query = $link->query($sql);
            }

        } catch (Exception $e) {
            //重新连接
            if ($e->getCode() == 'HY000') {
                echo "数据库重新连接_" . date("Y-m-d H:i:s") . "\n";
                try {
                    $link = new PDO($dsn, $_config['user'], $_config['passwd'], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                        PDO::ATTR_CASE => PDO::CASE_NATURAL, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
                } catch (PDOException $exc) {
                    $link = null;
                    return array("data" => array(), 'ret' => 0);
                }
                if(in_array($select_type,$this->exec_type)) {
                    $exec = $link->exec($sql);
                } else {
                    $query = $link->query($sql);
                }
            }
        }

        switch($select_type) {
            case 'selects' :
                $columns = $param['columns'];

                $res = $query ? $query->fetchAll(
                    (is_string($columns) && $columns != '*') ? PDO::FETCH_COLUMN : PDO::FETCH_ASSOC
                ) : false;
                break;
            case 'select' :

                $join = $param['join'];
                $columns = $param['columns'];
                $where = $param['where'];

                $column = $where == null ? $join : $columns;
                $is_single_column = (is_string($column) && $column !== '*');

                if (!$query) {
                    $res =  false;
                } else if ($columns === '*') {
                    $res = $query->fetchAll(PDO::FETCH_ASSOC);
                } else if ($is_single_column) {
                    $res = $query->fetchAll(PDO::FETCH_COLUMN);
                } else {

                    $stack = array();
                    $index = 0;

                    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                        foreach ($columns as $key => $value) {
                            if (is_array($value)) {
                                $this->data_map($index, $key, $value, $row, $stack);
                            } else {
                                $this->data_map($index, $key, preg_replace('/^[\w]*\./i', "", $value), $row, $stack);
                            }
                        }

                        $index++;
                    }
                    $res = $stack;
                }

                break;
            case 'get' :

                $join = $param['join'];
                $columns = $param['columns'];
                $where = $param['where'];

                $column = $where == null ? $join : $columns;
                $is_single_column = (is_string($column) && $column !== '*');

                if ($query) {
                    $data = $query->fetchAll(PDO::FETCH_ASSOC);

                    if (isset($data[0])) {
                        if ($is_single_column) {
                            $res = $data[0][preg_replace('/^[\w]*\./i', "", $column)];
                        } else if ($column === '*') {
                            $res = $data[0];
                        } else {

                            $stack = array();

                            foreach ($columns as $key => $value) {
                                if (is_array($value)) {
                                    $this->data_map(0, $key, $value, $data[0], $stack);
                                } else {
                                    $this->data_map(0, $key, preg_replace('/^[\w]*\./i', "", $value), $data[0], $stack);
                                }
                            }

                            $res = $stack[0];
                        }

                    } else {
                        $res = false;
                    }
                } else {
                    $res = false;
                }

                break;
            case 'sum':
                $res =  $query ? 0 + $query->fetchColumn() : false;
                break;
            case 'has' :
                if ($query) {
                    $res =  $query->fetchColumn() === '1';
                } else {
                    $res = false;
                }
                break;
            case 'count':
                $res =  $query ? 0 + $query->fetchColumn() : false;
                break;
            case 'insert' :
                if ($exec > 0) {
                    $lastId[] = $link->lastInsertId();
                } else {
                    $lastId[] = 0;
                }

                $res =  count($lastId) > 1 ? $lastId : $lastId[0];
                break;
            case 'update' :
                $res = $exec;
                break;
            case 'delete' :
                $res = $exec;
                break;
            default :
                break;
        }

        return array("data" => $res, 'ret' => 1);
    }

    function onFinish(Swoole\Server $serv, $taskId, $data) {
        return $data;
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new HttpServer;
        }
        return self::$instance;
    }

    public function data_map($index, $key, $value, $data, &$stack)
    {
        if (is_array($value)) {
            $sub_stack = array();

            foreach ($value as $sub_key => $sub_value) {
                if (is_array($sub_value)) {
                    $current_stack = $stack[$index][$key];

                    $this->data_map(false, $sub_key, $sub_value, $data, $current_stack);

                    $stack[$index][$key][$sub_key] = $current_stack[0][$sub_key];
                } else {
                    $this->data_map(false, preg_replace('/^[\w]*\./i', "", $sub_value), $sub_key, $data, $sub_stack);

                    $stack[$index][$key] = $sub_stack;
                }
            }
        } else {
            if ($index !== false) {
                $stack[$index][$value] = $data[$value];
            } else {
                if (preg_match('/[a-zA-Z0-9_\-\.]*\s*\(([a-zA-Z0-9_\-]*)\)/i', $key, $key_match)) {
                    $key = $key_match[1];
                }

                $stack[$key] = $data[$key];
            }
        }
    }

}

HttpServer::getInstance();

