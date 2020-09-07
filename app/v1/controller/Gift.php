<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-07
 * Time: 11:12
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\GiftService;
use app\v1\transformer\gift\GetAll;

class Gift extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "getAll",
        ]
    ];

    /**
     * 所有上架的礼物
     */
    public function getAll()
    {
        $service = new GiftService();
        $returnData = $service->getAll();

        return $this->jsonResponse($returnData, new GetAll());
    }

    /**
     * 赠送礼物
     */
    public function give()
    {
        $request = $this->query["content"];
        $gift_id = $request["gift_id"]??0;
        $r_user_number = $request["r_user_number"]??"";
        if (!checkInt($gift_id, false) || empty($r_user_number)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new GiftService();
        $returnData = $service->give($user, $r_user_number, $gift_id);
    }
}