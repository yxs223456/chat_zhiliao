<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午3:12
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\NearService;
use app\v1\transformer\near\UserTransformer;

class Near extends Base
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
     * 附近的人
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function user()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"] ?? 1;
        $pageSize = $request["page_size"] ?? 20;
        $long = $request["long"] ?? ""; // 经度
        $lat = $request["lat"] ?? ""; // 纬度
        $isFlush = $request["is_flush"] ?? 0; // 是否刷新 0-否，1-是
        $userId = $this->query["user"]["id"]; // 当前登陆用户ID

        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new NearService();
        list($userInfo, $distance) = $service->user($pageNum, $pageSize, $long, $lat, $isFlush, $userId);
        return $this->jsonResponse($userInfo, new UserTransformer(['distance' => $distance]));
    }

}