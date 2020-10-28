<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/23
 * Time: 下午3:12
 */

namespace app\common\service;


use app\common\helper\Redis;
use think\facade\Db;

class CharmService extends Base
{
    /**
     * 总排行
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
            'charm' => (string)bcdiv($charm,100,0),
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
        foreach ($userIds as $value) {
            $tmp = [];
            $tmp["u_id"] = $userIdToData[$value]["u_id"] ?? 0;
            $tmp["nickname"] = $userIdToData[$value]["nickname"] ?? "";
            $tmp["portrait"] = $userIdToData[$value]["portrait"];
            $tmp['charm'] = $data[$value] ?? 0;
            $tmp['rank'] = ++$start;
            $list[] = $tmp;
        }
        return $list;
    }
}