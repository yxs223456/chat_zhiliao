<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 16:32
 */

namespace app\common\service;


use app\common\AppException;
use app\common\enum\AppIsDownEnum;
use app\common\enum\AppIsForceEnum;
use app\common\enum\AppIsUpdateEnum;
use app\common\enum\AppVersionTypeEnum;
use app\common\helper\AliyunOss;
use think\facade\Db;

class VersionService extends Base
{

    /**
     * 获取app最新版本号
     * @param $version
     * @param $system
     *
     * @return array
     * @throws AppException
     */
    public function appVersion($version, $system)
    {
        $default = [
            'version' => $version,
            'description' => "",
            'is_force' => AppIsForceEnum::NO,
            'download_url' => '',
            'is_update' => AppIsUpdateEnum::NO,
        ];

        $buck = Db::name("app_versions")
            ->where("type", $system)
            ->where("is_down", AppIsDownEnum::NO)// 未下架
            ->order("version", "desc")->find();//获取未下架版本最大版本

        if (empty($buck)) {
            return $default;
        }

        $buck["is_update"] = AppIsUpdateEnum::YES;
        $buck["is_force"] = AppIsForceEnum::NO;
        if ($buck["version"] <= $version) {
            $buck["is_update"] = AppIsUpdateEnum::NO;
            return $buck;
        }

        $isForce = Db::name("app_versions")
            ->where("type", $system)
            ->where("is_force", AppIsForceEnum::YES)
            ->where("version", ">", $version)
            ->count();
        if ($isForce) {
            $buck["is_force"] = AppIsForceEnum::YES;
        }
        
        return $buck;
    }

    /**
     * 从header获取os类型
     *
     * @param $os
     * @return mixed
     */
    public static function getOs($os)
    {
        $os = strtolower($os);
        $allow = [AppVersionTypeEnum::ANDROID => "android", AppVersionTypeEnum::IOS => "ios"];
        return array_search($os, $allow);
    }

    /**
     * 上传包
     *
     * @param $id
     * @param $update
     * @return array|null|\think\Model
     */
    public function addAndUpdate($id, $update)
    {

        if (!empty($id)) { // 添加操作
            Db::name("app_versions")->where("id", $id)->update($update);
        } else {
            $id = Db::name("app_versions")->insertGetId($update);
        }

        return Db::name("app_versions")->where("id", $id)->find();
    }
}