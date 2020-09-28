<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-31
 * Time: 10:03
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\VipService;
use app\v1\transformer\vip\Home;
use app\v1\transformer\vip\PayByWeChat;

class Vip extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ],
        "checkSex" => [
            "except" => "",
        ]
    ];

    /**
     * vip 模块首页
     */
    public function home()
    {
        $user = $this->query["user"];
        $service = new VipService();
        $returnData = $service->home($user);

        return $this->jsonResponse($returnData, new Home());
    }

    /**
     * 微信购买vip套餐
     */
    public function payByWeChat()
    {
        $request = $this->query["content"];
        $id = $request["id"]??0;
        if (!checkInt($id, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new VipService();
        $returnData = $service->payByWeChat($user, $id);

        return $this->jsonResponse($returnData, new PayByWeChat());
    }

    /**
     * 支付宝购买vip套餐
     */
    public function payByAli()
    {
        $request = $this->query["content"];
        $id = $request["id"]??0;
        if (!checkInt($id, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new VipService();
        $returnData = $service->payByAli($user, $id);

        return $returnData;
    }
}