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
        ],
        "checkSex" => [
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
        return $this->jsonResponse($service->rankList($pageNum, $pageSize, $user,'month'), new ListTransformer());
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
        return $this->jsonResponse($service->rankList($pageNum, $pageSize, $user, 'week'), new ListTransformer());
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
        return $this->jsonResponse($service->rankList($pageNum, $pageSize, $user), new ListTransformer());
    }

}