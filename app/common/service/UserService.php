<?php

namespace app\common\service;

use app\common\AppException;
use app\common\Constant;
use app\common\enum\InviteRewardAddEnum;
use app\common\enum\PrettyFemaleLevelEnum;
use app\common\enum\PrettyMaleLevelEnum;
use app\common\enum\SmsSceneEnum;
use app\common\enum\UserSexEnum;
use app\common\enum\UserSwitchEnum;
use app\common\helper\AliMobilePhoneCertificate;
use app\common\helper\Pbkdf2;
use app\common\helper\Redis;
use app\common\helper\AliSms;
use app\common\helper\RongCloudApp;
use app\common\helper\WechatLogin;
use app\common\model\InviteRewardModel;
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
     * 使用手机号密码注册
     * @param $areaCode
     * @param $mobilePhone
     * @param $password
     * @param $verifyCode
     * @param $inviteUserNumber
     * @return array
     * @throws AppException
     * @throws \Throwable
     */
    public function register($areaCode, $mobilePhone, $password, $verifyCode, $inviteUserNumber)
    {
        // 判断验证码是否正确
        $apiMobile = $areaCode == 86 ? $mobilePhone : $areaCode . $mobilePhone;
        $redis = Redis::factory();
        $cacheCode = getSmsCode($apiMobile, SmsSceneEnum::REGISTER, $redis);
        if ($cacheCode != $verifyCode) {
            throw AppException::factory(AppException::USER_VERIFY_CODE_ERR);
        }

        // 执行注册流程
        $returnData = $this->registerByPhoneAndPassword($areaCode, $mobilePhone, $password, $inviteUserNumber);
        return $returnData;
    }

    /**
     * 密码登录
     * @param $account
     * @param $password
     * @return array
     * @throws AppException
     */
    public function passwordLogin($account, $password)
    {
        $userModel = new UserModel();
        $user = $userModel->findByMobilePhone($account);

        if (empty($user)) {
            throw AppException::factory(AppException::USER_ACCOUNT_ERROR);
        }
        if (!Pbkdf2::validate_password($password, $user["password"])) {
            throw AppException::factory(AppException::USER_ACCOUNT_ERROR);
        }

        $returnData = $this->doLogin($user->toArray());
        return $returnData;
    }

    /**
     * 重置密码
     * @param $areaCode
     * @param $mobilePhone
     * @param $verifyCode
     * @param $password
     * @return \stdClass
     * @throws AppException
     */
    public function resetPassword($areaCode, $mobilePhone, $verifyCode, $password)
    {
        // 判断验证码是否正确
        $apiMobile = $areaCode == 86 ? $mobilePhone : $areaCode . $mobilePhone;
        $redis = Redis::factory();
        $cacheCode = getSmsCode($apiMobile, SmsSceneEnum::RESET_PASSWORD, $redis);
        if ($cacheCode != $verifyCode) {
            throw AppException::factory(AppException::USER_VERIFY_CODE_ERR);
        }

        // 通过手机号获取用户
        $userModel = new UserModel();
        $user = $userModel->findByMobilePhone($mobilePhone);
        if (empty($user)) {
            throw AppException::factory(AppException::USER_NOT_EXISTS);
        }
        $user->password = Pbkdf2::create_hash($password);
        $user->save();

        return new \stdClass();
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
        $nickname = $weChatUserInfo["nickname"]?$weChatUserInfo["nickname"]:$userNumber;
        $portrait = $weChatUserInfo["headimgurl"]?$weChatUserInfo["headimgurl"]:Constant::USER_DEFAULT_PORTRAIT;
//        $rongCloudResponse = RongCloudApp::register($userNumber, $nickname, $portrait);
//        if (!empty($rongCloudResponse["token"])) {
//            $rongCloudToken = $rongCloudResponse["token"];
//        } else {
//            throw new \Exception("融云用户创建失败：" . json_encode($rongCloudResponse, JSON_UNESCAPED_UNICODE));
//        }

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
            $this->registerAfter($newUser, $parent, $nickname, $portrait);

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

        return self::getUserAllInfo($newUser["id"]);
    }

    /**
     * 使用手机号密码注册
     * @param $areaCode
     * @param $mobilePhone
     * @param $password
     * @param $inviteUserNumber
     * @return array
     * @throws AppException
     * @throws \Throwable
     */
    private function registerByPhoneAndPassword($areaCode, $mobilePhone, $password, $inviteUserNumber)
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
        $nickname = $userNumber;
        $portrait = Constant::USER_DEFAULT_PORTRAIT;
//        $rongCloudResponse = RongCloudApp::register($userNumber, $nickname, $portrait);
//        if (!empty($rongCloudResponse["token"])) {
//            $rongCloudToken = $rongCloudResponse["token"];
//        } else {
//            throw new \Exception("融云用户创建失败：" . json_encode($rongCloudResponse, JSON_UNESCAPED_UNICODE));
//        }

        Db::startTrans();
        try {
            // user表
            $userToken = getRandomString();
            $newUser = [
                "mobile_phone_area" => $areaCode,
                "mobile_phone" => $mobilePhone,
                "password" => Pbkdf2::create_hash($password),
                "user_number" => $userNumber,
                "token" => $userToken,
                "sex" => UserSexEnum::UNKNOWN,
            ];
            $newUser["id"] = Db::name("user")->insertGetId($newUser);

            // 后续处理
            $this->registerAfter($newUser, $parent, $nickname, $portrait);

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

        return self::getUserAllInfo($newUser["id"]);
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
        $nickname = $userNumber;
        $portrait = Constant::USER_DEFAULT_PORTRAIT;
//        $rongCloudResponse = RongCloudApp::register($userNumber, $nickname, $portrait);
//        if (!empty($rongCloudResponse["token"])) {
//            $rongCloudToken = $rongCloudResponse["token"];
//        } else {
//            throw new \Exception("融云用户创建失败：" . json_encode($rongCloudResponse, JSON_UNESCAPED_UNICODE));
//        }

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
            $this->registerAfter($newUser, $parent, $nickname, $portrait);

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

        return self::getUserAllInfo($newUser["id"]);
    }

    // 封装新用户注册公共处理部分
    private function registerAfter($newUser, $parent, $nickname, $portrait)
    {
        // user_info 表
        $userInfoData = [
            "u_id" => $newUser["id"],
            "portrait" => $portrait,
            "nickname" => $nickname,
        ];
        Db::name("user_info")->insert($userInfoData);

        // user_set 表
        $userSetData = [
            "u_id" => $newUser["id"],
        ];
        Db::name("user_set")->insert($userSetData);

        // user_rc_info 表
//        $userRcInfoData = [
//            "u_id" => $newUser["id"],
//            "rc_user_id" => $newUser["user_number"],
//            "token" => $rongCloudToken,
//            "token_expire" => 0,
//        ];
//        Db::name("user_rc_info")->insert($userRcInfoData);

        // invite_reward 表
        $userInviteRewardData = [
            "u_id" => $newUser["id"],
        ];
        Db::name("invite_reward")->insert($userInviteRewardData);

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

        // chat_free_wallet 表
        $chatFreeWallet = [
            "u_id" => $newUser["id"],
        ];
        Db::name("chat_free_wallet")->insert($chatFreeWallet);


        // tmp_user_register_callback 表
        if ($parent) {
            Db::name("tmp_user_register_callback")->insert([
                "u_id" => $newUser["id"],
            ]);
        }

        // 添加用户总评分表
        Db::name("user_score")->insert(['u_id' => $newUser['id']]);
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
            ->update(["token" => $user["token"]]);

        // 缓存用户登陆信息
        $redis = Redis::factory();
        cacheUserInfoByToken($user, $redis, $oldToken);
        cacheUserInfoById($user, $redis);

        return self::getUserAllInfo($user["id"]);
    }

    /**
     * 用户设置性别
     * @param $sex
     * @param $userBase
     * @return \stdClass
     * @throws \Throwable
     */
    public function setSex($sex, $userBase)
    {
        $userModel = new UserModel();
        Db::startTrans();
        try {
            $user = $userModel->where("id", $userBase["id"])->lock(true)->find();
            // 性别只能设置一次，设置后无法修改
            if ($user["sex"] != UserSexEnum::UNKNOWN) {
                throw AppException::factory(AppException::USER_MODIFY_SEX_FORBIDDEN);
            }
            // 纪录用户性别
            $user->sex = $sex;
            $user->save();

            //根据性别初始化设置
            $sexInitConfig = Constant::SEX_INIT_CONFIG;
            if ($sex == UserSexEnum::FEMALE) {
                $userSetUpdate = [
                    "video_chat_price" => $sexInitConfig["female"]["video_chat_price"],
                    "voice_chat_price" => $sexInitConfig["female"]["voice_chat_price"],
                ];
            } else {
                $userSetUpdate = [
                    "video_chat_price" => $sexInitConfig["male"]["video_chat_price"],
                    "voice_chat_price" => $sexInitConfig["male"]["voice_chat_price"],
                ];
            }
            Db::name("user_set")->where("u_id", $userBase["id"])->update($userSetUpdate);

            // 男生注册，奖励邀请人1元
            if ($sex == UserSexEnum::MALE) {
                $userCommunity = Db::name("user_community")->where("u_id", $user["id"])->find();
                if ($userCommunity["p_id"]) {
                    $inviteRewardModel = new InviteRewardModel();
                    $inviteRewardModel->add(
                        $userCommunity["p_id"],
                        InviteRewardAddEnum::MALE_REGISTER,
                        Constant::INVITE_MALE_REWARD_MONEY,
                        $user["id"]
                    );
                }
            }

            Db::commit();
            $redis = Redis::factory();
            cacheUserInfoByToken($user->toArray(), $redis);
            cacheUserInfoById($user->toArray(), $redis);
            deleteUserInfoDataByUId($userBase["id"], $redis);
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return new \stdClass();
    }

    public static function getUserByToken($token, $redis = null)
    {
        $redis = $redis === null ? Redis::factory() : $redis;
        $cacheUser = getUserInfoByToken($token, $redis);
        if (empty($cacheUser['id'])) {
            $model = new UserModel();
            $user = $model->findByToken($token);
            if ($user) {
                $cacheUser = $user->toArray();
                cacheUserInfoByToken($cacheUser, $redis);
            }
        }
        return $cacheUser;
    }

    public static function getUserByNumber($userNumber, $redis = null)
    {
        $redis = $redis === null ? Redis::factory() : $redis;
        $cacheUser = getUserInfoByNumber($userNumber, $redis);
        if (empty($cacheUser['id'])) {
            $model = new UserModel();
            $user = $model->findByUserNumber($userNumber);
            if ($user) {
                $cacheUser = $user->toArray();
                cacheUserInfoByNumber($cacheUser, $redis);
            }
        }
        return $cacheUser;
    }

    public static function getUserById($userId, $redis = null)
    {
        $redis = $redis === null ? Redis::factory() : $redis;
        $cacheUser = getUserInfoById($userId, $redis);
        if (empty($cacheUser['id'])) {
            $model = new UserModel();
            $user = $model->findById($userId);
            if ($user) {
                $cacheUser = $user->toArray();
                cacheUserInfoById($cacheUser, $redis);
            }
        }
        return $cacheUser;
    }

    /**
     * 设置视频和音频
     *
     * @param $switch int
     * @param $coin int
     * @param $user array
     * @param $type string (video,voice)
     * @throws AppException
     */
    public function setVideoOrVoice($switch, $coin, $user, $type = "video")
    {
        $sex = $user["sex"] ?? 0;
        if ($sex == UserSexEnum::UNKNOWN) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $userInfo = Db::name("user_info")->where("u_id", $user["id"])->find();
        if (empty($userInfo)) {
            throw AppException::factory(AppException::USER_NOT_EXISTS);
        }
        // 关闭操作，计费清零
        if ($switch == UserSwitchEnum::OFF) {
            $coin = 0;
        }
        // 女神金额逻辑
        if ($sex == UserSexEnum::FEMALE) {
            $femaleLevel = $userInfo["pretty_female_level"];
            $ruleCoin = $this->getFemaleCoinRule($femaleLevel);
            if ($coin > $ruleCoin) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            }

            Db::name("user_set")->where("u_id", $user["id"])->update(["{$type}_chat_switch" => $switch, "{$type}_chat_price" => $coin]);
            deleteUserSetByUId($user["id"], Redis::factory());
            return;
        }

        // 男神金额逻辑 必须是vip
        $maleLevel = $userInfo["pretty_male_level"];
        $today = date("Y-m-d");
        $vipDeadline = $userInfo["vip_deadline"] ?? $today;
        $svipDeadline = $userInfo['svip_deadline'] ?? $today;
        // vip过期 不是vip不能设置通话聊天金额
        if ($today >= $vipDeadline && $today >= $svipDeadline && $coin > 0) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $ruleCoin = $this->getMaleCoinRule($maleLevel);
        if ($coin > $ruleCoin) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        Db::name("user_set")->where("u_id", $user["id"])->update(["{$type}_chat_switch" => $switch, "{$type}_chat_price" => $coin]);
        deleteUserSetByUId($user["id"], Redis::factory());
        return;
    }

    /**
     * 设置消息收费
     *
     * @param $switch int
     * @param $user array
     * @throws AppException
     */
    public function setMessage($switch, $user)
    {
        $sex = $user["sex"] ?? 0;
        if ($sex == UserSexEnum::UNKNOWN) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }
        $userInfo = UserInfoService::getUserInfoById($user["id"]);
        if (empty($userInfo)) {
            throw AppException::factory(AppException::USER_NOT_EXISTS);
        }
        // 默认收费标准
        $coin = Constant::PRETTY_MESSAGE_PRICE_COIN;
        // 关闭操作，计费清零
        if ($switch == UserSwitchEnum::OFF) {
            $coin = 0;
        }
        // 女神金额逻辑(是女神)
        if ($sex == UserSexEnum::FEMALE) {
            $isPretty = $userInfo["is_pretty"];
            if (!$isPretty) {
                throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
            }
            Db::name("user_set")->where("u_id", $user["id"])->update(["direct_message_free" => $switch, "direct_message_price" => $coin]);
            deleteUserSetByUId($user["id"], Redis::factory());
            return;
        }

        // 男神金额逻辑 必须是vip
        $today = date("Y-m-d");
        $vipDeadline = $userInfo["vip_deadline"] ?? $today;
        $svipDeadline = $userInfo['svip_deadline'] ?? $today;
        // vip过期 不是vip不能设置通话聊天金额
        if ($today >= $vipDeadline && $today >= $svipDeadline) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        Db::name("user_set")->where("u_id", $user["id"])->update(["direct_message_free" => $switch, "direct_message_price" => $coin]);
        deleteUserSetByUId($user["id"], Redis::factory());
        return;
    }

    /**
     * 一键隐身必须是vip
     *
     * @param $switch int
     * @param $user array
     * @throws AppException
     */
    public function setStealth($switch, $user)
    {
        $redis = Redis::factory();
        // 开启逻辑 （删除用户坐标缓存,判断VIP）
        if ($switch == UserSwitchEnum::ON) {
            // 判断是否是vip
            $userInfo = Db::name("user_info")->where("u_id", $user["id"])->find();
            if (empty($userInfo)) {
                throw AppException::factory(AppException::USER_NOT_EXISTS);
            }
            $today = date("Y-m-d");
            $vipDeadline = $userInfo["vip_deadline"] ?? $today;
            $svipDeadline = $userInfo['svip_deadline'] ?? $today;
            // vip过期 不是vip不能设置通话聊天金额
            if ($today >= $vipDeadline && $today >= $svipDeadline) {
                throw AppException::factory(AppException::USER_NOT_VIP);
            }
            // 删除用户坐标缓存，不出现在附近
            deleteUserLongLatInfoByUserId($user["id"], $redis);
        }
        // 关闭逻辑直接关闭
        // 开启关闭的修改数据库操作
        Db::name("user_set")->where("u_id", $user["id"])->update(["is_stealth" => $switch]);
        deleteUserSetByUId($user["id"], Redis::factory());
        return;
    }

    /**
     * 获取女神等级金额限制规则
     *
     * @param $level
     * @return int|mixed
     */
    private function getFemaleCoinRule($level)
    {
        $levelToCoin = [
            PrettyFemaleLevelEnum::COMMON => Constant::PRETTY_FEMALE_LEVEL_COMMON,
            PrettyFemaleLevelEnum::TRAINEE => Constant::PRETTY_FEMALE_LEVEL_TRAINEE,
            PrettyFemaleLevelEnum::IRON => Constant::PRETTY_FEMALE_LEVEL_IRON,
            PrettyFemaleLevelEnum::COPPER => Constant::PRETTY_FEMALE_LEVEL_COPPER,
            PrettyFemaleLevelEnum::SILVER => Constant::PRETTY_FEMALE_LEVEL_SILVER,
            PrettyFemaleLevelEnum::GOLD => Constant::PRETTY_FEMALE_LEVEL_GOLD,
            PrettyFemaleLevelEnum::CROWN => Constant::PRETTY_FEMALE_LEVEL_CROWN,
        ];
        return $levelToCoin[$level] ?? 0;
    }

    /**
     * 获取男神等级金额限制规则
     *
     * @param $level
     * @return int|mixed
     */
    private function getMaleCoinRule($level)
    {
        $levelToCoin = [
            PrettyMaleLevelEnum::COMMON => Constant::PRETTY_MALE_LEVEL_COMMON,
            PrettyMaleLevelEnum::TRAINEE => Constant::PRETTY_MALE_LEVEL_TRAINEE,
            PrettyMaleLevelEnum::IRON => Constant::PRETTY_MALE_LEVEL_IRON,
            PrettyMaleLevelEnum::COPPER => Constant::PRETTY_MALE_LEVEL_COPPER,
            PrettyMaleLevelEnum::SILVER => Constant::PRETTY_MALE_LEVEL_SILVER,
            PrettyMaleLevelEnum::GOLD => Constant::PRETTY_MALE_LEVEL_GOLD,
            PrettyMaleLevelEnum::CROWN => Constant::PRETTY_MALE_LEVEL_CROWN,
        ];
        return $levelToCoin[$level] ?? 0;
    }

    /**
     * 获取用户info
     *
     * @param $user
     * @return array
     * @throws AppException
     */
    public function getInfo($user)
    {
        $info = UserInfoService::getUserInfoById($user["id"], Redis::factory());
        if (empty($info)) {
            throw AppException::factory(AppException::USER_NOT_EXISTS);
        }
        return self::getUserAllInfo($user['id']);
    }

    /**
     * 修改用户信息
     *
     * @param $user
     * @param $info
     * @throws AppException
     */
    public function editInfo($user, $info)
    {
        $redis = Redis::factory();
        $user = UserInfoService::getUserInfoById($user["id"], $redis);
        if (empty($user)) {
            throw AppException::factory(AppException::USER_NOT_EXISTS);
        }
        Db::name("user_info")->where("u_id", $user["id"])->update($info);
        // 修改头像和昵称更新融云信息
//        if (!empty($info["nickname"]) || !empty($info["portrait"])) {
//            RongCloudApp::updateUserInfo($user["user_number"], $info["nickname"] ?? $user["nickname"], $info["portrait"] ?? $user["portrait"]);
//        }
        deleteUserInfoDataByUId($user["id"], $redis);
        return;
    }

    /**
     * 获取用户主页数据
     *
     * @param $userId int 用户ID
     * @param int $retry 重试次数
     * @return array|mixed
     * @throws AppException
     */
    public function index($userId, $retry = 0)
    {
        $redis = Redis::factory();
        $ret = getUserIndexDataByUId($userId, $redis);
        if (!empty($ret)) {
            return $ret;
        }

        // 某个用户的更新锁
        $lockKey = REDIS_KEY_PREFIX . "USER_INDEX_LOCK" . $userId;
        if ($redis->setnx($lockKey, 1)) {
            // 设置锁过期时间，防止死锁永不更新
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
            $data = [
                "user" => [],
                "userInfo" => [],
                "userSet" => [],// 用户配置
                "dynamics" => [],
                "dynamicLike" => [],
                "videos" => [],
                "gifts" => [],
                "guard" => [], //守护
            ];
            $user = self::getUserById($userId, $redis);
            $userInfo = UserInfoService::getUserInfoById($userId,$redis);
            if (empty($user) || empty($userInfo)) {
                $redis->del($lockKey);
                throw AppException::factory(AppException::USER_NOT_EXISTS);
            }

            $data["user"] = $user;
            $data["userInfo"] = $userInfo;

            // 用户设置
            $set = Db::name("user_set")->where("u_id", $userId)->find();
            $data["userSet"] = $set;

            // 查询四条带有图片的动态
            $dynamic = Db::query("select * from dynamic where u_id=:id and length(source) > 2 order by create_time desc limit 4", ['id' => $userId]);
            $dynamicLike = [];
            if (!empty($dynamic)) {
                $dynamicLikeData = Db::name("dynamic_like")
                    ->whereIn("dynamic_id", array_column($dynamic, "id"))
                    ->group("dynamic_id")
                    ->field("count(dynamic_id) c,dynamic_id")
                    ->select()->toArray();
                if ($dynamicLikeData) {
                    $dynamicLike = array_column($dynamicLikeData, "c", 'dynamic_id');
                }
            }
            $data["dynamics"] = $dynamic;
            $data["dynamicLike"] = $dynamicLike;
            $data["videos"] = [];
            // 查询礼物数据
            $gifts = Db::name("gift_give_log")->alias("gl")
                ->leftJoin("config_gift cg", "gl.g_id = cg.id")
                ->field("cg.image_url,count(gl.g_id) c")
                ->where("gl.r_u_id", $userId)
                ->group("gl.g_id")
                ->order("cg.id", "asc")
                ->select()->toArray();
            $data["gifts"] = $gifts;

            // 获取守护人信息
            $guardUser = GuardService::getGuard($userId);
            $data['guard'] = $guardUser;

            cacheUserIndexDataByUId($userId, $data, $redis);
            $redis->del($lockKey);
            return $data;
        } else {
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME);
            return $this->index($userId, ++$retry);
        }
        throw AppException::factory(AppException::TRY_AGAIN_LATER);
    }

    /**
     * 获取用户我的信息
     *
     * @param $user
     * @return array
     * @throws AppException
     */
    public function wallet($user)
    {
        $userWallet = Db::name("user_wallet")->where("u_id", $user["id"])->find();
        if (empty($userWallet)) {
            throw AppException::factory(AppException::USER_NOT_EXISTS);
        }
        return $userWallet;
    }

    /**
     * 用户数据获取
     *
     * @param $uid
     * @return array
     */
    public static function getUserAllInfo($uid)
    {
        $redis = Redis::factory();
        // 删除用户缓存
        deleteUserInfoDataByUId($uid, $redis);
        deleteUserInfoById($uid, $redis);
        deleteUserSetByUId($uid, $redis);

        $userInfo = UserInfoService::getUserInfoById($uid, $redis);
        $user = UserService::getUserById($uid, $redis);
        $userSet = UserSetService::getUserSetByUId($uid, $redis);
        return [
            'user' => $user ?? [],
            'user_info' => $userInfo ?? [],
            'user_set' => $userSet ?? []
        ];
    }
}