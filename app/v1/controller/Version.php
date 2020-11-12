<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午3:12
 */

namespace app\v1\controller;


use app\common\AppException;
use app\common\enum\AppVersionTypeEnum;
use app\common\service\VersionService;
use app\v1\transformer\version\ListTransformer;

class Version extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "index",
        ],
        "checkSex" => [
            "except" => "index",
        ]
    ];

    /**
     * 获取版本信息
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function index()
    {
        $request = $this->query["content"];
        $version = $request["version"] ?? 0;
        $system = $request["system"] ?? 0;

        if (!checkInt($version) || !in_array($system, [AppVersionTypeEnum::ANDROID, AppVersionTypeEnum::IOS])) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new VersionService();
        return $this->jsonResponse($service->appVersion($version, $system), new ListTransformer());
    }

}