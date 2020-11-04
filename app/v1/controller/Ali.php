<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 16:26
 */

namespace app\v1\controller;


use app\BaseController;
use think\facade\Log;

class Ali extends BaseController
{

    /**
     * 首页推荐列表
     */
    public function notice()
    {
        Log::write(json_encode($_POST), "info");
        return json(["code" => 200]);
    }
}