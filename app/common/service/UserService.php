<?php

namespace app\common\service;

use app\common\AppException;
use app\common\Constant;
use app\common\enum\DbDataIsDeleteEnum;
use app\common\enum\SmsSceneEnum;
use app\common\helper\Redis;
use app\common\helper\AliSms;
use app\common\helper\RongCloudApp;
use app\common\model\SmsLogModel;
use app\common\model\UserCommunityModel;
use app\common\model\UserModel;
use think\facade\Db;
use think\facade\Log;

class UserService extends Base
{

    const SMS_CODE_LOGIN = 1;
    const PASSWORD_LOGIN = 2;

    /**
     * 发送短信验证码
     *
     * @param $mobile   string  手机号
     * @param $areaCode int     区号
     * @param $scene    int     短信使用场景
     * @return array
     * @throws AppException
     */
    public function sendVerifyCode($mobile, $areaCode, $scene)
    {
        $code = mt_rand(100000, 999999);
        $param = array('code' => $code);

        // 调用接口使用的参数 国内不加区号，国外港澳台加区号
        if ($areaCode == 86) {
            $apiMobile = $mobile;
            $templateType = AliSms::TYPE_CHINA;
        } else {
            $apiMobile = $areaCode . $mobile;
            $templateType = AliSms::TYPE_INTERNATIONAL;
        }

        $re = AliSms::sendSms($apiMobile, $scene, $param, $response, $templateType);

        // 记录所有发送短信返回成功和失败
        $sms = new SmsLogModel();
        if (!$sms->sendCodeMS($areaCode, $mobile, $param, $response, $scene)) {
            Log::error("手机号" . $mobile . "短信log写入错误 ：" . json_encode($param) . json_encode($response));
        }

        // 保存redis
        $redis = Redis::factory();
        setSmsCode(['phone' => $apiMobile, 'code' => $code, 'scene' => $scene], $redis);

        // 触发限制抛异常
        if (isset($re["Code"]) && $re["Code"] != "OK") {
            throw AppException::factory(AppException::USER_SEND_SMS_LIMIT);
        }

        return $re;
    }

    public function codeLogin($areaCode, $mobile, $verifyCode, $userNumber)
    {
        $apiMobile = $areaCode == 86 ? $mobile : $areaCode . $mobile;

        $redis = Redis::factory();
        $cacheCode = getSmsCode($apiMobile, SmsSceneEnum::LOGIN, $redis);
        if ($cacheCode != $verifyCode) {
            throw AppException::factory(AppException::USER_VERIFY_CODE_ERR);
        }

        $userModel = new UserModel();
        $user = $userModel->findByMobilePhone($mobile);

        if ($user == null) {
            $returnData = $this->registerByPhone($areaCode, $mobile, $userNumber);
        } else {
            $returnData = $this->dologin($areaCode, $mobile, $verifyCode, UserService::SMS_CODE_LOGIN);
        }
        return $returnData;
    }

    /**
     * 用户注册
     *
     * @param $areaCode string 手机区号
     * @param $mobile string 手机号
     * @param $inviteUserNumber string 用户编号
     * @return mixed
     * @throws AppException
     */
    public function registerByPhone($areaCode, $mobile, $inviteUserNumber)
    {
        //判断用户是否存在
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
                "mobile_phone" => $mobile,
                "user_number" => $userNumber,
                "token" => $userToken,
            ];
            $newUser["id"] = Db::name("user")->insertGetId($newUser);

            // user_info 表
            $userInfoData = [
                "u_id" => $newUser["id"],
                "portrait" => $portrait,
                "nickname" => $nickname,
            ];
            Db::name("user_info")->insert($userInfoData);

            // 后续处理
            $this->registerAfter($newUser, $parent, $rongCloudToken);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        // 缓存用户登陆信息
        cacheUserInfoByToken($newUser, Redis::factory());
        return $this->formatUserData($user->uuid,1);
    }

    private function registerAfter($newUser, $parent, $rongCloudToken)
    {
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

        if ($parent) {
            // todo 有上级用户后续处理
        }
    }

    /**
     * 获取用户信息
     *
     * @param $uuid
     * @return array
     */
    public function getInfo($uuid)
    {
        return Db::name("user")->alias('b')
            ->leftJoin("user_wallet w", "b.uuid = w.user_uuid")
            ->leftJoin("user_diamond_coin_wallet d", "d.user_uuid=b.uuid")
            ->field("b.uuid,b.account,b.nickname,b.avatar,b.area_code,b.phone,b.email,b.status,b.user_level,
            b.invite_code,b.token,b.password,b.trade_password,b.line,
            w.balance,w.address,
            d.liquidity_diamond_coin,d.locked_diamond_coin")
            ->where("b.uuid", $uuid)
            ->where("b.is_delete", DbDataIsDeleteEnum::NO)
            ->find();
    }

    /**
     * 格式化用户登陆注册返回数据
     *
     * @param string $userUuid
     * @param int $firstLogin
     * @return array
     */
    public function formatUserData($userUuid, $firstLogin = 0)
    {
        $user = $this->getInfo($userUuid);
        $data = [
            "uuid" => $user["uuid"] ?? "",
            "account" => empty($user["account"]) ? "用户" : hideUserPhone($user['account']),
            "avatar" => $user["avatar"] ?? "",
            "area_code" => $user["area_code"] ?? "",
            "phone" => $user["phone"] ?? "",
            "email" => $user["email"] ?? "",
            "user_level" => $user["user_level"] ?? 0,
            "invite_code" => $user["invite_code"] ?? "",
            "token" => $user["token"] ?? "",
            "address" => $user["address"] ?? "",
            "first_login" => (int)$firstLogin
        ];
        return $data;
    }

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
}