<?php
namespace app\controller;

use app\BaseController;
use app\common\helper\WeChatWork;

class Index extends BaseController
{
    public function index()
    {
       return WeChatWork::sendMessageToUser("测试消息");
    }
}
