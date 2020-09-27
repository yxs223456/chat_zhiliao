<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/24
 * Time: 下午1:49
 */

namespace app\v1\controller;


use app\common\AppException;
use app\common\service\CharmService;
use app\v1\transformer\charm\ListTransformer;

class Charm extends Base
{

    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ]
    ];

    /**************************************************(女神魅力榜)*************************************************/

    /**
     * 月榜（本月）
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function month()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"] ?? 1;
        $pageSize = $request["page_size"] ?? 20;
        $user = $this->query["user"];

        if (!checkInt($pageSize, false) || !checkInt($pageNum, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new CharmService();
        $startTime = date("Y-m-01");
        $endTime = date("Y-m-31");
        return $this->jsonResponse($service->rankList($startTime, $endTime, $pageNum, $pageSize, $user), new ListTransformer());
    }

    /**
     * 周榜 (本周)
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function week()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"] ?? 1;
        $pageSize = $request["page_size"] ?? 20;
        $user = $this->query["user"];

        if (!checkInt($pageSize, false) || !checkInt($pageNum, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new CharmService();
        $week = getWeekStartAndEnd();
        return $this->jsonResponse($service->rankList($week[0], $week[1], $pageNum, $pageSize, $user), new ListTransformer());
    }

    /**
     * 日榜（本日）
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function day()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"] ?? 1;
        $pageSize = $request["page_size"] ?? 20;
        $user = $this->query["user"];

        if (!checkInt($pageSize, false) || !checkInt($pageNum, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new CharmService();
        $date = date("Y-m-d");
        return $this->jsonResponse($service->rankList($date, $date, $pageNum, $pageSize, $user), new ListTransformer());
    }

}