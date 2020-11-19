<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/27
 * Time: 上午10:29
 */

namespace app\common\service;

use app\common\AppException;
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
            "u_id" => $user["id"],
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
        $ret["nickname"] = $userInfo["nickname"] ?? "";
        return $ret;
    }

    /**
     * 获取总收入列表
     *
     * @param $pageNum
     * @param $pageSize
     * @return array|mixed|null
     * @throws AppException
     */
    private function getAllList($pageNum, $pageSize)
    {
        $data = Db::name("guard_income")->alias("gi")
            ->leftJoin("user u", "gi.u_id = u.id")
            ->leftJoin("user_info ui", "gi.u_id = ui.u_id")
            ->field("gi.u_id,gi.total_amount,gi.guard_count,u.user_number,ui.portrait,ui.nickname")
            ->order("gi.total_amount", "desc")
            ->limit(($pageNum - 1) * $pageSize, $pageSize)
            ->select()->toArray();
        if (empty($data)) {
            return [];
        }
        return $data;
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
        $redis = Redis::factory();
        $userInfo = UserInfoService::getUserInfoById($user["id"], $redis);
        $ret = [
            "id" => $user["id"] ?? 0,
            "avatar" => $userInfo["portrait"] ?? "",
            "total_amount" => 0,
            "rank" => 0,
            "nickname" => $userInfo["nickname"] ?? '',
        ];
        $score = (int)getMaleGuardEarnSortSetWeekScore($user["id"], $redis);
        $rank = (int)getMaleGuardEarnSortSetWeekRank($user["id"], $redis);

        $ret["total_amount"] = $score;
        $ret["rank"] = empty($score) ? 0 : $rank + 1;
        return $ret;
    }

    /**
     * 获取男生本周收入排行
     *
     * @param $pageNum
     * @param $pageSize
     * @return array
     * @throws AppException
     */
    private function getWeekList($pageNum, $pageSize)
    {
        $redis = Redis::factory();
        $start = ($pageNum - 1) * $pageSize;
        $data = getMaleGuardEarnSortSetWeek($start, $start + $pageSize - 1, $redis);

        if (empty($data)) {
            return [];
        }

        $userIds = array_keys($data);
        // 查询用户本周守护人数
        $startDate = getLastWeekStartDate();
        $endDate = getLastWeekEndDate();
        $userIdString = implode(",", $userIds);
        $guard = Db::query("select guard_u_id,count(guard_u_id) as counts from  guard_history where start_date >= '{$startDate}' and end_date <= '{$endDate}' and guard_u_id in ($userIdString) GROUP by guard_u_id");
        $userIdToCounts = array_column($guard, 'counts', 'guard_u_id');

        // 获取用户展示信息
        $userInfo = Db::name("user_info")->alias("ui")
            ->leftJoin("user u", "u.id = ui.u_id")
            ->field("ui.portrait,u.user_number,u.id,ui.nickname")
            ->whereIn("u.id", $userIds)
            ->select()->toArray();
        $userIdToInfo = array_combine(array_column($userInfo, 'id'), $userInfo);

        $allData = [];
        foreach ($data as $uid => $score) {
            $tmp = [];
            $tmp["id"] = $userIdToInfo[$uid]["id"];
            $tmp["total_amount"] = $score;
            $tmp["user_number"] = $userIdToInfo[$uid]["user_number"] ?? "";
            $tmp["portrait"] = $userIdToInfo[$uid]["portrait"] ?? "";
            $tmp["counts"] = $userIdToCounts[$uid] ?? 0;
            $allData[] = $tmp;
        }

        return $allData;
    }
}