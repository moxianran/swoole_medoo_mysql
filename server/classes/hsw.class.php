<?php
class hsw {
	private $serv = null;
	public function __construct(){
        $Table = $this->create_table();
		$this->serv = new swoole_websocket_server("0.0.0.0",9502);
		$this->serv->set(array(
			'task_worker_num'     => 8
		));
		$this->serv->on("open",array($this,"onOpen"));
		$this->serv->on("message",array($this,"onMessage"));
		$this->serv->on("Task",array($this,"onTask"));
		$this->serv->on("Finish",array($this,"onFinish"));
		$this->serv->on("close",array($this,"onClose"));

        $this->serv->LoignTable = $Table['LoignTable'];
        $this->serv->fdByAdminTable = $Table['fdByAdminTable'];

        $this->serv->start();
	}

	private function create_table(){

	    //创建fd和admin对应的内存表
        $table = new swoole_table(1024);
        $table->column('fd_id', swoole_table::TYPE_INT);
        $table->column('admin_id', swoole_table::TYPE_INT);
        $table->create();

        $fdByAdminTable = new swoole_table(1024);
        $fdByAdminTable->column('admin_id', swoole_table::TYPE_INT);
        $fdByAdminTable->create();

        return array('LoignTable'=>$table,'fdByAdminTable'=>$fdByAdminTable);
    }
	
	public function onOpen( $serv , $request ){

	}
	
	public function onMessage( $serv , $frame ){
		$data = json_decode( $frame->data , true );
		switch($data['type']){
			case 1://登录
				$data = array(
					'task' => 'client',
                    'admin_id' => $data['admin_id'],
					'fd' => $frame->fd,
				);
				$this->serv->task( json_encode($data) );
				break;
			case 2: //新消息
				$data = array(
					'task' => 'send',
                    'notice_id' => $data['notice_id'],
                    'admin_id' => $data['admin_id'],
                    'fd' => $frame->fd,
				);
				$this->serv->task( json_encode($data) );
				break;
			default :
				$this->serv->push($frame->fd, json_encode(array('code'=>0,'msg'=>'type error')));
		}
	}
	public function onTask( $serv , $task_id , $from_id , $data ){
        $Notice = new Notice();
		$data = json_decode($data,true);
		switch( $data['task'] ){
			case 'client':
                $Notice->doClient( $serv,$data );
				break;
			case 'send':
                $to_admin_ids = $Notice->doSend( $data );
                $this->sendMsg($to_admin_ids);
                break;
			case 'logout':
                $Notice->doClose( $serv,$data );
				break;
		}
	}
	
	public function onClose( $serv , $fd ){
		//获取用户信息
			$data = array(
				'task' => 'logout',
				'fd' => $fd
			);
			$this->serv->task( json_encode($data) );
	}
	
	public function sendMsg($myfd){

	    //需要推送的admin_id
        $to_admin_ids = $myfd;
        if(isset($to_admin_ids) && $to_admin_ids) {
            foreach ($to_admin_ids as $k => $v) {
                $ukey = "admin_" . $v;
                if ($this->serv->LoignTable->exist($ukey)) {

                    $LoignTable = $this->serv->LoignTable->get($ukey);
                    $fd = $LoignTable['fd_id'];

                    $this->serv->push($fd, json_encode(['type'=>1]));
                }
            }
        }
	}
	
	public function onFinish( $serv , $task_id , $data ){
	}
}