<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/25
 * Time: 上午10:36
 */

namespace app\v1\controller;


use app\common\AppException;
use app\common\service\CharmService;
use app\v1\transformer\pretty\LastWeekTransformer;
use app\v1\transformer\pretty\ThisWeekTransformer;

class Pretty extends Base
{

    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ]
    ];

    /**
     * 女神上周贡献榜
     */
    public function lastWeekContributeList()
    {
        $request = $this->query["content"];
        $uid = $request["id"] ?? 0;

        if (!checkInt($uid)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        if (!$uid) {
            $uid = $this->query['user']['id'];
        }

        $service = new CharmService();
        $data = $service->lastWeekContributeList($uid, $this->query['user']['id']);
        return $this->jsonResponse($data, new LastWeekTransformer());
    }

    /**
     * 女神本周角逐榜单
     */
    public function thisWeekContributeList()
    {
        $request = $this->query['content'];
        $uid = $request['id'] ?? 0;

        if (!checkInt($uid)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        if (!$uid) {
            $uid = $this->query['user']['id'];
        }

        $service = new CharmService();
        $data = $service->thisWeekContributeList($uid, $this->query['user']['id']);
        return $this->jsonResponse($data, new ThisWeekTransformer());
    }
}