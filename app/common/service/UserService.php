<?php

namespace app\common\service;

use app\common\AppException;
use app\common\enum\DbDataIsDeleteEnum;
use app\common\helper\Redis;
use app\common\helper\AliSms;
use app\common\model\SmsLogModel;
use app\common\model\UserModel;
use think\Exception;
use think\facade\Db;
use think\facade\Log;


class UserService extends Base
{

    const SMS_CODE_LOGIN = 1;
    const PASSWORD_LOGIN = 2;

    /**
     * 发送短信验证码
     *
     * @param $mobile
     * @param $areaCode int 区号
     * @return array
     * @throws AppException
     */
    public function sendSms($mobile, $areaCode = 86)
    {
        $code = rand(100000, 999999);
        $param = array('code' => $code);

        // 调用接口使用的参数 国内不加区号，国外港澳台加区号
        $apiMobile = $mobile;
        $templateType = AliSms::TYPE_CHINA;
        if ($areaCode != 86) {
            $apiMobile = $areaCode . $mobile;
            $templateType = AliSms::TYPE_INTERNATIONAL;
        }

        $re = AliSms::sendSms($apiMobile, $param, $response, $templateType);

        // 记录所有发送短信返回成功和失败
        $sms = new SmsLogModel();
        if (!$sms->sendCodeMS($areaCode, $mobile, $param, $response)) {
            Log::error("手机号" . $mobile . "短信log写入错误 ：" . json_encode($param) . json_encode($response));
        }

        // 保存redis
        $redis = Redis::factory();
        setSmsCode(array('phone' => $apiMobile, 'code' => $code), $redis);

        // 触发限制抛异常
        if (isset($re["Code"]) && $re["Code"] != "OK") {
            throw AppException::factory(AppException::USER_MESSAGE);
        }

        return $re;
    }

    /**
     * 用户注册
     *
     * @param $areaCode string 手机区号
     * @param $mobile string 手机号
     * @param $inviteCode string 邀请码
     * @return mixed
     * @throws AppException
     */
    public function register($areaCode, $mobile, $inviteCode)
    {
        try {
            Db::startTrans();

            $user = new UserModel();
            $userUuid = getRandomString(8);
            $user->uuid = $userUuid;
            $user->account = $mobile;
            $user->area_code = $areaCode;
            $user->phone = $mobile;
            $user->last_time = date('Y-m-d H:i:s');
            $user->last_ip = request()->ip();
            $user->token = getRandomString();
            $user->invite_code = createInviteCode(10);
            $user->create_date = date("Y-m-d");
            $user->last_login_time = time();
            $user->last_active_time = time();

            if (!empty($inviteCode)) {
                $pUser = Db::name("user_base")->where("invite_code", $inviteCode)->find();
                if (empty($pUser)) {
                    throw AppException::factory(AppException::USER_INVITE_CODE_NOT_EXISTS);
                }

                $user->p_uuid = $pUser["uuid"];
                $user->parent_uuid_path = $pUser["parent_uuid_path"] . ($pUser["parent_uuid_path"]?",":"") . $pUser["uuid"];

                //上级统计表添加一条数据
                Db::name("tmp_user_add_parent_callback")->insert([
                    "user_uuid" => $userUuid,
                    "create_time" => time(),
                    "update_time" => time(),
                ]);

            }

            // 添加user_base
            if (!$user->save()) {
                throw new AppException(json_encode($user->toArray()));
            }

            // 添加user_descendant
            $data = array();
            $data['user_uuid'] = $user->uuid;
            $data["create_time"] = time();
            $data['update_time'] = time();
            $ret = Db::name("user_descendant")->insertGetId($data);
            if (empty($ret)) {
                throw new AppException("db error user_descendant 创建失败");
            }

            // 创建钻石币钱包
            $walletCoin = array();
            $walletCoin["user_uuid"] = $user->uuid;
            $walletCoin["create_time"] = time();
            $walletCoin["update_time"] = time();
            $ret = Db::name("user_diamond_coin_wallet")->insertGetId($walletCoin);
            if (empty($ret)) {
                throw new AppException("db error 钻石币钱包创建失败");
            }

            Db::commit();
        } catch (Exception $e) {
            Log::error("[] : " . $e->getMessage());
            throw AppException::factory(AppException::USER_REGISTER_ERR);
        } catch (\Throwable $e) {
            Log::error("[用户注册失败] ： " . $e->getMessage());
            Db::rollback();
            throw AppException::factory(AppException::USER_REGISTER_ERR);
        }

        // 缓存用户登陆信息
        cacheUserInfoByToken($user->toArray(), Redis::factory());
        return $this->formatUserData($user->uuid,1);
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
}