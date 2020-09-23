<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-08
 * Time: 14:02
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\Constant;
use app\common\enum\ChatTypeEnum;
use app\common\service\ChatService;
use app\common\service\GiftService;
use app\v1\transformer\chat\Answer;
use app\v1\transformer\chat\Dial;
use app\v1\transformer\chat\Gift;
use app\v1\transformer\chat\RedPackage;

class Chat extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ]
    ];

    /**
     * 初始化通话
     */
    public function dial()
    {
        $request = $this->query["content"];
        $tUId = $request["t_u_id"] ?? "";
        $chatType = $request["chat_type"] ?? 0;

        if (!checkInt($tUId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!in_array($chatType, ChatTypeEnum::getAllValues())) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $s = new ChatService();
        $returnData = $s->dial($user, $tUId, $chatType);

        return $this->jsonResponse($returnData, new Dial());
    }

    /**
     * 挂断通话请求
     */
    public function hangUp()
    {
        $request = $this->query["content"];
        $chatId = $request["chat_id"] ?? 0;
        if (!checkInt($chatId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $s = new ChatService();
        $returnData = $s->hangUp($user, $chatId);

        return $this->jsonResponse($returnData);
    }

    /**
     * 接听通话请求
     */
    public function answer()
    {
        $request = $this->query["content"];
        $chatId = $request["chat_id"] ?? 0;
        if (!checkInt($chatId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $s = new ChatService();
        $returnData = $s->answer($user, $chatId);

        return $this->jsonResponse($returnData, new Answer());
    }

    /**
     * 结束通话
     */
    public function end()
    {
        $request = $this->query["content"];
        $chatId = $request["chat_id"] ?? 0;
        if (!checkInt($chatId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $s = new ChatService();
        $returnData = $s->end($user, $chatId);

        return $this->jsonResponse($returnData);
    }

    /**
     * 聊天通话中，赠送礼物
     */
    public function gift()
    {
        $request = $this->query["content"];
        $giftId = $request["gift_id"]??0;
        $chatId = $request["chat_id"]??"";
        if (!checkInt($giftId, false) || !checkInt($chatId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new GiftService();
        $returnData = $service->giveWhenChat($user, $chatId, $giftId);

        return $this->jsonResponse($returnData, new Gift());
    }

    /**
     * 聊天通话中，发送红包
     */
    public function redPackage()
    {
        $request = $this->query["content"];
        $amount = $request["amount"]??0;
        $chatId = $request["chat_id"]??"";
        if (!checkInt($amount, false) || !checkInt($chatId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if ($amount < Constant::RED_PACKAGE_MIN_AMOUNT) {
            throw AppException::factory(AppException::GIFT_RED_PACKAGE_AMOUNT_LESS);
        }

        $user = $this->query["user"];
        $service = new GiftService();
        $returnData = $service->sendRedPackageWhenChat($user, $chatId, $amount);

        return $this->jsonResponse($returnData, new RedPackage());
    }
}