<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 16:26
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\HomeService;
use app\v1\transformer\home\NewList;
use app\v1\transformer\home\RecommendList;

class Home extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "only" => "",
        ],
        "checkSex" => [
            "except" => "",
        ]
    ];

    /**
     * 首页推荐列表
     */
    public function recommendList()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"]??1;
        $pageSize = $request["page_size"]??10;
        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new HomeService();
        $returnData = $service->recommendList($pageNum, $pageSize);

        return $this->jsonResponse($returnData, new RecommendList());
    }

    /**
     * 首页活跃列表
     */
    public function activeList()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"]??1;
        $pageSize = $request["page_size"]??10;
        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new HomeService();
        $returnData = $service->recommendList($pageNum, $pageSize);

        return $this->jsonResponse($returnData, new RecommendList());
    }

    /**
     * 首页新人列表
     */
    public function newList()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"]??1;
        $pageSize = $request["page_size"]??10;
        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new HomeService();
        $returnData = $service->newList($pageNum, $pageSize);

        return $this->jsonResponse($returnData, new NewList());
    }

    /**
     * 首页新人列表
     */
    public function siteList()
    {
        $request = $this->query["content"];
        $site = $request["site"]??"";
        $pageNum = $request["page_num"]??1;
        $pageSize = $request["page_size"]??10;
        if (empty($site)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new HomeService();
        $returnData = $service->siteList($site, $pageNum, $pageSize);

        return $this->jsonResponse($returnData, new NewList());
    }
}