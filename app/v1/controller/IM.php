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

class IM extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ],
        "checkSex" => [
            "except" => "",
        ]
    ];

    public function sendMessage()
    {
        $request = $this->query["content"];
        $tUId = $request["t_u_id"] ?? 0;
        $user = $this->query["user"];

        if (!checkInt($tUId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $service = new IMService();
        $returnData = $service->sendMessage($user, $tUId);
        return $this->jsonResponse($returnData);
    }
}