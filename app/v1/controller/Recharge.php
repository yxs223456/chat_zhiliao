<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/29
 * Time: 下午3:47
 */

namespace app\v1\controller;


use app\common\AppException;
use app\common\service\RechargeService;
use app\v1\transformer\recharge\IndexTransformer;
use app\v1\transformer\recharge\PayByAliTransformer;
use app\v1\transformer\recharge\PayByWeChatTransformer;
use think\facade\Db;

class Recharge extends Base
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
     * 充值规格获取
     *
     * @return \think\response\Json
     */
    public function index()
    {
        $service = new RechargeService();
        $data = $service->index();
        return $this->jsonResponse($data, new IndexTransformer());
    }

    /**
     * 微信充值
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function payByWeChat()
    {
        $request = $this->query["content"];
        $rechargeId = $request["id"] ?? 0;
        if (!checkInt($rechargeId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];

        $service = new RechargeService();
        $data = $service->payByWeChat($rechargeId, $user);
        return $this->jsonResponse($data, new PayByWeChatTransformer());
    }

    /**
     * 支付宝充值
     *
     * @return string
     * @throws AppException
     */
    public function payByAli()
    {
        $request = $this->query["content"];
        $rechargeId = $request["id"] ?? 0;
        if (!checkInt($rechargeId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];

        $service = new RechargeService();
        $data = $service->payByAli($rechargeId, $user);
        return $this->jsonResponse(["url" => $data], new PayByAliTransformer());
    }
}