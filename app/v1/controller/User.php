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
     * 用户手机号验证码登陆
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
        $returnData = $us->codeLogin($areaCode, $mobile, $verifyCode, $inviteUserNumber);

        return $this->jsonResponse($returnData);
    }

    /**
     * 手机号直接登录
     */
    public function phoneLogin()
    {
        $request = $this->query["content"];
        $accessToken = $request["access_token"]??"";
        $inviteUserNumber = $request["invite_user_number"] ?? "";
        if (empty($accessToken)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $returnData = $us->phoneLogin($accessToken, $inviteUserNumber);

        return $this->jsonResponse($returnData);
    }

    /**
     * 微信登录
     */
    public function weChatLogin()
    {
        $request = $this->query["content"];
        $weChatCode = $request["code"]??"";
        $inviteUserNumber = $request["invite_user_number"] ?? "";

        if (empty($weChatCode)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $returnData = $us->weChatLogin($weChatCode, $inviteUserNumber);

        return $this->jsonResponse($returnData);
    }
}
