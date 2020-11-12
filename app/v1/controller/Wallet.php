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

    /**
     * line pay 充值
     */
    public function rechargeByLine()
    {
        $request = $this->query["content"];
        $rechargeId = $request["id"] ?? 0;
        if (!checkInt($rechargeId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];

        $service = new WalletService();
        $returnData = $service->rechargeByLine($rechargeId, $user);
        return $this->jsonResponse($returnData);
    }

    /**
     * 提现页面信息
     */
    public function withdrawInfo()
    {
        $user = $this->query["user"];

        $service = new WalletService();
        $returnData = $service->withdrawInfo($user);
        return $this->jsonResponse($returnData);
    }

    /**
     * 提现
     */
    public function withdrawBy()
    {
        $request = $this->query["content"];
        $amount = $request["amount"] ?? 0;
        if (!checkInt($amount, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];

        $service = new WalletService();
        $returnData = $service->withdrawBy($amount, $user);
        return $this->jsonResponse($returnData);
    }
}