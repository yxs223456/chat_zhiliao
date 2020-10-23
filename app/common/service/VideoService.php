<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/23
 * Time: 下午3:12
 */

namespace app\common\service;

use app\common\AppException;
use app\common\enum\DbDataIsDeleteEnum;
use app\common\helper\Redis;
use think\facade\Db;

class VideoService extends Base
{

    /**
     * 发布小视频
     *
     * @param $source
     * @param $user
     * @return int|string
     * @throws \Throwable
     */
    public function post($source, $user)
    {
        // TODO 验证规则
        Db::startTrans();
        try {
            // 添加动态
            $videoData = [
                'u_id' => $user["id"],
                'source' => $source,
                'create_time' => date("Y-m-d H:i:s")
            ];
            $id = Db::name("video")->insertGetId($videoData);
            $videoCountData = [
                'u_id' => $user["id"],
                'video_id' => $id,
                'create_time' => date("Y-m-d H:i:s")
            ];
            Db::name("video_count")->insert($videoCountData);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return $id;
    }

    /**
     * 删除小视频
     *
     * @param $id
     * @param $user
     * @return int
     * @throws AppException
     */
    public function delete($id, $user)
    {
        $video = Db::name("video")->where("id", $id)->find();
        if (empty($video)) {
            throw AppException::factory(AppException::VIDEO_NOT_EXISTS);
        }

        if ($video["u_id"] != $user["id"]) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        // 删除用户首页缓存
        deleteUserIndexDataByUId($user["id"], Redis::factory());
        return Db::name("video")->where("id", $id)->update(["is_delete" => DbDataIsDeleteEnum::YES]);
    }

    /**
     * 点赞小视频
     *
     * @param $id
     * @param $user
     * @throws \Throwable
     */
    public function like($id, $user)
    {
        $video = Db::name("video")
            ->where("id", $id)
            ->where("is_delete",DbDataIsDeleteEnum::NO)
            ->field("id,u_id")->find();
        if (empty($video)) {
            throw AppException::factory(AppException::VIDEO_NOT_EXISTS);
        }
        $uid = $video["u_id"];
        if (BlackListService::inUserBlackList($user['id'], $uid)) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 添加访问记录队列
        VisitorService::addVisitorLog($uid, $user["id"]);

        Db::startTrans();
        try {
            Db::name("video_like")->insertGetId([
                "video_id" => $id,
                'u_id' => $user['id'],
            ]);
            Db::name("video_count")->where("video_id", $id)
                ->inc("like_count", 1)
                ->update();

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 小视频取消点赞
     *
     * @param $id
     * @param $user
     * @throws \Throwable
     */
    public function unlike($id, $user)
    {
        $video = Db::name("video")
            ->where("id", $id)
            ->where("is_delete", DbDataIsDeleteEnum::NO)
            ->field("id,u_id")
            ->find();
        if (empty($video)) {
            throw AppException::factory(AppException::VIDEO_NOT_EXISTS);
        }
        Db::startTrans();
        try {
            $flag = Db::name("video_like")->where("video_id", $id)
                ->where("u_id", $user["id"])
                ->delete();
            if ($flag) { // 删除成功了再减少
                Db::name("video_count")->where("video_id", $id)
                    ->dec("like_count", 1)
                    ->update();
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 获取城市小视频列表
     *
     * @param $startId
     * @param $pageSize
     * @param $city
     * @return array|mixed|null
     */
    public function cityList($startId, $pageSize, $city)
    {
        $ret = [
            "video" => [],
            "likeVideoUserIds" => []
        ];

        // 获取动态数据
        $videoQuery = Db::name("video")->alias("v")
            ->leftJoin("user_info ui", "v.u_id = ui.u_id")
            ->leftJoin("video_count vc", "vc.video_id = v.id")
            ->whereLike("ui.city", $city . "%")
            ->where("v.is_delete", DbDataIsDeleteEnum::NO)
            ->field("v.id,v.u_id,v.source,vc.like_count,ui.portrait,ui.city")
            ->order("v.id", "desc");
        if (!empty($startId)) {
            $videoQuery = $videoQuery->where("v.id", "<", $startId);
        }
        $videos = $videoQuery->limit($pageSize)->select()->toArray();

        if (empty($videos)) {
            return array_values($ret);
        }
        $ret["video"] = $videos;

        // 获取动态点赞的用户ID
        $videoIdToUserIds = Db::name("video_like")
            ->whereIn("video_id", array_column($videos, 'id'))
            ->field("video_id,u_id")
            ->select()->toArray();
        $likeVideoUserIds = [];
        // 点赞用户ID根据动态ID分组
        array_map(function ($item) use (&$likeVideoUserIds) {
            $likeVideoUserIds[$item['video_id']][] = $item["u_id"];
        }, $videoIdToUserIds);
        $ret["likeVideoUserIds"] = $likeVideoUserIds;

        return array_values($ret);
    }

    /**
     * 获取城市小视频列表
     *
     * @param $startId
     * @param $pageSize
     * @return array|mixed|null
     */
    public function recommendList($startId, $pageSize)
    {
        $ret = [
            "video" => [],
            "likeVideoUserIds" => []
        ];

        // 获取动态数据
        $videoQuery = Db::name("video")->alias("v")
            ->leftJoin("user_info ui", "v.u_id = ui.u_id")
            ->leftJoin("video_count vc", "vc.video_id = v.id")
            ->where("v.is_delete", DbDataIsDeleteEnum::NO)
            ->field("v.id,v.u_id,v.source,vc.like_count,ui.portrait,ui.city")
            ->order("v.id", "desc");
        if (!empty($startId)) {
            $videoQuery = $videoQuery->where("v.id", "<", $startId);
        }
        $videos = $videoQuery->limit($pageSize)->select()->toArray();

        if (empty($videos)) {
            return array_values($ret);
        }
        $ret["video"] = $videos;

        // 获取动态点赞的用户ID
        $videoIdToUserIds = Db::name("video_like")
            ->whereIn("video_id", array_column($videos, 'id'))
            ->field("video_id,u_id")
            ->select()->toArray();
        $likeVideoUserIds = [];
        // 点赞用户ID根据动态ID分组
        array_map(function ($item) use (&$likeVideoUserIds) {
            $likeVideoUserIds[$item['video_id']][] = $item["u_id"];
        }, $videoIdToUserIds);
        $ret["likeVideoUserIds"] = $likeVideoUserIds;

        return array_values($ret);
    }
}