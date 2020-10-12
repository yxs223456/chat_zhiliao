<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 16:26
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\helper\AliyunOss;
use app\common\service\HomeService;
use app\v1\transformer\home\NewUserList;
use app\v1\transformer\home\RecommendUserList;
use app\v1\transformer\home\SiteUserList;

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
    public function recommendUserList()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"]??1;
        $pageSize = $request["page_size"]??10;
        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new HomeService();
        $returnData = $service->recommendUserList($pageNum, $pageSize);

        return $this->jsonResponse($returnData, new RecommendUserList());
    }

    /**
     * 首页活跃列表
     */
    public function activeUserList()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"]??1;
        $pageSize = $request["page_size"]??10;
        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new HomeService();
        $returnData = $service->recommendUserList($pageNum, $pageSize);

        return $this->jsonResponse($returnData, new RecommendUserList());
    }

    /**
     * 首页新人列表
     */
    public function newUserList()
    {
        $request = $this->query["content"];
        $pageNum = $request["page_num"]??1;
        $pageSize = $request["page_size"]??10;
        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new HomeService();
        $returnData = $service->newUserList($pageNum, $pageSize);

        return $this->jsonResponse($returnData, new NewUserList());
    }

    /**
     * 首页对应地区用户列表
     */
    public function siteUserList()
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
        $returnData = $service->siteUserList($site, $pageNum, $pageSize);

        return $this->jsonResponse($returnData, new SiteUserList());
    }

    /**
     * 获取oss前端上传token
     *
     * @return \think\response\Json
     */
    public function ossToken()
    {
        return json(AliyunOss::getToken());
    }
}