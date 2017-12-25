<?php
require 'Base.php';

class Notice extends Base
{
    protected $_db = null;
    protected $table_notice;

    public function __construct()
    {
        parent::__construct();
        $this->table_notice = $this->get_table('notice');
    }

    /**
     * 登录之后推送消息
     */
    public function doClient($serv,$data)
    {
        $admin_id = $data['admin_id'];
        $fd = $data['fd'];

        $ukey = "admin_".$admin_id;
        $serv->LoignTable->set($ukey,array('fd_id'=>$fd,'admin_id'=>$admin_id));

        $fkey = "fd_".$fd;
        $serv->fdByAdminTable->set($fkey,array('admin_id'=>$admin_id));

        echo $fd."--".$admin_id."登录";
    }

    /**
     * 推送消息
     */
    public function doSend($data)
    {
        //推送
        $notice_id = $data['notice_id'];
        $this->get_mysql_connect();
        $where = ['AND'=>['id'=>$notice_id]];
        $notice_info = $this->_db->get("*", $where, $this->table_notice);

        if(isset($notice_info) && $notice_info) {
            $to_admin_id = $notice_info['to_admin_id'];
            $to_admin_ids = explode(",",$to_admin_id);
        } else {
            $to_admin_ids = [];
        }
        return $to_admin_ids;
    }

    /**
     * 断开链接
     */
    public function doClose($serv,$data)
    {
        $fd = $data['fd'];

        $fkey = "fd_".$fd;
        $admin_id =0;
        if($serv->fdByAdminTable->exist($fkey)){

            $admin_id_arr = $serv->fdByAdminTable->get($fkey);
            $admin_id = $admin_id_arr['admin_id'];
            $serv->fdByAdminTable->del($fkey);

        }

        if($admin_id>0){

            $ukey = "admin_".$admin_id;
            if($serv->LoignTable->exist($ukey))
            {
                $serv->LoignTable->del($ukey);
            }
        }
        echo $fd."--".$admin_id."退出";
    }

}