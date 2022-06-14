<?php
// +----------------------------------------------------------------------
// | VitPHP
// +----------------------------------------------------------------------
// | 版权所有 2018~2021 藁城区创新网络电子商务中心 [ http://www.vitphp.cn ]
// +----------------------------------------------------------------------
// | VitPHP是一款免费开源软件,您可以访问http://www.vitphp.cn/以获得更多细节。
// +----------------------------------------------------------------------

declare (strict_types = 1);

namespace vitphp\vitlite\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class NodeRefresh extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('vitphp:authnode')
            ->setDescription('刷新Authnode数据');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->info('开始重建权限节点');
        \vitphp\mengzhe\Node::reload();
        // 指令输出
        $output->writeln('权限节点重建完成');
    }
}
