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
     * 获取魅力值排行
     *
     * @param $startTime
     * @param $endTime
     * @param $pageNum
     * @param $pageSize
     * @param $user
     * @return array
     */
    public function rankList($startTime, $endTime, $pageNum, $pageSize, $user)
    {
        $ret = [
            'user' => $this->getUserInfo($startTime, $endTime, $user),
            'list' => []
        ];

        // 超过限制查询数量直接返回
        if ($pageNum * $pageSize > Constant::CHARM_LIST_MAX_NUM) {
            return $ret;
        }

        $ret['list'] = $this->getList($pageNum, $pageSize, $startTime, $endTime);
        return $ret;
    }

    /**
     * 获取当前登陆用户时间段内的魅力排名
     *
     * @param $startTime
     * @param $endTime
     * @param $user
     * @return array|mixed|null
     */
    private function getUserInfo($startTime, $endTime, $user)
    {
        $redis = Redis::factory();
        if ($data = getUserFemaleCharmInfo($user['id'], $startTime, $endTime, $redis)) {
            return $data;
        }

        $userInfo = UserInfoService::getUserInfoById($user["id"]);
        $ret = [
            'u_id' => $user['id'],
            'nickname' => $userInfo['nickname'] ?? "",
            'avatar' => $userInfo['portrait'] ?? "",
            'charm' => 0,
            'rank' => 0,
        ];

        $charm = Db::name("guard_charm_log")
            ->where("create_date", ">=", $startTime)
            ->where("create_date", "<=", $endTime)
            ->where("sex_type", "=", InteractSexTypeEnum::FEMALE_TO_MALE)
            ->where('u_id', '=', $user['id'])
            ->sum('amount');
        if (empty($charm)) {
            cacheUserFemaleCharmInfo($user["id"], $startTime, $endTime, $ret, $redis);
            return $ret;
        }
        $ret['charm'] = $charm;

        $preRank = Db::query("select sum(amount) as total_amount from guard_charm_log where create_date >= '$startTime' and create_date <= '$endTime' and sex_type = 21 group by u_id having total_amount >$charm");
        $rank = count($preRank) + 1;
        if ($rank  < Constant::CHARM_LIST_MAX_NUM) {
            $ret['rank'] = $rank;
        }
        cacheUserFemaleCharmInfo($user['id'], $startTime, $endTime, $ret, $redis);
        return $ret;
    }

    /**
     * 获取月分页数据
     *
     * @param $pageNum
     * @param $pageSize
     * @param $startTime
     * @param $endTime
     * @param int $retry
     * @return array
     * @throws AppException
     */
    private function getList($pageNum, $pageSize, $startTime, $endTime, $retry = 0)
    {
        $redis = Redis::factory();
        if ($data = getUserFemaleCharmList($pageNum, $pageSize, $startTime, $endTime, $redis)) {
            return $data['data'];
        }

        $lockKey = REDIS_KEY_PREFIX . "USER_FEMALE_CHARM_LIST_LOCK";
        if ($redis->setnx($lockKey, 1)) {
            // 设置过期时间
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
            $ret = [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'page_num' => $pageNum,
                'page_size' => $pageSize,
                'data' => []
            ];
            $data = Db::query("select u_id,sum(amount) as total_amount from guard_charm_log where create_date >= '{$startTime}' and create_date <= '{$endTime}' and sex_type = 21 group by u_id ORDER by total_amount DESC limit " . ($pageNum - 1) * $pageSize . "," . $pageSize);
            if (empty($data)) {
                cacheUserFemaleCharmList($pageNum, $pageSize, $startTime, $endTime, $ret, $redis);
                $redis->del($lockKey);
                return [];
            }

            $userIds = array_column($data, 'u_id');
            $userIdToAmount = array_column($data, 'total_amount', 'u_id');
            $userInfos = Db::name("user_info")
                ->field("u_id,nickname,portrait")
                ->whereIn("u_id", $userIds)
                ->select()->toArray();

            $list = [];
            foreach ($userInfos as $item) {
                $tmp = [];
                $tmp["u_id"] = $item["u_id"];
                $tmp["nickname"] = $item["nickname"];
                $tmp["portrait"] = $item["portrait"];
                $tmp['charm'] = $userIdToAmount[$item['u_id']] ?? 0;
                $list[] = $tmp;
            }
            $ret['data'] = $list;
            cacheUserFemaleCharmList($pageNum, $pageSize, $startTime, $endTime, $ret, $redis);
            $redis->del($lockKey);
            return $list;
        } else {
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME);
            return $this->getList($pageNum, $pageSize, $startTime, $endTime, ++$retry);
        }
        throw AppException::factory(AppException::TRY_AGAIN_LATER);
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