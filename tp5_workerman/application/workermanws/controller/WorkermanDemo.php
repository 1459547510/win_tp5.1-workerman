<?php
namespace app\workermanws\controller;

use think\worker\Server;
use think\Db;
use \GatewayWorker\Lib\Gateway;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Channel;

class WorkermanDemo extends Server
{
	protected $socket = 'websocket://0.0.0.0:2346';
	protected $port = '2346';
	protected $protocol = 'websocket';
	protected $host = '0.0.0.0';

	protected $workerId;

	public function onWorkerStart($worker)
	{
		// $this->workerId = $worker->id;
		// foreach($worker->connections as $connection)
        // {
        //     $connection->send(time());
        // }

		// Channel客户端连接到Channel服务端
		Channel\Client::connect('127.0.0.1', 5506);
		// 以自己的进程id为事件名称
		$event_name = $worker->id;
		echo 'woker id:' . $event_name . "\n";
		// 订阅worker->id事件并注册事件处理函数
		Channel\Client::on($event_name, function($event_data)use($worker){
			print_r($event_data);
			echo "\n";
			$to_connection_id = $event_data['to_connection_id'];
			$message = $event_data['content'];
			if(!isset($worker->connections[$to_connection_id]))
			{
				echo "connection not exists\n";
				return;
			}
			$to_connection = $worker->connections[$to_connection_id];
			echo $message . "\n";
			$to_connection->send($message);
		});
	}


	public function onConnect($connection){
		global $worker;
		$msg = "workerID:{$connection->worker->id} connectionID:{$connection->id} connected\n";
		echo $msg;
		$connection->send($msg);
	}

	public function onMessage($connection,$data)
	{
		global $worker;

		// ?to_wid=0&to_cid=2&msg=hello
		$data = json_decode($data,true);
		$event_name = $data['to_wid'];
		$to_connection_id = $data['to_cid'];
		$content = $data['msg'];

		Channel\Client::publish($event_name, array(
			'to_connection_id' => $to_connection_id,
			'content'          => $content
		 ));
		$connection->send($content);

		// // 判断当前客户端是否已经验证,即是否设置了uid
		// if(!isset($connection->uid))
		// {
		//    // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
		//    $connection->uid = $data;
		//    /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
		// 	* 实现针对特定uid推送数据
		// 	*/
		//    $worker->uidConnections[$connection->uid] = $connection;
		//    return $connection->send('login success, your uid is ' . $connection->uid);
		// }
		// // 其它逻辑，针对某个uid发送 或者 全局广播
		// // 假设消息格式为 uid:message 时是对 uid 发送 message
		// // uid 为 all 时是全局广播
		// list($recv_uid, $message) = explode(':', $data);
		// dump($recv_uid);
		// // 全局广播
		// if($recv_uid == 'all')
		// {
		// 	$this->broadcast($message);
		// }
		// // 给特定uid发送
		// else
		// {
		// 	$this->sendMessageByUid($recv_uid, $message);
		// }




		// $data = json_decode($data,true);
		// // 要想知道对方是谁，需要客户端发送鉴权数据,在onMessage回调里做鉴权。
		// if(isset($data['is_first'])){
		// 	// 是否为改聊天组里的用户
		// 	$uids = Db::name('group')->where('id',$data['gid'])->value('uids');
		// 	if(empty($uids)){
		// 		$connection->send($this->back('err',401));
		// 		return;
		// 	}
		// 	if(!in_array($data['uid'],explode(',',$uids))){
		// 		$connection->send($this->back('err',401));
		// 		return;
		// 	}
		//  	$statusInfo = Db::name('user_group_status')->where(['uid'=>$data['uid'],'gid'=>$data['gid']])->find();
		// 	if(empty($statusInfo)){
		// 		Db::name('user_group_status')->insert(['uid'=>$data['uid'],'gid'=>$data['gid'],'status'=>2,'iden'=>$this->$connection->worker->id . '_' . $connection->id]);
		// 	}else{
		// 		Db::name('user_group_status')->where(['uid'=>$data['uid'],'gid'=>$data['gid']])->update(['status'=>2,'iden'=>$connection->worker->id . '_' . $connection->id]);
		// 	}
		// 	$connection->send($this->back('已上线',200));
		// 	return;
		// }
		// // 标识列表
		// // $idenList = Db::name('user_group_status')->where(['gid'=>$data['gid'],'status'=>2])->column('iden');
		// // foreach($idenList as $v){

		// // }
		// // dump($connection);
		// // dump($connection->worker);
		// foreach($connection->worker->connections as $con)
		// {
		// 	$con->send(time());
		// }
	}
	protected function FunctionName()
	{
		# code...
	}
	 
	public static function onClose($connection)
	{
		
	}

	static function back($msg='',$code=401,array $data=[]){
        $res=[
            'msg'=>$msg,
            'code'=>$code,
            'data'=>$data
        ];
        return json_encode($res);
    }

	public function sendMessageByUid($uid,$message)
	{
		global $worker;
		if(isset($worker->uidConnections[$uid]))
		{
			$connection = $worker->uidConnections[$uid];
			$connection->send($message);
		}
	}
	
	public function broadcast($message)
	{
		global $worker;
		foreach($worker->uidConnections as $connection)
		{
				$connection->send($message);
		}
	}
}