<?php
namespace app\controller;

use app\BaseController;

class Index extends BaseController
{
    public function index()
    {
        throw new \Exception("正式环境异常测试");
    }
}
