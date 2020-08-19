<?php

namespace app\v1\controller;

use app\common\AppException;
use app\common\helper\Redis;
use app\common\service\UserService;

class User extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "sendSms,codeLogin,passLogin",
        ]
    ];

    /**
     * 用户发送验证码
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function sendSms()
    {
        $request = $this->query["content"];
        $mobile = $request["mobile"] ?? "";
        if (!checkIsMobile($mobile)) {
            throw AppException::factory(AppException::USER_MOBILE_ERR);
        }

        $us = new UserService();
        $re = $us->sendSms($mobile);

        if (empty($re)) {
            throw AppException::factory(AppException::USER_SMS_ERR);
        }

        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 用户验证码登陆
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function codeLogin()
    {
        $request = $this->query["content"];
        $areaCode = $request["areaCode"] ?? "";
        $mobile = $request["mobile"] ?? "";
        $smsCode = $request["smsCode"] ?? "123456";
        $inviteCode = $request["code"] ?? "";

        if (!checkIsPhone($mobile) || empty($smsCode) || empty($areaCode)) {
            throw AppException::factory(AppException::COM_PARAMS_INVALID);
        }
        if (!checkInt($areaCode, false)) {
            throw AppException::factory(AppException::USER_AREA_CODE_ERR);
        }

        $apiMobile = $mobile;
        if ($areaCode != 86) {
            $apiMobile = $areaCode . $mobile;
        }

        $cacheCode = getSmsCode($apiMobile, Redis::factory());
        if ($cacheCode != $smsCode) {
            throw AppException::factory(AppException::USER_CODE_ERR);
        }

        $us = new UserService();
        if ($us->checkPhoneExists($areaCode, $mobile)) {
            $ret = $us->dologin($areaCode, $mobile, $smsCode, UserService::SMS_CODE_LOGIN);
        } else {
            $ret = $us->register($areaCode, $mobile, $inviteCode);
        }
        return $this->jsonResponse($ret);
    }
}
