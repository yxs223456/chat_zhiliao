<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-09
 * Time: 11:26
 */

namespace app\common\service;

use app\common\AppException;
use app\common\enum\CertificateStatusEnum;
use app\common\helper\Redis;
use think\facade\Db;

class CertificationService extends Base
{
    /**
     * 提交自拍认证
     * @param $user
     * @param $certificationImage
     * @return \stdClass
     * @throws AppException
     * @throws \Throwable
     */
    public function submit($user, $certificationImage)
    {
        $redis = Redis::factory();
        $userInfo = UserInfoService::getUserInfoById($user["id"], $redis);

        // 自拍认证审核中或已通过的用户无法再次认证
        if ($userInfo["certificate_status"] == CertificateStatusEnum::WAIT_AUDIT) {
            throw AppException::factory(AppException::USER_CERTIFICATE_SUBMIT_ALREADY);
        } elseif ($userInfo["certificate_status"] == CertificateStatusEnum::SUCCESS) {
            throw AppException::factory(AppException::USER_CERTIFICATE_SUCCESS_ALREADY);
        }

        // 纪录自拍认证请求 将用户自拍认证改为待审核状态
        Db::startTrans();
        try {
            Db::name("user_certification")->insert([
                "u_id" => $user["id"],
                "certification_image" => $certificationImage,
                "audit_status" => CertificateStatusEnum::WAIT_AUDIT,
            ]);

            Db::name("user_info")->where("u_id", $user["id"])->update([
                "certificate_status" => CertificateStatusEnum::WAIT_AUDIT,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        // 删除用户详情缓存
        deleteUserInfoDataByUId($user["id"], $redis);

        return new \stdClass();
    }
}