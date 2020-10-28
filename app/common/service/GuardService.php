<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/23
 * Time: 下午3:12
 */

namespace app\common\service;


use app\common\AppException;
use app\common\Constant;
use app\common\enum\InteractSexTypeEnum;
use app\common\enum\UserSexEnum;
use app\common\helper\Redis;
use think\facade\Db;

class GuardService extends Base
{

    /**
     * 获取用户守护人信息
     *
     * @param $userId
     * @return array
     */
    public static function getGuard1($userId)
    {
        $redis = Redis::factory();
        // 缓存有返回
        if (!empty($data = getUserGuard($userId, $redis))) {
            return $data;
        }

        $data = Db::name("guard_history")->alias("gh")
            ->leftJoin("user_info ui", "gh.guard_u_id = ui.u_id")
            ->leftJoin("user u","gh.guard_u_id = u.id")
            ->field("ui.*,u.sex")
            ->where("gh.u_id", $userId)
            ->where("start_date", getLastWeekStartDate())
            ->where("end_date", getLastWeekEndDate())
            ->find();
        if (empty($data)) {
            return [];
        }
        cacheUserGuard($userId, $data, $redis);
        return $data;
    }


    /**
     * 获取用户守护人信息没有时创建(不添加历史记录，因为有脚本添加，这里只获取)
     *
     * @param $userId
     * @return array
     */
    public static function getGuard($userId)
    {
        $redis = Redis::factory();
        // 缓存有返回
        if (!empty($data = getUserGuard($userId, $redis))) {
            return $data["data"];
        }

        // 没有缓存查历史记录
        $data = Db::name("guard_history")->alias("gh")
            ->leftJoin("user_info ui", "gh.guard_u_id = ui.u_id")
            ->leftJoin("user u", "gh.guard_u_id = u.id")
            ->field("ui.*,u.sex")
            ->where("gh.u_id", $userId)
            ->where("start_date", getLastWeekStartDate())
            ->where("end_date", getLastWeekEndDate())
            ->find();
        if (!empty($data)) {
            cacheUserGuard($userId, ["data" => $data], $redis);
            return $data;
        }

        // 历史记录不存在
        // 计算用户的守护者 (女神计算男生)
        $userInfo = UserService::getUserById($userId);
        // 男生直接删除记录
        if ($userInfo['sex'] == UserSexEnum::MALE) {
            cacheUserGuard($userId, ["data" => []], $redis);
            return [];
        }

        // 只查询21的数据
        $guard = Db::query("select guard_u_id,sum(amount) as s from guard_charm_log where u_id = :uid and sex_type = :sex 
and create_date >= :start_date and create_date <= :end_date 
GROUP BY guard_u_id having s >= :s ORDER by s desc limit 1",
            [
                'uid' => $userId,
                'sex' => InteractSexTypeEnum::FEMALE_TO_MALE,
                'start_date' => getLastWeekStartDate(),
                'end_date' => getLastWeekEndDate(),
                's' => Constant::GUARD_MIN_AMOUNT
            ]);

        // 没有达到守护条件直接返回
        if (empty($guard)) {
            cacheUserGuard($userId, ["data" => []], $redis);
            return [];
        }

        $data = Db::name("user_info")->alias("ui")
            ->leftJoin("user u", "ui.u_id = u.id")
            ->field("ui.*,u.sex")
            ->where("ui.u_id", $guard['guard_u_id'])
            ->find();

        cacheUserGuard($userId, ["data" => $data], $redis);
        return $data;
    }

    /**
     * 男生等待守护
     *
     * @param $user
     * @param $pageNum
     * @param $pageSize
     * @return array
     * @throws AppException
     */
    public function wait($user, $pageNum, $pageSize)
    {
        $userInfo = UserInfoService::getUserInfoById($user['id']);
        // 男生的等待守护
        if ($userInfo["sex"] != UserSexEnum::MALE) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        $ret = [
            "list" => []
        ];

        $redis = Redis::factory();
        $start = ($pageNum - 1) * $pageSize;
        $data = getMaleContributeSortSetThisWeek($user['id'], $start, $start + $pageSize - 1, $redis);

        if (empty($data)) {
            return $ret;
        }

        $userIds = array_keys($data);
        $prettyInfo = Db::name("user_info")->alias("ui")
            ->leftJoin("user_set us", "us.u_id = ui.u_id")
            ->field("ui.u_id,ui.portrait,ui.nickname,us.voice_chat_switch,us.voice_chat_price,
            us.video_chat_switch,us.video_chat_price,us.direct_message_free,us.direct_message_price")
            ->whereIn("ui.u_id", $userIds)
            ->select()->toArray();
        $prettyIdToInfo = array_combine(array_column($prettyInfo, 'u_id'), $prettyInfo);

        $list = [];
        foreach ($data as $uid => $score) {
            $tmp = [];
            $tmp["u_id"] = $prettyIdToInfo[$uid]["u_id"] ?? 0;
            $tmp["portrait"] = $prettyIdToInfo[$uid]["portrait"] ?? '';
            $tmp["nickname"] = $prettyIdToInfo[$uid]["nickname"] ?? '';
            $tmp["voice_chat_switch"] = $prettyIdToInfo[$uid]["voice_chat_switch"] ?? 0;
            $tmp["voice_chat_price"] = $prettyIdToInfo[$uid]["voice_chat_price"] ?? 0;
            $tmp["video_chat_switch"] = $prettyIdToInfo[$uid]["video_chat_switch"] ?? 0;
            $tmp["video_chat_price"] = $prettyIdToInfo[$uid]["video_chat_price"] ?? 0;
            $tmp["direct_message_free"] = $prettyIdToInfo[$uid]["direct_message_free"] ?? 0;
            $tmp["direct_message_price"] = $prettyIdToInfo[$uid]["direct_message_price"] ?? 0;
            $tmp["score"] = $score;
            $list[] = $tmp;
        }
        $ret["list"] = $list;
        return $ret;
    }

    /**
     * 获取当前登陆的男生正在守护的女神列表
     *
     * @param $user
     * @return array|mixed|null
     * @throws AppException
     */
    public function current($user)
    {
        $userInfo = UserInfoService::getUserInfoById($user['id']);
        // 男生的正在守护
        if ($userInfo["sex"] != UserSexEnum::MALE) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }
        $redis = Redis::factory();
        if ($data = getMaleCurrentGuardPretty($user["id"], $redis)) {
            return $data;
        }

        $ret = [
            "u_id" => $user['id'],
            "data" => []
        ];

        $data = Db::name("guard_history")->alias("gh")
            ->leftJoin("user_info ui", "gh.u_id = ui.u_id")
            ->leftJoin("user_set us", "gh.u_id = us.u_id")
            ->field("gh.charm_amount,gh.u_id,ui.portrait,ui.nickname,us.voice_chat_switch,us.voice_chat_price,
            us.video_chat_switch,us.video_chat_price,us.direct_message_free,us.direct_message_price")
            ->where("start_date", ">=", getLastWeekStartDate())
            ->where("end_date", "<=", getLastWeekEndDate())
            ->where("guard_u_id", "=", $user["id"])
            ->select()->toArray();
        if (empty($data)) {
            cacheMaleCurrentGuardPretty($user['id'], $ret, $redis);
            return $ret;
        }

        $ret["data"] = $data;
        cacheMaleCurrentGuardPretty($user['id'], $ret, $redis);
        return $ret;
    }

    /**
     * 男生最近三个月守护的女神列表
     *
     * @param $user
     * @return array|mixed|null
     * @throws AppException
     */
    public function recently($user)
    {
        $userInfo = UserInfoService::getUserInfoById($user['id']);
        // 男生的正在守护
        if ($userInfo["sex"] != UserSexEnum::MALE) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        $redis = Redis::factory();
        if ($data = getMaleRecentlyGuardPretty($user["id"], $redis)) {
            return $data;
        }

        $ret = [
            "u_id" => $user['id'],
            "data" => []
        ];

        $data = Db::name("guard_history")->alias("gh")
            ->leftJoin("user_info ui", "gh.u_id = ui.u_id")
            ->leftJoin("user_set us", "gh.u_id = us.u_id")
            ->field("gh.charm_amount,gh.u_id,ui.portrait,ui.nickname,us.voice_chat_switch,us.voice_chat_price,
            us.video_chat_switch,us.video_chat_price,us.direct_message_free,us.direct_message_price")
            ->where("start_date", ">=", date("Y-m-d", strtotime("-3 month")))
            ->where("guard_u_id", "=", $user["id"])
            ->select()->toArray();
        if (empty($data)) {
            cacheMaleRecentlyGuardPretty($user['id'], $ret, $redis);
            return $ret;
        }

        $ret["data"] = $data;
        cacheMaleRecentlyGuardPretty($user['id'], $ret, $redis);
        return $ret;
    }
}