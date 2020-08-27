<?php

namespace app\common\service;

use app\common\AppException;
use app\common\Constant;
use app\common\enum\SmsSceneEnum;
use app\common\enum\UserSexEnum;
use app\common\helper\AliMobilePhoneCertificate;
use app\common\helper\Redis;
use app\common\helper\AliSms;
use app\common\helper\RongCloudApp;
use app\common\helper\WechatLogin;
use app\common\model\SmsLogModel;
use app\common\model\UserCommunityModel;
use app\common\model\UserModel;
use think\facade\Db;
use think\facade\Log;

class UserService extends Base
{

    /**
     * 发送短信验证码
     *
     * @param $mobilePhone   string  手机号
     * @param $areaCode int     区号
     * @param $scene    int     短信使用场景
     * @return array
     * @throws AppException
     */
    public function sendVerifyCode($mobilePhone, $areaCode, $scene)
    {
        $verifyCode = mt_rand(100000, 999999);
        $param = array('code' => $verifyCode);

        // 调用接口使用的参数 国内不加区号，国外港澳台加区号
        if ($areaCode == 86) {
            $apiMobile = $mobilePhone;
            $templateType = AliSms::TYPE_CHINA;
        } else {
            $apiMobile = $areaCode . $mobilePhone;
            $templateType = AliSms::TYPE_INTERNATIONAL;
        }

        $re = AliSms::sendSms($apiMobile, $scene, $param, $response, $templateType);

        // 记录所有发送短信返回成功和失败
        $sms = new SmsLogModel();
        if (!$sms->sendCodeMS($areaCode, $mobilePhone, $param, $response, $scene)) {
            Log::error("手机号" . $mobilePhone . "短信log写入错误 ：" . json_encode($param) . json_encode($response));
        }

        // 保存redis
        $redis = Redis::factory();
        setSmsCode(['phone' => $apiMobile, 'code' => $verifyCode, 'scene' => $scene], $redis);

        // 触发限制抛异常
        if (isset($re["Code"]) && $re["Code"] != "OK") {
            throw AppException::factory(AppException::USER_SEND_SMS_LIMIT);
        }

        return $re;
    }

    /**
     * 手机号验证码登录
     * @param $areaCode
     * @param $mobilePhone
     * @param $verifyCode
     * @param $inviteUserNumber
     * @return array
     * @throws AppException
     * @throws \Throwable
     */
    public function codeLogin($areaCode, $mobilePhone, $verifyCode, $inviteUserNumber)
    {
        // 判断验证码是否正确
        $apiMobile = $areaCode == 86 ? $mobilePhone : $areaCode . $mobilePhone;
        $redis = Redis::factory();
        $cacheCode = getSmsCode($apiMobile, SmsSceneEnum::LOGIN, $redis);
        if ($cacheCode != $verifyCode) {
            throw AppException::factory(AppException::USER_VERIFY_CODE_ERR);
        }

        // 通过手机号获取用户
        $userModel = new UserModel();
        $user = $userModel->findByMobilePhone($mobilePhone);

        if ($user == null) {
            // 用户不存在执行注册流程
            $returnData = $this->registerByPhone($areaCode, $mobilePhone, $inviteUserNumber);
        } else {
            // 用户存在直接登录
            $returnData = $this->doLogin($user->toArray());
        }
        return $returnData;
    }

    /**
     * 手机号直接登录
     * @param $accessToken
     * @param $inviteUserNumber
     * @return array
     * @throws AppException
     * @throws \AlibabaCloud\Client\Exception\ClientException
     * @throws \AlibabaCloud\Client\Exception\ServerException
     * @throws \Throwable
     */
    public function phoneLogin($accessToken, $inviteUserNumber)
    {
        // 通过access_token获取手机号
        $mobile = AliMobilePhoneCertificate::getMobile($accessToken);

        // 通过手机号获取用户
        $userModel = new UserModel();
        $user = $userModel->findByMobilePhone($mobile);

        if ($user == null) {
            // 用户不存在执行注册流程
            $returnData = $this->registerByPhone("86", $mobile, $inviteUserNumber);
        } else {
            // 用户存在直接登录
            $returnData = $this->doLogin($user->toArray());
        }
        return $returnData;
    }

    /**
     * app端微信登录
     * @param $weChatCode
     * @param $inviteUserNumber
     * @return array
     * @throws AppException
     * @throws \Throwable
     */
    public function weChatLogin($weChatCode, $inviteUserNumber)
    {
        // 获取用户微信信息
        $weChatLogin = WechatLogin::getObject();
        $weChatUserInfo = $weChatLogin->getUser($weChatCode);

        // 通过unionid获取用户
        $userModel = new UserModel();
        $user = $userModel->findByWeChatUnionid($weChatUserInfo["unionid"]);

        if ($user == null) {
            // 用户不存在执行注册流程
            $returnData = $this->registerByWeChatApp($weChatUserInfo, $inviteUserNumber);
        } else {
            // 用户存在直接登录
            $returnData = $this->doLogin($user->toArray());
        }
        return $returnData;
    }

    // 通过微信移动应用注册用户
    private function registerByWeChatApp($weChatUserInfo, $inviteUserNumber)
    {
        //判断邀请用户是否存在
        $userModel = new UserModel();
        if (!empty($inviteUserNumber)) {
            $parent = $userModel->findById($inviteUserNumber);
            if (empty($parent)) {
                throw AppException::factory(AppException::USER_USER_NUMBER_NOT_EXISTS);
            }
            $userCommunityModel = new UserCommunityModel();
            $parentCommunity = $userCommunityModel->findByUId($parent["id"]);
            $parent = $parent->toArray();
            $parent["p_id_path"] = $parentCommunity["p_id_path"];
        } else {
            $parent = null;
        }

        // 创建融云用户
        $userNumber = $this->createUserNumber(6);
        $nickname = $weChatUserInfo["nickname"]?$weChatUserInfo["nickname"]:"用户" . $userNumber;
        $portrait = $weChatUserInfo["headimgurl"]?$weChatUserInfo["headimgurl"]:Constant::USER_DEFAULT_PORTRAIT;
        $rongCloudResponse = RongCloudApp::register($userNumber, $nickname, $portrait);
        if (!empty($rongCloudResponse["token"])) {
            $rongCloudToken = $rongCloudResponse["token"];
        } else {
            throw new \Exception("融云用户创建失败：" . json_encode($rongCloudResponse, JSON_UNESCAPED_UNICODE));
        }

        Db::startTrans();
        try {
            // user表
            $userToken = getRandomString();
            $newUser = [
                "mobile_phone_area" => "",
                "wc_unionid" => $weChatUserInfo["unionid"],
                "wc_app_openid" => $weChatUserInfo["openid"],
                "user_number" => $userNumber,
                "token" => $userToken,
                "sex" => UserSexEnum::UNKNOWN,
            ];
            $newUser["id"] = Db::name("user")->insertGetId($newUser);

            // 后续处理
            $this->registerAfter($newUser, $parent, $rongCloudToken, $nickname, $portrait);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        // 缓存用户登陆信息
        cacheUserInfoByToken($newUser, Redis::factory());
        if ($parent) {
            $this->registerCallback($newUser["id"]);
        }

        return [
            "user_number" => $newUser["user_number"],
            "token" => $newUser["token"],
            "sex" => $newUser["sex"],
            "rc_token" => $rongCloudToken,
        ];
    }

    // 通过手机号注册用户
    private function registerByPhone($areaCode, $mobilePhone, $inviteUserNumber)
    {
        //判断邀请用户是否存在
        $userModel = new UserModel();
        if (!empty($inviteUserNumber)) {
            $parent = $userModel->findById($inviteUserNumber);
            if (empty($parent)) {
                throw AppException::factory(AppException::USER_USER_NUMBER_NOT_EXISTS);
            }
            $userCommunityModel = new UserCommunityModel();
            $parentCommunity = $userCommunityModel->findByUId($parent["id"]);
            $parent = $parent->toArray();
            $parent["p_id_path"] = $parentCommunity["p_id_path"];
        } else {
            $parent = null;
        }

        // 创建融云用户
        $userNumber = $this->createUserNumber(6);
        $nickname = "用户" . $userNumber;
        $portrait = Constant::USER_DEFAULT_PORTRAIT;
        $rongCloudResponse = RongCloudApp::register($userNumber, $nickname, $portrait);
        if (!empty($rongCloudResponse["token"])) {
            $rongCloudToken = $rongCloudResponse["token"];
        } else {
            throw new \Exception("融云用户创建失败：" . json_encode($rongCloudResponse, JSON_UNESCAPED_UNICODE));
        }

        Db::startTrans();
        try {
            // user表
            $userToken = getRandomString();
            $newUser = [
                "mobile_phone_area" => $areaCode,
                "mobile_phone" => $mobilePhone,
                "user_number" => $userNumber,
                "token" => $userToken,
                "sex" => UserSexEnum::UNKNOWN,
            ];
            $newUser["id"] = Db::name("user")->insertGetId($newUser);

            // 后续处理
            $this->registerAfter($newUser, $parent, $rongCloudToken, $nickname, $portrait);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        // 缓存用户登陆信息
        cacheUserInfoByToken($newUser, Redis::factory());
        if ($parent) {
            $this->registerCallback($newUser["id"]);
        }

        return [
            "token" => $newUser["token"],
            "sex" => $newUser["sex"],
            "user_number" => $newUser["user_number"],
            "rc_token" => $rongCloudToken,
        ];
    }

    // 封装新用户注册公共处理部分
    private function registerAfter($newUser, $parent, $rongCloudToken, $nickname, $portrait)
    {
        // user_info 表
        $userInfoData = [
            "u_id" => $newUser["id"],
            "portrait" => $portrait,
            "nickname" => $nickname,
        ];
        Db::name("user_info")->insert($userInfoData);

        // user_rc_info 表
        $userRcInfoData = [
            "u_id" => $newUser["id"],
            "rc_user_id" => $newUser["user_number"],
            "token" => $rongCloudToken,
            "token_expire" => 0,
        ];
        Db::name("user_rc_info")->insert($userRcInfoData);

        // user_invite_reward 表
        $userInviteRewardData = [
            "u_id" => $newUser["id"],
        ];
        Db::name("user_invite_reward")->insert($userInviteRewardData);

        // user_wallet 表
        $userWalletData = [
            "u_id" => $newUser["id"],
        ];
        Db::name("user_wallet")->insert($userWalletData);

        // user_community表
        $userCommunityData = [
            "u_id" => $newUser["id"],
            "p_id" => $parent ? $parent["id"] : 0,
            "p_id_path" => $parent ? $parent["p_id_path"] . $parent["id"] : "",
        ];
        Db::name("user_community")->insert($userCommunityData);

        // tmp_user_register_callback 表
        if ($parent) {
            Db::name("tmp_user_register_callback")->insert([
                "u_id" => $newUser["id"],
            ]);
        }
    }

    // 用户注册后的异步处理
    private function registerCallback($userId)
    {
        userAddParentCallbackProduce($userId);
    }

    // 生成用户编号
    private function createUserNumber($length = 10)
    {
        //32个字符
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $userModel = new UserModel();

        do {
            $str = '';
            for ($i = 0; $i < $length; $i++) {
                $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
            }
            $user = $userModel->findByUserNumber($str);
        } while($user);

        return $str;
    }

    // 用户登录
    private function doLogin($user)
    {
        // 修改用户token
        $oldToken = $user["token"];
        $user["token"] = getRandomString();
        Db::name("user")
            ->where("id", $user["id"])
            ->update(["token"=> $user["token"]]);

        // 用户融云信息
        $rcUser = Db::name("user_rc_info")
            ->where("u_id", $user["id"])
            ->find();

        // 缓存用户登陆信息
        cacheUserInfoByToken($user, Redis::factory(), $oldToken);

        return [
            "user_number" => $user["user_number"],
            "token" => $user["token"],
            "sex" => $user["sex"],
            "rc_token" => $rcUser["token"],
        ];
    }
}