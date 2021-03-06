<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 16:26
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\enum\UserIsStealthEnum;
use app\common\helper\Redis;
use app\common\service\HomeService;
use app\common\service\ToolService;
use app\common\service\UserSetService;
use app\v1\transformer\home\NewUserList;
use app\v1\transformer\home\RecommendUserList;
use app\v1\transformer\home\SiteUserList;

class Home extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "only" => "geo",
        ],
        "checkSex" => [
            "except" => "geo",
        ]
    ];

    /**
     * 首页推荐列表
     */
    public function recommendUserList()
    {
        $request = $this->query["content"];
        $sex = $request["sex"]??0;
        $price = $request["price"]??0;
        $pageNum = $request["page_num"]??1;
        $pageSize = $request["page_size"]??10;
        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new HomeService();
        $returnData = $service->recommendUserList($sex, $price, $pageNum, $pageSize);

        return $this->jsonResponse($returnData, new RecommendUserList());
    }

    /**
     * 首页活跃列表
     */
    public function activeUserList()
    {
        $request = $this->query["content"];
        $sex = $request["sex"]??0;
        $price = $request["price"]??0;
        $pageNum = $request["page_num"]??1;
        $pageSize = $request["page_size"]??10;
        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new HomeService();
        $returnData = $service->recommendUserList($sex, $price, $pageNum, $pageSize);

        return $this->jsonResponse($returnData, new RecommendUserList());
    }

    /**
     * 首页新人列表
     */
    public function newUserList()
    {
        $request = $this->query["content"];
        $sex = $request["sex"]??0;
        $price = $request["price"]??0;
        $pageNum = $request["page_num"]??1;
        $pageSize = $request["page_size"]??10;
        if (!checkInt($pageNum, false) || !checkInt($pageSize, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new HomeService();
        $returnData = $service->newUserList($sex, $price, $pageNum, $pageSize);

        return $this->jsonResponse($returnData, new NewUserList());
    }

    /**
     * 首页对应地区用户列表
     */
    public function siteUserList()
    {
        $request = $this->query["content"];
        $sex = $request["sex"]??0;
        $price = $request["price"]??0;
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
        $returnData = $service->siteUserList($site, $sex, $price, $pageNum, $pageSize);

        return $this->jsonResponse($returnData, new SiteUserList());
    }

    /**
     * 获取oss前端上传token
     *
     * @return \think\response\Json
     */
    public function ossToken()
    {
        $service = new ToolService();
        $data = $service->ossToken();
        return json($data);
    }

    /**
     * 获取oss配置
     *
     * @return \think\response\Json
     */
    public function ossConfig()
    {
        $service = new ToolService();
        $data = $service->ossConfig();
        return $this->jsonResponse($data);
    }

    /**
     * 用户上报地理位置
     */
    public function geo()
    {
        $request = $this->query["content"];
        $long = $request["long"] ?? 0; // 经度
        $lat = $request["lat"] ?? 0; // 纬度
        $userId = $this->query["user"]['id'];

        $redis = Redis::factory();
        $userSet = UserSetService::getUserSetByUId($userId, $redis);
        // 不隐身 缓存当前用户坐标
        if ($userSet["is_stealth"] == UserIsStealthEnum::NO) {
            cacheUserLongLatInfo($userId, $lat, $long, $redis);
        }
        return $this->jsonResponse(new \stdClass());
    }

}