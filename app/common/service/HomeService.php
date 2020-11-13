<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 16:32
 */

namespace app\common\service;

use app\common\helper\Redis;
use think\facade\Db;

class HomeService extends Base
{
    /**
     * 首页推荐列表
     * @param $sex
     * @param $price
     * @param $pageNum
     * @param $pageSize
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function recommendUserList($sex, $price, $pageNum, $pageSize)
    {
        $redis = Redis::factory();
        $returnData["list"] = [];

        // 首页推荐用户
        $condition = $sex . ":" . $price;
        $users = getUserListFromHomeRecommendList2($condition, $pageNum, $pageSize, $redis);
        $returnData["list"] = $users;
        return $returnData;
        $userIds = getUserListFromHomeRecommendList($condition, $pageNum, $pageSize, $redis);
        if (empty($userIds)) {
            return $returnData;
        }

        $users = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id=ui.u_id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->whereIn("ui.u_id", $userIds)
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->select()->toArray();
        foreach ($users as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $users[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($users[$key]["photos"]);

            $users[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $users[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($users[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $users[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($users[$key]["total_score"]);
            unset($users[$key]["total_users"]);
        }

        $returnData["list"] = $users;
        return $returnData;
    }

    /**
     * 首页新人列表
     * @param $sex
     * @param $price
     * @param $pageNum
     * @param $pageSize
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function newUserList($sex, $price, $pageNum, $pageSize)
    {

        $redis = Redis::factory();
        $returnData["list"] = [];

        // 首页新人用户
        $condition = $sex . ":" . $price;
        $users = getUserListFromHomeNewUserList2($condition, $pageNum, $pageSize, $redis);
        $returnData["list"] = $users;
        return $returnData;
        $userIds = getUserListFromHomeNewUserList($condition, $pageNum, $pageSize, $redis);
        if (empty($userIds)) {
            return $returnData;
        }

        $users = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id=ui.u_id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->whereIn("ui.u_id", $userIds)
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->select()->toArray();
        foreach ($users as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $users[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($users[$key]["photos"]);

            $users[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $users[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($users[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $users[$key]["score"] = ScoreService::getScoreByScore($score);;
            unset($users[$key]["total_score"]);
            unset($users[$key]["total_users"]);
        }

        $returnData["list"] = $users;
        return $returnData;
    }

    /**
     * 首页对应地区用户列表
     * @param $site
     * @param $sex
     * @param $price
     * @param $pageNum
     * @param $pageSize
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function siteUserList($site, $sex, $price, $pageNum, $pageSize)
    {
        $redis = Redis::factory();
        $returnData["list"] = [];

        // 首页对应地区用户
        $condition = $sex . ":" . $price;
        $userIds = getUserListFromHomeSiteList($site, $condition, $pageNum, $pageSize, $redis);
        if (empty($userIds)) {
            return $returnData;
        }

        $users = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id=ui.u_id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->whereIn("ui.u_id", $userIds)
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->select()->toArray();
        foreach ($users as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $users[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($users[$key]["photos"]);

            $users[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $users[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($users[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $users[$key]["score"] = ScoreService::getScoreByScore($score);;
            unset($users[$key]["total_score"]);
            unset($users[$key]["total_users"]);
        }

        $returnData["list"] = $users;
        return $returnData;
    }

    /**
     * 用户符合的首页搜索条件
     * @param $userSex
     * @param $price
     * @return array
     */
    public static function getHomeConditionByUser($userSex, $price)
    {
        $priceCondition = self::getHomePriceCondition($price);
        $conditions = [
            "0:0",
            $userSex.":0",
        ];
        if ($priceCondition) {
            $conditions[] = "0:".$priceCondition;
            $conditions[] = $userSex.":".$priceCondition;
        }
        return $conditions;
    }

    /**
     * 根据价格判断符合的首页价格条件
     * @param $price
     * @return string|null
     */
    public static function getHomePriceCondition($price)
    {
        if ($price < 200) {
            return "100_200";
        } elseif ($price < 350) {
            return "200_350";
        } elseif ($price >= 350) {
            return "350_500";
        } else {
            return null;
        }
    }

    /**
     * 首页全部价格类搜索条件
     * @return array
     */
    public static function getAllHomePriceCondition()
    {
        return [
            "0",
            "100_200",
            "200_350",
            "350_500",
        ];
    }
}