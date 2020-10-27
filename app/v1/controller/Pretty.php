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
use app\common\service\GuardService;
use app\common\service\PrettyService;
use app\v1\transformer\pretty\LastWeekTransformer;
use app\v1\transformer\pretty\RecentlyTransformer;
use app\v1\transformer\pretty\ThisWeekTransformer;
use app\v1\transformer\pretty\WaitTransformer;

class Pretty extends Base
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
     * 申请女神条件完成情况
     */
    public function conditionInfo()
    {
        $user = $this->query["user"];
        $service = new PrettyService();
        $returnData = $service->conditionInfo($user);

    }

    /**
     * 女神上周贡献榜
     */
    public function lastWeekContributeList()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"] ?? 1;
        $pageSize = $request["page_size"] ?? 10;
        $uid = $request["id"] ?? 0;

        if (!checkInt($pageSize, false) || !checkInt($pageNum, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        if (!checkInt($uid)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        if (!$uid) {
            $uid = $this->query['user']['id'];
        }

        $service = new CharmService();
        $data = $service->lastWeekContributeList($uid, $this->query['user']['id'], $pageNum, $pageSize);
        return $this->jsonResponse($data, new LastWeekTransformer());
    }

    /**
     * 女神本周角逐榜单
     */
    public function thisWeekContributeList()
    {
        $request = $this->query['content'];
        $pageNum = $request["page_num"] ?? 1;
        $pageSize = $request["page_size"] ?? 10;
        $uid = $request['id'] ?? 0;

        if (!checkInt($pageSize, false) || !checkInt($pageNum, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

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

    /**
     * 等待被守护
     */
    public function wait()
    {
        $user = $this->query["user"];

        $service = new GuardService();
        $data = $service->wait($user);
        return $this->jsonResponse($data, new WaitTransformer());
    }

    /**
     * 最近被守护
     */
    public function recently()
    {
        $user = $this->query["user"];

        $service = new GuardService();
        $data = $service->recently($user);
        return $this->jsonResponse($data, new RecentlyTransformer());
    }
}