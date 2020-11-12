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
            'version' => "",
            'description' => "",
            'is_force' => AppIsForceEnum::NO,
            'download_url' => ''
        ];

        $buck = Db::name("app_versions")
            ->where("type", $system)
            ->where("is_down", AppIsDownEnum::NO)// 未下架
            ->order("version", "desc")->find();//获取未下架版本最大版本

        if (empty($buck)) {
            return $default;
        }

        $buck["is_force"] = AppIsForceEnum::NO;
        if ($buck["version"] == $version) {
            return $buck;
        } else if ($buck["version"] < $version) {
            $buck["is_force"] = AppIsForceEnum::YES;
            return $buck;
        } else {
            $isForce = Db::name("app_versions")
                ->where("type", $system)
                ->where("is_force", AppIsForceEnum::YES)
                ->where("version", ">", $version)
                ->count();
            if ($isForce) {
                $buck["is_force"] = AppIsForceEnum::YES;
            }
        }

        return $buck;
    }
}