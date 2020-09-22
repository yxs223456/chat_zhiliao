<?php

namespace app\v1\controller;

use app\common\AppException;
use app\common\enum\SmsSceneEnum;
use app\common\enum\UserOccupationEnum;
use app\common\enum\UserSexEnum;
use app\common\enum\UserSwitchEnum;
use app\common\service\BlackListService;
use app\common\service\UserService;
use app\v1\transformer\user\BlackListTransformer;
use app\v1\transformer\user\IndexTransformer;
use app\v1\transformer\user\InfoTransformer;
use app\v1\transformer\user\LoginTransformer;

class User extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "sendVerifyCode,register,passwordLogin,codeLogin,phoneLogin,weChatLogin,resetPassword",
        ],
        "checkSex" => [
            "except" => "setSex",
        ]
    ];

    /**
     * 用户发送验证码
     */
    public function sendVerifyCode()
    {
        $request = $this->query["content"];
        $mobilePhone = $request["mobile_phone"] ?? "";
        $areaCode = !empty($request["area_code"]) ? $request["area_code"] : "86";
        $scene = !empty($request["scene"]) ? $request["scene"] : SmsSceneEnum::LOGIN;

        if (empty($mobilePhone)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $re = $us->sendVerifyCode($mobilePhone, $areaCode, $scene);

        if (empty($re)) {
            throw AppException::factory(AppException::USER_SEND_SMS_ERR);
        }

        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 手机号密码注册
     * @return \think\response\Json
     * @throws AppException
     * @throws \Throwable
     */
    public function register()
    {
        $request = $this->query["content"];
        $mobilePhone = $request["mobile_phone"] ?? "";
        $areaCode = !empty($request["area_code"]) ? $request["area_code"] : "86";
        $verifyCode = $request["verify_code"] ?? null;
        $inviteUserNumber = $request["invite_user_number"] ?? "";
        $password = $request["password"] ?? "";

        if (empty($mobilePhone) || $verifyCode === null || empty($areaCode)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!checkInt($areaCode, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if ($password === "") {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $returnData = $us->register($areaCode, $mobilePhone, $password, $verifyCode, $inviteUserNumber);

        return $this->jsonResponse($returnData, new LoginTransformer);
    }

    /**
     * 密码登录
     * @return \think\response\Json
     * @throws AppException
     */
    public function passwordLogin()
    {
        $request = $this->query["content"];
        $account = $request["account"] ?? "";
        $password = $request["password"] ?? "";

        if (empty($account) || $password === "") {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $returnData = $us->passwordLogin($account, $password);

        return $this->jsonResponse($returnData, new LoginTransformer);
    }

    /**
     * 用户手机号验证码登陆
     */
    public function codeLogin()
    {
        $request = $this->query["content"];
        $mobilePhone = $request["mobile_phone"] ?? "";
        $areaCode = !empty($request["area_code"]) ? $request["area_code"] : "86";
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
     * 重置密码
     */
    public function resetPassword()
    {
        $request = $this->query["content"];
        $mobilePhone = $request["mobile_phone"] ?? "";
        $areaCode = !empty($request["area_code"]) ? $request["area_code"] : "86";
        $verifyCode = $request["verify_code"] ?? null;
        $password = $request["password"] ?? "";

        if (empty($mobilePhone) || $verifyCode === null || empty($areaCode)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!checkInt($areaCode, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if ($password === "") {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $returnData = $us->resetPassword($areaCode, $mobilePhone, $verifyCode, $password);

        return $this->jsonResponse($returnData);
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

    /**
     * 查看用户info
     */
    public function info()
    {
        $user = $this->query["user"];
        $service = new UserService();
        $userInfo = $service->getInfo($user);
        return $this->jsonResponse($userInfo, new InfoTransformer());
    }

    /**
     * 编辑用户信息
     *
     * @return \think\response\Json
     * @throws AppException
     */
    public function editInfo()
    {
        $request = $this->query["content"];
        $avatar = $request["avatar"] ?? "";
        $nickname = $request["nickname"] ?? "";
        $birthday = $request["birthday"] ?? "";
        $occupation = $request["occupation"] ?? 0;
        $photo = $request["photos"] ?? [];
        if (!empty($birthday) && !checkDateFormat($birthday)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!empty($occupation) && !in_array($occupation, UserOccupationEnum::getAllValues())) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!empty($photo) && !is_array($photo)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new UserService();
        $update = ["portrait" => $avatar, "nickname" => $nickname, "birthday" => $birthday, "occupation" => $occupation, "photos" => json_encode($photo)];
        $service->editInfo($user, array_filter($update));
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 黑名单列表
     */
    public function blackList()
    {
        $user = $this->query["user"];
        $service = new BlackListService();
        $list = $service->list($user);
        return $this->jsonResponse($list, new BlackListTransformer());
    }

    /**
     * 加入黑名单 (黑名单内不能进行关注,通话,私信,评论,赞,打赏操作)
     */
    public function addBlack()
    {
        $request = $this->query["content"];
        $blackUserId = $request['black_u_id'] ?? 0;
        if (!checkInt($blackUserId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new BlackListService();
        $service->addBlack($user, $blackUserId);
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 移除黑名单
     */
    public function removeBlack()
    {
        $request = $this->query["content"];
        $blackUserId = $request["black_u_id"] ?? 0;
        if (!checkInt($blackUserId, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new BlackListService();
        $service->removeBlack($user, $blackUserId);
        return $this->jsonResponse(new \stdClass());
    }

    /**
     * 用户主页
     */
    public function index()
    {
        $request = $this->query["content"];
        $uid = $request["u_id"] ?? 0;
        if (!checkInt($uid)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (empty($uid)) {
            $uid = $this->query["user"]['id'];
        }

        $service = new UserService();
        $data = $service->index($uid);
        return $this->jsonResponse($data, new IndexTransformer());
    }
}
