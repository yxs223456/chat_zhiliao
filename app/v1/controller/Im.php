<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-14
 * Time: 14:54
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\IMService;
use app\v1\transformer\im\SendMessage;

class Im extends Base
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
     * 非通话发送私聊消息
     */
    public function sendMessage()
    {
        $request = $this->query["content"];
        $tUId = $request["t_u_id"] ?? 0;
        $message = $request["message"] ?? "";

        if (!checkInt($tUId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (empty($message)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new IMService();
        $returnData = $service->sendMessage($user, $tUId, $message);
        return $this->jsonResponse($returnData, new SendMessage());
    }
}