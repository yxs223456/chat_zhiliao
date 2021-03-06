<?php

namespace app\v1\controller;

use app\common\AppException;
use app\common\enum\SmsSceneEnum;
use app\common\enum\UserOccupationEnum;
use app\common\enum\UserSexEnum;
use app\common\enum\UserSwitchEnum;
use app\common\helper\JPush;
use app\common\service\BlackListService;
use app\common\service\UserService;
use app\common\service\VisitorService;
use app\v1\transformer\user\AllInfoTransformer;
use app\v1\transformer\user\BlackListTransformer;
use app\v1\transformer\user\IndexTransformer;
use app\v1\transformer\user\WalletTransformer;

class User extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "sendVerifyCode,register,passwordLogin,codeLogin,phoneLogin,weChatLogin,resetPassword",
        ],
        "checkSex" => [
            "except" => "sendVerifyCode,register,passwordLogin,codeLogin,phoneLogin,weChatLogin,resetPassword,
                        setSex,info,editInfo",
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
        $deviceNo = $this->query["device_no"] ?? ""; // 设备标示

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
        $returnData = $us->register($areaCode, $mobilePhone, $password, $verifyCode, $inviteUserNumber, $deviceNo);

        return $this->jsonResponse($returnData, new AllInfoTransformer());
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
        $deviceNo = $this->query["device_no"] ?? "";

        if (empty($account) || $password === "") {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $returnData = $us->passwordLogin($account, $password, $deviceNo);

        return $this->jsonResponse($returnData, new AllInfoTransformer());
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
        $deviceNo = $this->query["device_no"] ?? "";

        if (empty($mobilePhone) || $verifyCode === null || empty($areaCode)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (!checkInt($areaCode, false)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $returnData = $us->codeLogin($areaCode, $mobilePhone, $verifyCode, $inviteUserNumber, $deviceNo);

        return $this->jsonResponse($returnData, new AllInfoTransformer());
    }

    /**
     * 手机号直接登录
     */
    public function phoneLogin()
    {
        $request = $this->query["content"];
        $accessToken = $request["access_token"]??"";
        $inviteUserNumber = $request["invite_user_number"] ?? "";
        $deviceNo = $this->query["device_no"] ?? "";
        if (empty($accessToken)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $returnData = $us->phoneLogin($accessToken, $inviteUserNumber, $deviceNo);

        return $this->jsonResponse($returnData, new AllInfoTransformer());
    }

    /**
     * 微信登录
     */
    public function weChatLogin()
    {
        $request = $this->query["content"];
        $weChatCode = $request["code"]??"";
        $inviteUserNumber = $request["invite_user_number"] ?? "";
        $deviceNo = $this->query["device_no"] ?? "";

        if (empty($weChatCode)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $us = new UserService();
        $returnData = $us->weChatLogin($weChatCode, $inviteUserNumber, $deviceNo);

        return $this->jsonResponse($returnData, new AllInfoTransformer());
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
        $open = $request["switch"] ?? null;
        $coin = $request["coin"] ?? null;
        if (isset($open) && !in_array($open, [UserSwitchEnum::ON, UserSwitchEnum::OFF])) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        if (isset($coin) && !checkInt($coin)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $user = $this->query["user"];

        $service = new UserService();
        $service->setVideoOrVoice($open, $coin, $user,"video");
        return $this->jsonResponse(UserService::getUserAllInfo($user['id']), new AllInfoTransformer());
    }

    /**
     * 设置语音通话金额，开启关闭
     */
    public function setVoice()
    {
        $request = $this->query["content"];
        $open = $request["switch"] ?? null;
        $coin = $request["coin"] ?? null;
        if (isset($open) && !in_array($open, [UserSwitchEnum::ON, UserSwitchEnum::OFF])) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        if (isset($coin) && !checkInt($coin)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $user = $this->query["user"];

        $service = new UserService();
        $service->setVideoOrVoice($open, $coin, $user, 'voice');
        return $this->jsonResponse(UserService::getUserAllInfo($user['id']), new AllInfoTransformer());
    }

    /**
     * 设置语音通话金额，开启关闭
     */
    public function setCall()
    {
        $request = $this->query["content"];
        $open = $request["switch"] ?? null;
        $coin = $request["coin"] ?? null;
        if (isset($open) && !in_array($open, [UserSwitchEnum::ON, UserSwitchEnum::OFF])) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        if (isset($coin) && !checkInt($coin)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $user = $this->query["user"];

        $service = new UserService();
        $service->setVideoOrVoice($open, $coin, $user);
        return $this->jsonResponse(UserService::getUserAllInfo($user['id']), new AllInfoTransformer());
    }

    /**
     * 设置私信收费
     */
    public function setMessage()
    {
        $request = $this->query["content"];
        $open = $request["switch"] ?? 0;
        if (!in_array($open, [UserSwitchEnum::ON, UserSwitchEnum::OFF])) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];

        $service = new UserService();
        $service->setMessage($open, $user);
        return $this->jsonResponse(UserService::getUserAllInfo($user['id']), new AllInfoTransformer());
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
        return $this->jsonResponse(UserService::getUserAllInfo($user['id']), new AllInfoTransformer());
    }

    /**
     * 查看用户info
     */
    public function info()
    {
        $user = $this->query["user"];
        $service = new UserService();
        $userInfo = $service->getInfo($user);
        return $this->jsonResponse($userInfo, new AllInfoTransformer());
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
        $avatar = $request["avatar"] ?? null;
        $nickname = $request["nickname"] ?? null;
        $birthday = $request["birthday"] ?? null;
        $occupation = $request["occupation"] ?? null;
        $city = $request["city"] ?? null;
        $sex = $request["sex"] ?? null;
        $photo = $request["photos"] ?? null;
        $signatures = $request["signatures"] ?? null;

        $update = [];
        if (isset($birthday)) {
            if (!checkDateFormat($birthday)) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            }
            $update["birthday"] = $birthday;
        }
        if (isset($occupation)) {
            if (!in_array($occupation, UserOccupationEnum::getAllValues())) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            }
            $update["occupation"] = $occupation;
        }
        isset($avatar) && $update["portrait"] = $avatar;
        isset($nickname) && $update["nickname"] = $nickname;
        isset($city) && $update["city"] = $city;

        $user = $this->query["user"];
        $service = new UserService();

        if (isset($photo)) {
            if (!is_string($photo)) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            }
            $photo = array_filter(explode(",", $photo));
            if (count($photo) > 10) {
                throw AppException::factory(AppException::USER_PHOTOS_TOO_MUCH);
            }
            $update["photos"] = json_encode($photo);
        }

        if (isset($signatures)) {
            if (!is_string($signatures)) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            }
            $signatures = array_filter(explode(",", $signatures));
            $update["signatures"] = json_encode($signatures);
        }

        if (empty($update)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        // 如果提交性别信息，修改用户性别
        if (isset($sex)) {
            if (!in_array(
                $sex,
                [
                    UserSexEnum::MALE,
                    UserSexEnum::FEMALE
                ]
            )
            ) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            }
            $service->setSex($sex, $user);
        }

        // 修改用户头像，昵称，生日，照片，城市
        $service->editInfo($user, $update);

        $userInfo = UserService::getUserAllInfo($user['id']);
        return $this->jsonResponse($userInfo, new AllInfoTransformer());
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
     * 用户主页 （添加访问记录）
     */
    public function index()
    {
        $request = $this->query["content"];
        $uid = $request["u_id"] ?? 0;
        if (!checkInt($uid,true)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        if (empty($uid)) {
            $uid = $this->query["user"]['id'];
        }
        // 添加访问记录到队列
        VisitorService::addVisitorLog($uid, $this->query["user"]["id"]);
        $service = new UserService();
        $data = $service->index($uid, $this->query["user"]["id"]);
        return $this->jsonResponse($data, new IndexTransformer());
    }

    /**
     * 我的钱包
     *
     * @return \think\response\Json
     */
    public function wallet()
    {
        $user = $this->query['user'];
        $service = new UserService();
        $data = $service->wallet($user);
        return $this->jsonResponse($data, new WalletTransformer());
    }
}
