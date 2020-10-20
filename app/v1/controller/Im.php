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
use app\v1\transformer\im\CheckSendMessage;

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
     * 判断是否可以发送私聊
     */
    public function checkSendMessage()
    {
        $request = $this->query["content"];
        $tUId = $request["t_u_id"] ?? 0;
        $user = $this->query["user"];

        if (!checkInt($tUId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $service = new IMService();
        $returnData = $service->checkSendMessage($user, $tUId);
        return $this->jsonResponse($returnData, new CheckSendMessage());
    }
}