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
     * @param $pageNum
     * @param $pageSize
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function recommendUserList($pageNum, $pageSize)
    {
        $redis = Redis::factory();
        $returnData["list"] = [];

        // 首页推荐用户
        $userIds = getUserListFromHomeRecommendList($pageNum, $pageSize, $redis);
        if (empty($userIds)) {
            return $returnData;
        }

        $users = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id=ui.u_id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->whereIn("ui.u_id", $userIds)
            ->field("u.id,u.user_number,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->select()->toArray();
        foreach ($users as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $users[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($users[$key]["photos"]);

            $signatures = json_decode($user["signatures"], true);
            $users[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($users[$key]["signatures"]);
        }

        $returnData["list"] = $users;
        return $returnData;
    }

    /**
     * 首页新人列表
     * @param $pageNum
     * @param $pageSize
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function newUserList($pageNum, $pageSize)
    {
        $redis = Redis::factory();
        $returnData["list"] = [];

        // 首页新人用户
        $userIds = getUserListFromHomeNewUserList($pageNum, $pageSize, $redis);
        if (empty($userIds)) {
            return $returnData;
        }

        $users = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id=ui.u_id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->whereIn("ui.u_id", $userIds)
            ->field("u.id,u.user_number,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->select()->toArray();
        foreach ($users as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $users[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($users[$key]["photos"]);

            $signatures = json_decode($user["signatures"], true);
            $users[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($users[$key]["signatures"]);
        }

        $returnData["list"] = $users;
        return $returnData;
    }

    /**
     * 首页对应地区用户列表
     * @param $site
     * @param $pageNum
     * @param $pageSize
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function siteUserList($site, $pageNum, $pageSize)
    {
        $redis = Redis::factory();
        $returnData["list"] = [];

        // 首页对应地区用户
        $userIds = getUserListFromHomeSiteList($pageNum, $pageSize, $site, $redis);
        if (empty($userIds)) {
            return $returnData;
        }

        $users = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id=ui.u_id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->whereIn("ui.u_id", $userIds)
            ->field("u.id,u.user_number,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->select()->toArray();
        foreach ($users as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $users[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($users[$key]["photos"]);

            $signatures = json_decode($user["signatures"], true);
            $users[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($users[$key]["signatures"]);
        }

        $returnData["list"] = $users;
        return $returnData;
    }
}