## tp5.1（文档php版本使用7.2.9）
[tp5.1官网](https://www.kancloud.cn/manual/thinkphp5_1/353946)
[tp5.1 git](https://github.com/top-think/think)
cmd输入命令安装tp5.1

`composer create-project topthink/think=5.1.* tp5`

<br/>

## 安装workerman扩展

[workerman官网](https://www.workerman.net/)

tp5根目录下

`composer require topthink/think-worker=2.0.*`

<br/>

## 安装workerman Channel分布式通讯组件

[官方文档](https://www.workerman.net/doc/workerman/components/channel.html)

tp5跟目录下

`composer require workerman/channel:*`

<br/>

## 修改config/worker_server.php

1、增加属性（可选）

单进程添加，并且配置文件中进程数必须设置为1

[官方文档说明](https://www.workerman.net/doc/workerman/faq/send-data-to-client.html)

```php
'uidConnections' => []
```

2、设置自定义服务类

这里是在模块 workermanws\controller下,类型为WorkemanDemo

```php
// 自定义Workerman服务类名 支持数组定义多个服务
'worker_class'   => 'app\workermanws\controller\WorkermanDemo', 
```

```php
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
		// Channel客户端连接到Channel服务端
		// 127.0.0.1为本地连接  如果是两台服务器设置为内网ip
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
```

<br/>

## 自定义开启Channel指令

将会在command模块下新建WorkermanChannel类文件

`php think make:command WorkermanChannel channel`

<br/>

## 修改WorkermanChannel.php

```php
<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use Workerman\Worker;
use Channel;

class WorkermanChannel extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('chann')
            ->addArgument('action', Argument::OPTIONAL, "action  start|stop|restart")
            ->addArgument('type', Argument::OPTIONAL, "d -d")
            ->setDescription('workerman chat');
        // 设置参数
        
    }

    protected function execute(Input $input, Output $output)
    {
        $this->start();
    }

    
    private function start()
    {
        $this->startClienStart();
    }

    private function startClienStart()
    {
        $channel_server = new Channel\Server('0.0.0.0','5506');
        if(!defined('GLOBAL_START'))
        {
            Worker::runAll();
        }
    }
}

```

<br/>

## 命令行开启Channel

`php think channel`

显示一下信息开启成功

```
----------------------- WORKERMAN -----------------------------
Workerman version:3.5.31          PHP version:7.2.9
------------------------ WORKERS -------------------------------
worker               listen                              processes status
ChannelServer        frame://0.0.0.0:5506                1         [ok]
```

## 命令行启动服务端

`php think worker:server`