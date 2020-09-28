<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/27
 * Time: 上午10:29
 */

namespace app\common\service;


use app\common\AppException;
use app\common\Constant;
use app\common\enum\FlowTypeEnum;
use app\common\enum\UserSexEnum;
use app\common\enum\WalletAddEnum;
use app\common\helper\Redis;
use think\facade\Db;

class EarnService extends Base
{

    /**
     * 获取男生收入总排行
     *
     * @param $pageNum
     * @param $pageSize
     * @param $user
     * @return array
     * @throws AppException
     */
    public function rank($pageNum, $pageSize, $user)
    {
        $userInfo = UserService::getUserById($user["id"]);
        // 用户不存在或者不是男生
        if (empty($userInfo) || $userInfo['sex'] != UserSexEnum::MALE) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        $ret = [
            'user' => $this->getSelf($user),
            'list' => $this->getAllList($pageNum, $pageSize),
        ];
        return $ret;
    }

    /**
     * 获取当前用户的排名信息
     *
     * @param $user
     * @return mixed
     */
    private function getSelf($user)
    {
        $ret = [
            "avatar" => "",
            "total_amount" => 0,
            "rank" => 0
        ];

        $userInfo = UserInfoService::getUserInfoById($user['id']);
        $ret["avatar"] = $userInfo["portrait"];
        $totalAmount = Db::name("guard_income")->where("u_id", $user['id'])->value("total_amount");
        if (empty($totalAmount)) {
            $totalAmount = 0;
        }
        $rank = Db::name("guard_income")->where("total_amount", ">", $totalAmount)->count("id");
        $ret["total_amount"] = $totalAmount;
        $ret["rank"] = $rank + 1;
        return $ret;
    }

    /**
     * 获取总收入列表
     *
     * @param $pageNum
     * @param $pageSize
     * @param int $retry
     * @return array|mixed|null
     * @throws AppException
     */
    private function getAllList($pageNum, $pageSize, $retry = 0)
    {
        $redis = Redis::factory();
        if ($data = getMaleAllEarnList($pageNum, $pageSize, $redis)) {
            return $data['data'];
        }

        $ret = [
            'pageNum' => $pageNum,
            'pageSize' => $pageSize,
            'data' => []
        ];

        $lockKey = REDIS_KEY_PREFIX . "MALE_ALL_EARN_LIST_LOCK:" . $pageNum . ":" . $pageSize;
        if ($redis->setnx($lockKey, 1)) {
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);

            $data = Db::name("guard_income")->alias("gi")
                ->leftJoin("user u", "gi.u_id = u.id")
                ->leftJoin("user_info ui", "gi.u_id = ui.u_id")
                ->field("gi.u_id,gi.total_amount,gi.guard_count,u.user_number,ui.portrait")
                ->order("gi.total_amount", "desc")
                ->limit(($pageNum - 1) * $pageSize, $pageSize)
                ->select()->toArray();
            if (empty($data)) {
                cacheMaleAllEarnList($pageNum, $pageSize, $ret, $redis);
                $redis->del($lockKey);
                return $ret;
            }

            $ret["data"] = $data;
            cacheMaleAllEarnList($pageNum, $pageSize, $ret, $redis);
            $redis->del($lockKey);
            return $data;
        } else {
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME);
            return $this->getAllList($pageNum, $pageSize, ++$retry);
        }
        throw AppException::factory(AppException::TRY_AGAIN_LATER);
    }

    /**
     * 收入周榜
     *
     * @param $pageNum
     * @param $pageSize
     * @param $user
     * @return array
     * @throws AppException
     */
    public function weekRank($pageNum, $pageSize, $user)
    {
        $userInfo = UserService::getUserById($user["id"]);
        // 用户不存在或者不是男生
        if (empty($userInfo) || $userInfo['sex'] != UserSexEnum::MALE) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        $ret = [
            'user' => $this->getWeekSelf($user),
            'list' => $this->getWeekList($pageNum, $pageSize),
        ];
        return $ret;
    }

    /**
     * 获取自己本周收入
     *
     * @param $user
     * @return array
     */
    private function getWeekSelf($user)
    {
        $ret = [
            "avatar" => "",
            "total_amount" => 0,
            "rank" => 0
        ];

        list($startDate,$endDate) = getWeekStartAndEnd();
        $userInfo = UserInfoService::getUserInfoById($user['id']);
        $ret["avatar"] = $userInfo["portrait"];
        $totalAmount = Db::name("user_wallet_flow")
            ->where("u_id","=", $user['id'])
            ->where("create_date",">=",$startDate)
            ->where("create_date","<=",$endDate)
            ->where("add_type","=",WalletAddEnum::ANGEL)
            ->sum("amount");
        if (empty($totalAmount)) {
            $totalAmount = 0;
        }
        $rank = Db::query("select sum(amount) as total_amount from user_wallet_flow where create_date >= '$startDate' and create_date <= '$endDate' and add_type = ".WalletAddEnum::ANGEL ." group by u_id having total_amount >" . $totalAmount);

        $ret["total_amount"] = $totalAmount;
        $ret["rank"] = count($rank) + 1;
        return $ret;
    }

    /**
     * 获取男生本周收入排行
     *
     * @param $pageNum
     * @param $pageSize
     * @param int $retry
     * @return array
     * @throws AppException
     */
    private function getWeekList($pageNum, $pageSize, $retry = 0)
    {
        $redis = Redis::factory();
        if ($data = getMaleWeekEarnList($pageNum, $pageSize, $redis)) {
            return $data["data"];
        }

        $lockKey = REDIS_KEY_PREFIX . "MALE_WEEK_EARN_LIST_LOCK:" . $pageSize . ":" . $pageNum;
        if ($redis->setnx($lockKey, 1)) {
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
            $ret = [
                "pageSize" => $pageSize,
                "pageNum" => $pageNum,
                "data" => []
            ];

            list($startDate, $endDate) = getWeekStartAndEnd();
            $data = Db::query("select u_id,SUM(amount) as total_amount from user_wallet_flow where create_date >= :startDate and create_date <= :endDate
and add_type = :addType group by u_id order by total_amount desc limit " . ($pageNum - 1) * $pageSize . "," . $pageSize, [
                "addType" => WalletAddEnum::ANGEL,
                "startDate" => $startDate,
                "endDate" => $endDate
            ]);

            if (empty($data)) {
                cacheMaleWeekEarnList($pageNum, $pageSize, $ret, $redis);
                $redis->del($lockKey);
                return [];
            }

            $userIds = array_column($data, 'u_id');

            // 查询用户本周守护人数
            $startDate = getLastWeekStartDate();
            $endDate = getLastWeekEndDate();
            $guard = Db::query("select guard_u_id,count(id) as counts from  guard_history where start_date >= '{$startDate}' and end_date <= '{$endDate}' and guard_u_id in :uid GROUP by guard_u_id", [
                'uid' => $userIds
            ]);
            $userIdToCounts = array_column($guard, 'counts', 'guard_u_id');

            // 获取用户展示信息
            $userInfo = Db::name("user_info")->alias("ui")
                ->leftJoin("user u", "u.id = ui.u_id")
                ->field("ui.portrait,u.user_number,u.id")
                ->whereIn("u.id", $userIds)
                ->select()->toArray();
            $userIdToInfo = array_combine(array_column($userInfo, 'id'), $userInfo);

            $allData = [];
            foreach ($data as $item) {
                $tmp = [];
                $tmp["id"] = $item["u_id"];
                $tmp["total_amount"] = $item["total_amount"];
                $tmp["user_number"] = $userIdToInfo[$item["u_id"]]["user_number"] ?? "";
                $tmp["portrait"] = $userIdToInfo[$item["u_id"]]["portrait"] ?? "";
                $tmp["counts"] = $userIdToCounts[$item["u_id"]] ?? 0;
                $allData[] = $tmp;
            }

            $ret["data"] = $allData;
            cacheMaleWeekEarnList($pageNum, $pageSize, $data, $redis);
            $redis->del($lockKey);
            return $allData;
        } else {
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME);
            return $this->getWeekList($pageNum, $pageSize, ++$retry);
        }

        throw AppException::factory(AppException::TRY_AGAIN_LATER);
    }
}