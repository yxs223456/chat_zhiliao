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

class Chat extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ]
    ];

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
        $s = new UserService();
        $returnData = $us->codeLogin($areaCode, $mobilePhone, $verifyCode, $inviteUserNumber);

        return $this->jsonResponse($returnData, new LoginTransformer);
    }
}