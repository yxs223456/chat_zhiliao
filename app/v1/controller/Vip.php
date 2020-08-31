<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-31
 * Time: 10:03
 */

namespace app\v1\controller;

use app\common\service\VipService;
use app\v1\transformer\vip\Home;

class Vip extends Base
{
    protected $beforeActionList = [
        "getUser" => [

        ]
    ];

    public function home()
    {
        $user = $this->query["user"];

        $service = new VipService();
        $returnData = $service->home($user);

        return $this->jsonResponse($returnData, new Home());
    }
}