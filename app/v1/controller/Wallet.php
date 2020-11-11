<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-11-11
 * Time: 11:06
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\WalletService;
use app\v1\transformer\wallet\IndexTransformer;
use app\v1\transformer\wallet\PayByAliTransformer;
use app\v1\transformer\wallet\PayByWeChatTransformer;

class Wallet extends Base
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
     */
    public function rechargePackage()
    {
        $service = new WalletService();
        $data = $service->rechargePackage();
        return $this->jsonResponse($data, new IndexTransformer());
    }

    /**
     * 微信充值
     */
    public function rechargeByWeChat()
    {
        $request = $this->query["content"];
        $rechargeId = $request["id"] ?? 0;
        if (!checkInt($rechargeId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];

        $service = new WalletService();
        $data = $service->rechargeByWeChat($rechargeId, $user);
        return $this->jsonResponse($data, new PayByWeChatTransformer());
    }

    /**
     * 支付宝充值
     */
    public function rechargeByAli()
    {
        $request = $this->query["content"];
        $rechargeId = $request["id"] ?? 0;
        if (!checkInt($rechargeId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];

        $service = new WalletService();
        $data = $service->rechargeByAli($rechargeId, $user);
        return $this->jsonResponse(["url" => $data], new PayByAliTransformer());
    }

    public function rechargeByLine()
    {

    }
}