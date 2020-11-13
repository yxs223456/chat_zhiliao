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
use think\App;

class Version extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "index,post",
        ],
        "checkSex" => [
            "except" => "index,post",
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
        $v = $this->query["v"]; // 获取当前请求app的版本号
        $os = (int)VersionService::getOs($this->query["os"]); // 获取当前请求的系统类型

        if (!in_array($os, [AppVersionTypeEnum::ANDROID, AppVersionTypeEnum::IOS])) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $service = new VersionService();
        return $this->jsonResponse($service->appVersion($v, $os), new ListTransformer());
    }

    /**
     *  手动上传包
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function post()
    {
        $download = input("download_url") ?? null;
        $type = input("type", null);
        $version = input("version", null);
        $description = input("description", null);
        $isForce = input("is_force", null);
        $isDown = input("is_down", null);

        $update = [];
        if (isset($download)) {
            if (empty($download)) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            } else {
                $update["download_url"] = $download;
            }
        }
        if (isset($type)) {
            if (empty($type)) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            } else {
                $update["type"] = $type;
            }

        }

        if (empty($version)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $update["version"] = $version;

        if (isset($description)) {
            if (empty($description)) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            } else {
                $update["description"] = $description;
            }
        }
        if (isset($isForce)) {
            $update["is_force"] = $isForce;
        }
        if (isset($isDown)) {
            $update["is_down"] = $isDown;
        }

        if (empty($update)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $service = new VersionService();
        $data = $service->addAndUpdate($update);

        return $this->jsonResponse($data);
    }

}