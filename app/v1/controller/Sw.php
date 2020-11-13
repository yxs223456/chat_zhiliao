<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-26
 * Time: 15:53
 */
namespace app\v1\controller;

use app\common\AppException;
use app\common\service\ChatService;
use app\common\service\IMService;
use app\v1\transformer\shengwang\GetChatToken;
use app\v1\transformer\shengwang\GetImToken;

class Sw extends Base
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
     * 获取声网 1v1私聊 RTM token
     * @return \think\response\Json
     * @throws \Exception
     */
    public function getImToken()
    {
        $user = $this->query["user"];

        $service = new IMService();
        $returnData = $service->getSWImToken($user);

        return $this->jsonResponse($returnData, new GetImToken());
    }

    /**
     * 获取声网 1v1通话 RTC token
     * @return \think\response\Json
     * @throws AppException
     */
    public function getChatToken()
    {
        $request = $this->query["content"];
        $chatId = $request["chat_id"] ?? 0;
        if (!checkInt($chatId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new ChatService();
        $returnData = $service->getSwRtcToken($user, $chatId);

        return $this->jsonResponse($returnData);
    }
}