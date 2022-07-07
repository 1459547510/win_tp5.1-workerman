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
        $this->setName('channel');
            // ->addArgument('action', Argument::OPTIONAL, "action  start|stop|restart")
            // ->addArgument('type', Argument::OPTIONAL, "d -d")
            // ->setDescription('workerman chat');
        
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
