<?php

namespace app\v1\controller;

use app\common\AppException;
use app\common\enum\SmsSceneEnum;
use app\common\helper\Redis;
use app\common\service\UserService;

class User extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "sendVerifyCode,codeLogin",
        ]
    ];

    /**
     * 用户发送验证码
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function sendVerifyCode()
    {
        $request = $this->query["content"];
        $mobile = $request["mobile"] ?? "";
        $areaCode = $request["area_code"] ? $request["area_code"] : "86";
        $scene = $request["scene"] ? $request["scene"] : SmsSceneEnum::LOGIN;

        $us = new UserService();
        $re = $us->sendVerifyCode($mobile, $areaCode, $scene);

        if (empty($re)) {
            throw AppException::factory(AppException::USER_SEND_SMS_ERR);
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
        $mobile = $request["mobile"] ?? "";
        $areaCode = $request["area_code"] ? $request["area_code"] : "86";
        $verifyCode = $request["verify_code"] ?? null;
        $inviteUserNumber = $request["invite_user_number"] ?? "";

        if (empty($mobile) || $verifyCode === null || empty($areaCode)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!checkInt($areaCode, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $ret = $us->codeLogin($areaCode, $mobile, $verifyCode, $inviteUserNumber);

        return $this->jsonResponse($ret);
    }
}
