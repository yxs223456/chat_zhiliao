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
use app\common\enum\UserSexTypeEnum;
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
            ->where("sex_type", "=", UserSexTypeEnum::FEMALE_TO_MALE)
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
}