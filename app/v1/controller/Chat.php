<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-08
 * Time: 14:02
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\enum\ChatTypeEnum;
use app\common\service\ChatService;
use app\v1\transformer\chat\Init;

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
    public function init()
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
        $returnData = $s->init($user, $tUId, $chatType);

        return $this->jsonResponse($returnData, new Init());
    }

    /**
     * 挂断通话请求
     */

    /**
     * 结束通话
     */
}