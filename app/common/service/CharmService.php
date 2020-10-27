<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/23
 * Time: 下午3:12
 */

namespace app\common\service;


use app\common\AppException;
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
     * @param $time string (day,week,month)
     * @return array
     */
    public function rankList($pageNum, $pageSize, $user, $time = "day")
    {
        $redis = Redis::factory();
        $ret = [
            'user' => $this->getCurrentUserInfo($user,$time),
            'list' => []
        ];

        $start = ($pageNum - 1) * $pageSize;
        switch ($time){
            case "day":
                $data = getFemaleCharmSortSetDay($start, $start + $pageSize - 1, $redis);
                break;
            case "week":
                $data = getFemaleCharmSortSetWeek($start, $start + $pageSize - 1, $redis);
                break;
            case "month":
                $data = getFemaleCharmSortSetMonth($start, $start + $pageSize - 1, $redis);
                break;
        }

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
     * @param $time string (day,month,week)
     * @return array|mixed|null
     */
    private function getCurrentUserInfo($user, $time = "day")
    {
        $redis = Redis::factory();
        $userInfo = UserInfoService::getUserInfoById($user["id"]);
        switch ($time) {
            case "day":
                $rank = (int)getFemaleCharmSortSetDayRank($user["id"], $redis);
                $charm = getFemaleCharmSortSetDayScore($user["id"], $redis);
                break;
            case "month":
                $rank = (int)getFemaleCharmSortSetMonthRank($user["id"], $redis);
                $charm = getFemaleCharmSortSetMonthScore($user["id"], $redis);
                break;
            case "week":
                $rank = (int)getFemaleCharmSortSetWeekRank($user["id"], $redis);
                $charm = getFemaleCharmSortSetWeekScore($user["id"], $redis);
                break;
        }

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
            'user_number' => 0
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

        $start++; // index + 1
        $list = [];
        $userIdToInfo = array_combine(array_column($userInfo,"u_id"),$userInfo);
        foreach ($data as $key => $score) {
            $tmp = [];
            $tmp["u_id"] = $userIdToInfo[$key]["u_id"];
            $tmp["nickname"] = $userIdToInfo[$key]["nickname"];
            $tmp["portrait"] = $userIdToInfo[$key]["portrait"];
            $tmp["user_number"] = $userIdToInfo[$key]["user_number"];
            $tmp['charm'] = $score;
            $tmp['rank'] = $start++;
            $list[] = $tmp;
        }

        return $list;
    }
}