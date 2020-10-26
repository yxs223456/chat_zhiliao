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

class CharmService extends Base
{
    /**
     * 月排行
     *
     * @param $pageNum
     * @param $pageSize
     * @param $user
     * @return array
     */
    public function MonthList($pageNum, $pageSize, $user)
    {
        $redis = Redis::factory();
        $ret = [
            'user' => $this->getMonthUserInfo($user),
            'list' => []
        ];

        $start = ($pageNum - 1) * $pageSize;
        $data = getFemaleCharmSortSetMonth($start, $start + $pageSize - 1, $redis);
        if (empty($data)) {
            return $ret;
        }

        $ret["list"] = $this->getUserInfo($start,$data);
        return $ret;
    }

    /**
     * 日排行
     *
     * @param $pageNum
     * @param $pageSize
     * @param $user
     * @return array
     */
    public function weekList($pageNum, $pageSize, $user)
    {
        $redis = Redis::factory();
        $ret = [
            'user' => $this->getWeekUserInfo($user),
            'list' => []
        ];

        $start = ($pageNum - 1) * $pageSize;
        $data = getFemaleCharmSortSetWeek($start, $start + $pageSize - 1, $redis);

        if (empty($data)) {
            return $ret;
        }

        $ret["list"] = $this->getUserInfo($start,$data);
        return $ret;
    }

    /**
     * 日排行
     *
     * @param $pageNum
     * @param $pageSize
     * @param $user
     * @return array
     */
    public function dayList($pageNum, $pageSize, $user)
    {
        $redis = Redis::factory();
        $ret = [
            'user' => $this->getDayUserInfo($user),
            'list' => []
        ];

        $start = ($pageNum - 1) * $pageSize;
        $data = getFemaleCharmSortSetDay($start, $start + $pageSize - 1, $redis);
        if (empty($data)) {
            return $ret;
        }

        $ret["list"] = $this->getUserInfo($start,$data);
        return $ret;
    }

    /**
     * 获取当前登陆用户日魅力排名
     *
     * @param $user
     * @return array|mixed|null
     */
    private function getDayUserInfo($user)
    {
        $redis = Redis::factory();
        $userInfo = UserInfoService::getUserInfoById($user["id"]);
        $rank = (int)getFemaleCharmSortSetDayRank($user["id"], $redis);
        $charm = getFemaleCharmSortSetDayScore($user["id"], $redis);
        return [
            'u_id' => $user['id'],
            'nickname' => $userInfo['nickname'] ?? "",
            'avatar' => $userInfo['portrait'] ?? "",
            'charm' => (int)$charm,
            'rank' => empty($charm) ? 0 : $rank + 1,
        ];
    }

    /**
     * 获取当前登陆用户月魅力排名
     *
     * @param $user
     * @return array
     */
    private function getMonthUserInfo($user)
    {
        $redis = Redis::factory();
        $userInfo = UserInfoService::getUserInfoById($user["id"]);
        $rank = (int)getFemaleCharmSortSetMonthRank($user["id"], $redis);
        $charm = getFemaleCharmSortSetMonthScore($user["id"], $redis);
        return [
            'u_id' => $user['id'],
            'nickname' => $userInfo['nickname'] ?? "",
            'avatar' => $userInfo['portrait'] ?? "",
            'charm' => (int)$charm,
            'rank' => empty($charm) ? 0 : $rank + 1,
        ];
    }

    /**
     * 获取当前登陆用户月魅力排名
     *
     * @param $user
     * @return array
     */
    private function getWeekUserInfo($user)
    {
        $redis = Redis::factory();
        $userInfo = UserInfoService::getUserInfoById($user["id"]);
        $rank = (int)getFemaleCharmSortSetWeekRank($user["id"], $redis);
        $charm = getFemaleCharmSortSetWeekScore($user["id"], $redis);
        return [
            'u_id' => $user['id'],
            'nickname' => $userInfo['nickname'] ?? "",
            'avatar' => $userInfo['portrait'] ?? "",
            'charm' => (int)$charm,
            'rank' => empty($charm) ? 0 : $rank + 1,
        ];
    }

    /**
     * 获取排行用户数据
     *
     * @param $start int 开始
     * @param $data
     * @return array
     */
    private function getUserInfo($start, $data)
    {
        $userIds = array_keys($data);
        $userInfos = Db::name("user_info")
            ->field("u_id,nickname,portrait")
            ->whereIn("u_id", $userIds)
            ->select()->toArray();
        $userIdToData = array_combine(array_column($userInfos, "u_id"), $userInfos);

        $list = [];
        $start++; // index + 1
        foreach ($userIds as $value) {
            $tmp = [];
            $tmp["u_id"] = $userIdToData[$value]["u_id"] ?? 0;
            $tmp["nickname"] = $userIdToData[$value]["nickname"] ?? "";
            $tmp["portrait"] = $userIdToData[$value]["portrait"];
            $tmp['charm'] = $data[$value] ?? 0;
            $tmp['rank'] = $start++;
            $list[] = $tmp;
        }
        return $list;
    }

    /**
     * 获取上周贡献排行榜（前十）
     *
     * @param $uid
     * @param $currentUid
     * @throws AppException
     * @return array
     */
    public function lastWeekContributeList($uid, $currentUid)
    {
        $userInfo = UserService::getUserById($uid);
        // 用户不存在或者不是女生
        if (empty($userInfo) || $userInfo['sex'] != UserSexEnum::FEMALE) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        $lastWeekStart = getLastWeekStartDate();
        $lastWeekEnd = getLastWeekEndDate();

        $ret = [
            "user" => $this->getUserWeekContribute($uid, $currentUid, $lastWeekStart, $lastWeekEnd),
            "list" => $this->getWeekContributeList($uid, $lastWeekStart, $lastWeekEnd)
        ];
        return $ret;
    }

    /**
     * 获取本周贡献角逐榜（前十）
     *
     * @param $uid
     * @param $currentUid
     * @throws AppException
     * @return array
     */
    public function thisWeekContributeList($uid, $currentUid)
    {
        $userInfo = UserService::getUserById($uid);
        // 用户不存在或者不是女生
        if (empty($userInfo) || $userInfo['sex'] != UserSexEnum::FEMALE) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        list($startDate, $endDate) = getWeekStartAndEnd();
        $ret = [
            "guard" => GuardService::getGuard($uid),
            "user" => $this->getUserWeekContribute($uid, $currentUid, $startDate, $endDate),
            "list" => $this->getWeekContributeList($uid, $startDate, $endDate)
        ];
        return $ret;
    }

    /**
     * 获取当前用户的贡献
     *
     * @param $uid
     * @param $currentId
     * @param $startDate
     * @param $endDate
     * @return array
     */
    private function getUserWeekContribute($uid, $currentId, $startDate, $endDate)
    {
        $userInfo = UserInfoService::getUserInfoById($currentId);
        $ret = [
            'u_id' => $userInfo["u_id"],
            'avatar' => $userInfo["portrait"] ?? "",
            'charm' => 0,
            'rank' => 0,
            'user_number' => 0
        ];
        $user = UserService::getUserById($currentId);
        $ret['user_number'] = $user['user_number'] ?? 0;
        // 当前登陆用户是女性不计算贡献值
        if ($user["sex"] == UserSexEnum::FEMALE) {
            return $ret;
        }

        // 计算贡献值
        $charm = Db::name("guard_charm_log")
            ->where("u_id", '=', $uid)
            ->where("guard_u_id", '=', $currentId)
            ->where('sex_type', '=', InteractSexTypeEnum::FEMALE_TO_MALE)
            ->where('create_date', '>=', $startDate)
            ->where('create_date', '<=', $endDate)
            ->sum('amount');
        $ret['charm'] = $charm;
        return $ret;
    }

    /**
     * 获取上周贡献排行
     *
     * @param $uid int 女神用户ID
     * @param string $startDate
     * @param string $endDate
     * @param int $retry
     * @return array
     * @throws AppException
     */
    private function getWeekContributeList($uid, $startDate, $endDate, $retry = 0)
    {
        $redis = Redis::factory();
        if ($data = getPrettyWeekContributionList($uid, $startDate, $endDate, $redis)) {
            return $data["list"];
        }

        $ret = [
            "uid" => $uid,
            'list' => []
        ];

        $lockKey = REDIS_KEY_PREFIX . "PRETTY_LAST_WEEK_CONTRIBUTION_LIST_LOCK:" . $uid . ":" . $startDate . '-' . $endDate;
        if ($redis->setnx($lockKey, 1)) {
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);

            $userIdTotalAmount = Db::query("select guard_u_id,sum(amount) as total_amount from guard_charm_log where u_id = {$uid} and create_date >= '" . $startDate . "' and create_date <= '" . $endDate . "' and sex_type = " . InteractSexTypeEnum::FEMALE_TO_MALE . " group by guard_u_id order by total_amount desc limit 0,10");
            if (empty($userIdTotalAmount)) {
                cachePrettyWeekContributionList($uid,$startDate,$endDate, $ret, $redis);
                $redis->del($lockKey);
                return [];
            }

            $userIds = array_column($userIdTotalAmount, 'guard_u_id');
            $userIdToAmount = array_column($userIdTotalAmount, 'total_amount', 'guard_u_id');
            $userInfo = Db::name("user_info")->alias("ui")
                ->leftJoin("user u", "u.id = ui.u_id")
                ->field("ui.u_id,ui.nickname,ui.portrait,u.user_number")
                ->whereIn("u_id", $userIds)
                ->select()->toArray();

            $list = [];
            foreach ($userInfo as $item) {
                $tmp = [];
                $tmp["u_id"] = $item["u_id"];
                $tmp["nickname"] = $item["nickname"];
                $tmp["portrait"] = $item["portrait"];
                $tmp["user_number"] = $item["user_number"];
                $tmp['charm'] = $userIdToAmount[$item['u_id']] ?? 0;
                $list[] = $tmp;
            }

            $ret["list"] = $list;
            cachePrettyWeekContributionList($uid, $startDate, $endDate, $ret, $redis);
            $redis->del($lockKey);
            return $list;
        } else {
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME);
            return $this->getWeekContributeList($uid, $startDate, $endDate, ++$retry);
        }

        throw AppException::factory(AppException::TRY_AGAIN_LATER);
    }
}