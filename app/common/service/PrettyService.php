<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-12
 * Time: 10:15
 */

namespace app\common\service;

use app\common\AppException;
use app\common\enum\UserSexEnum;
use app\common\helper\Redis;
use think\facade\Db;

class PrettyService extends Base
{
    public function conditionInfo($user)
    {

    }

    /**
     * 更新女神月，周，日排行redis集合,更新女神周贡献，更新男神周贡献
     *
     * @param $pretty
     * @param $spend
     * @param $coin
     */
    public static function updatePrettySortList($pretty, $spend, $coin)
    {
        // 男的忽略
        if ($pretty["sex"] == UserSexEnum::MALE) {
            return;
        }
        $redis = Redis::factory();
        // 更新女的魅力月榜
        cacheFemaleCharmSortSetMonth($pretty['id'], $coin, $redis);
        // 更新女的魅力周榜
        cacheFemaleCharmSortSetWeek($pretty['id'], $coin, $redis);
        // 更新女的魅力日榜
        cacheFemaleCharmSortSetDay($pretty['id'], $coin, $redis);

        // 如果花钱的是男的
        if ($spend["sex"] == UserSexEnum::MALE) {
            // 更新女神贡献周排名
            cacheFemaleContributeSortSet($pretty["id"], $spend["id"], $coin, $redis);
            // 更新男的贡献周排名
            cacheMaleContributeSortSet($spend['id'], $pretty['id'], $coin, $redis);
        }
    }

    /**
     * 最近三个月守护的人
     *
     * @param $user
     * @return array
     * @throws AppException
     */
    public function recently($user)
    {
        $userInfo = UserInfoService::getUserInfoById($user['id']);
        // 只有女的才有最近守护
        if ($userInfo["sex"] != UserSexEnum::FEMALE) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }
        $ret = [
            "guard" => GuardService::getGuard($user["id"]),
            "guardList" => []
        ];

        $startDate = date("Y-m-d", strtotime("-3 month"));
        $data = Db::name("guard_history")->alias("gh")
            ->leftJoin("user_info ui", "ui.u_id = gh.guard_u_id")
            ->leftJoin("user_set us","us.u_id = gh.guard_u_id")
            ->field("gh.*,ui.portrait,ui.nickname,us.voice_chat_switch,us.voice_chat_price,
            us.video_chat_switch,us.video_chat_price,us.direct_message_free,us.direct_message_price")
            ->where("gh.u_id", "=", $user["id"])
            ->where("start_date", ">=", $startDate)
            ->order("gh.start_date", "desc")
            ->select()->toArray();

        if (empty($data)) {
            return $ret;
        }

        $ret["guardList"] = $data;
        return $ret;
    }

    /**
     * 女神本周等待被守护用户排名
     *
     * @param $user
     * @param $pageNum
     * @param $pageSize
     * @throws AppException
     * @return array
     */
    public function wait($user, $pageNum, $pageSize)
    {
        $userInfo = UserInfoService::getUserInfoById($user['id']);
        // 只有女的才有等待守护
        if ($userInfo["sex"] != UserSexEnum::FEMALE) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }
        $ret = [
            "guard" => GuardService::getGuard($user["id"]),
            "list" => []
        ];

        $redis = Redis::factory();
        $start = ($pageNum - 1) * $pageSize;
        $data = getFemaleContributeSortSetThisWeek($user['id'], $start, $start + $pageSize - 1, $redis);

        if (empty($data)) {
            return $ret;
        }

        $userIds = array_keys($data);
        $guardUserInfo = Db::name("user_info")->alias("ui")
            ->leftJoin("user_set us", "us.u_id = ui.u_id")
            ->field("ui.u_id,ui.portrait,ui.nickname,us.voice_chat_switch,us.voice_chat_price,
            us.video_chat_switch,us.video_chat_price,us.direct_message_free,us.direct_message_price")
            ->whereIn("ui.u_id", $userIds)
            ->select()->toArray();
        $guardIdToInfo = array_combine(array_column($guardUserInfo, 'u_id'), $guardUserInfo);

        $list = [];
        foreach ($data as $uid => $score) {
            $tmp = [];
            $tmp["u_id"] = $guardIdToInfo[$uid]["u_id"] ?? 0;
            $tmp["portrait"] = $guardIdToInfo[$uid]["portrait"] ?? "";
            $tmp["nickname"] = $guardIdToInfo[$uid]["nickname"] ?? "";
            $tmp["voice_chat_switch"] = $guardIdToInfo[$uid]["voice_chat_switch"] ?? 0;
            $tmp["voice_chat_price"] = $guardIdToInfo[$uid]["voice_chat_price"] ?? 0;
            $tmp["video_chat_switch"] = $guardIdToInfo[$uid]["video_chat_switch"] ?? 0;
            $tmp["video_chat_price"] = $guardIdToInfo[$uid]["video_chat_price"] ?? 0;
            $tmp["direct_message_free"] = $guardIdToInfo[$uid]["direct_message_free"] ?? 0;
            $tmp["direct_message_price"] = $guardIdToInfo[$uid]["direct_message_price"] ?? 0;
            $tmp["charm"] = $score;
            $list[] = $tmp;
        }
        $ret["list"] = $list;
        return $ret;
    }

    /**
     * 获取上周贡献排行榜（前十）
     *
     * @param $uid
     * @param $currentUid
     * @param $pageNum
     * @param $pageSize
     * @throws AppException
     * @return array
     */
    public function lastWeekContributeList($uid, $currentUid, $pageNum, $pageSize)
    {
        $userInfo = UserService::getUserById($uid);
        // 用户不存在或者不是女生
        if (empty($userInfo) || $userInfo['sex'] != UserSexEnum::FEMALE) {
            throw AppException::factory(AppException::USER_NOT_FEMALE);
        }

        $ret = [
            "user" => $this->getUserWeekContribute($uid, $currentUid,0),
            "list" => $this->getWeekContributeList($uid, $pageNum, $pageSize,0)
        ];
        return $ret;
    }

    /**
     * 获取本周贡献角逐榜（前十）
     *
     * @param $uid
     * @param $currentUid
     * @param $pageNum
     * @param $pageSize
     * @throws AppException
     * @return array
     */
    public function thisWeekContributeList($uid, $currentUid, $pageNum, $pageSize)
    {
        $userInfo = UserService::getUserById($uid);
        // 用户不存在或者不是女生
        if (empty($userInfo) || $userInfo['sex'] != UserSexEnum::FEMALE) {
            throw AppException::factory(AppException::USER_NOT_FEMALE);
        }

        $ret = [
            "guard" => GuardService::getGuard($uid),
            "user" => $this->getUserWeekContribute($uid, $currentUid),
            "list" => $this->getWeekContributeList($uid, $pageNum, $pageSize)
        ];
        return $ret;
    }

    /**
     * 获取当前用户对女神上周的贡献
     *
     * @param $uid
     * @param $currentId
     * @param $currentWeek int 1-本周，0-上周
     * @return array
     */
    private function getUserWeekContribute($uid, $currentId, $currentWeek = 1)
    {
        $userInfo = UserInfoService::getUserInfoById($currentId);
        $ret = [
            'u_id' => $userInfo["u_id"],
            'avatar' => $userInfo["portrait"] ?? "",
            'charm' => 0,
            'rank' => 0,
            'user_number' => 0,
            'nickname' => $userInfo["nickname"] ?? ""
        ];
        $user = UserService::getUserById($currentId);
        $ret['user_number'] = $user['user_number'] ?? 0;
        // 当前登陆用户是女性不计算贡献值
        if ($user["sex"] == UserSexEnum::FEMALE) {
            return $ret;
        }

        $redis = Redis::factory();
        if ($currentWeek) {
            $rank = (int)getFemaleContributeSortSetThisWeekRank($uid, $currentId, $redis);
            $charm = getFemaleContributeSortSetThisWeekScore($uid, $currentId, $redis);
        } else {
            $rank = (int)getFemaleContributeSortSetLastWeekRank($uid, $currentId, $redis);
            $charm = getFemaleContributeSortSetLastWeekScore($uid, $currentId, $redis);
        }

        // 计算贡献值
        $ret['charm'] = $rank;
        $ret['rank'] = empty($charm) ? 0 : $rank + 1;
        return $ret;
    }

    /**
     * 获取周贡献排行
     *
     * @param $uid int 女神用户ID
     * @param $pageNum int 分页
     * @param $pageSize int 页数
     * @param $currentWeek int 1-本周，0-上周
     * @return array
     * @throws AppException
     */
    private function getWeekContributeList($uid, $pageNum, $pageSize, $currentWeek = 1)
    {
        $redis = Redis::factory();
        $start = ($pageNum - 1) * $pageSize;
        if ($currentWeek) {
            $data = getFemaleContributeSortSetThisWeek($uid, $start, $start + $pageSize - 1, $redis);
        } else {
            $data = getFemaleContributeSortSetLastWeek($uid, $start, $start + $pageSize - 1, $redis);
        }
        if (empty($data)) {
            return [];
        }

        $userIds = array_keys($data);
        $userInfo = Db::name("user_info")->alias("ui")
            ->leftJoin("user u", "u.id = ui.u_id")
            ->field("ui.u_id,ui.nickname,ui.portrait,u.user_number")
            ->whereIn("u_id", $userIds)
            ->select()->toArray();

        $list = [];
        $userIdToInfo = array_combine(array_column($userInfo,"u_id"),$userInfo);
        foreach ($data as $key => $score) {
            $tmp = [];
            $tmp["u_id"] = $userIdToInfo[$key]["u_id"];
            $tmp["nickname"] = $userIdToInfo[$key]["nickname"];
            $tmp["portrait"] = $userIdToInfo[$key]["portrait"];
            $tmp["user_number"] = $userIdToInfo[$key]["user_number"];
            $tmp['charm'] = $score;
            $tmp['rank'] = ++$start;
            $list[] = $tmp;
        }

        return $list;
    }
}