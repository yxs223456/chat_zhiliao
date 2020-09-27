<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午3:12
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\VisitorService;
use app\v1\transformer\visitor\UserTransformer;

class Visitor extends Base
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
     * 访客列表
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function user()
    {
        $request = $this->query["content"];
        $startId = $request["start_id"] ?? 0;
        $pageSize = $request["page_size"] ?? 20;
        $userId = $this->query["user"]["id"]; // 当前登陆用户ID

        if (!checkInt($startId) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new VisitorService();
        $data = $service->user($startId, $pageSize, $userId);
        return $this->jsonResponse($data, new UserTransformer());
    }

}