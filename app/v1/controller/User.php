<?php

namespace app\v1\controller;

use app\common\AppException;
use app\common\enum\SmsSceneEnum;
use app\common\enum\UserOpenEnum;
use app\common\enum\UserSexEnum;
use app\common\enum\UserSwitchEnum;
use app\common\service\UserService;
use app\v1\transformer\user\LoginTransformer;

class User extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "sendVerifyCode,codeLogin,phoneLogin,weChatLogin",
        ]
    ];

    /**
     * 用户发送验证码
     */
    public function sendVerifyCode()
    {
        $request = $this->query["content"];
        $mobilePhone = $request["mobile_phone"] ?? "";
        $areaCode = $request["area_code"] ? $request["area_code"] : "86";
        $scene = $request["scene"] ? $request["scene"] : SmsSceneEnum::LOGIN;

        $us = new UserService();
        $re = $us->sendVerifyCode($mobilePhone, $areaCode, $scene);

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
        $mobilePhone = $request["mobile_phone"] ?? "";
        $areaCode = $request["area_code"] ? $request["area_code"] : "86";
        $verifyCode = $request["verify_code"] ?? null;
        $inviteUserNumber = $request["invite_user_number"] ?? "";

        if (empty($mobilePhone) || $verifyCode === null || empty($areaCode)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!checkInt($areaCode, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $returnData = $us->codeLogin($areaCode, $mobilePhone, $verifyCode, $inviteUserNumber);

        return $this->jsonResponse($returnData, new LoginTransformer);
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

        return $this->jsonResponse($returnData, new LoginTransformer());
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

        return $this->jsonResponse($returnData, new LoginTransformer());
    }

    /**
     * 设置性别
     */
    public function setSex()
    {
        $request = $this->query["content"];
        $sex = $request["sex"]??null;
        if (!in_array(
            $sex,
            [
                UserSexEnum::MALE,
                UserSexEnum::FEMALE
            ]
        )) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $us = new UserService();
        $returnData = $us->setSex($sex, $user);

        return $this->jsonResponse($returnData);
    }

    /**
     * 设置视频通话金额，开启关闭
     */
    public function setVideo()
    {
        $request = $this->query["content"];
        $open = $request["switch"] ?? 0;
        $coin = $request["coin"] ?? 0;
        if (!in_array($open, [UserSwitchEnum::ON, UserSwitchEnum::OFF])) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!checkInt($coin)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $user = $this->query["user"];

        $service = new UserService();
        $service->setVideoOrVoice($open, $coin, $user);
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 设置语音通话金额，开启关闭
     */
    public function setVoice()
    {
        $request = $this->query["content"];
        $open = $request["switch"] ?? 0;
        $coin = $request["coin"] ?? 0;
        if (!in_array($open, [UserSwitchEnum::ON, UserSwitchEnum::OFF])) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!checkInt($coin)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $user = $this->query["user"];

        $service = new UserService();
        $service->setVideoOrVoice($open, $coin, $user, 'voice');
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 设置私信收费和收费金额
     */
    public function setMessage()
    {

    }

    /**
     * 一键隐身和取消隐身
     */
    public function setStealth()
    {
        $request = $this->query["content"];
        $open = $request["switch"] ?? 0;
        if (!in_array($open, [UserSwitchEnum::ON, UserSwitchEnum::OFF])) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new UserService();
        $service->setStealth($open, $user);
        return $this->jsonResponse(new \stdClass());
    }
}
