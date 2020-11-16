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
use app\common\enum\VideoIsTransCodeEnum;
use app\common\helper\Redis;
use think\facade\Db;

class VideoService extends Base
{

    /**
     * 发布小视频
     *
     * @param $cover
     * @param $source
     * @param $user
     * @return int|string
     * @throws \Throwable
     */
    public function post($cover, $source, $user)
    {
        // TODO 验证规则
        Db::startTrans();
        try {
            // 添加动态
            $videoData = [
                'u_id' => $user["id"],
                'cover' => $cover,
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
        // 所有的都转码
        videoTransCodeProduce($id, Redis::factory());
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

        $isLike = Db::name("video_like")
            ->where("video_id", $id)->where("u_id", $user['id'])
            ->field("id")->find();
        if ($isLike) {
            throw AppException::factory(AppException::DYNAMIC_IS_LIKE);
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

        $isLike = Db::name("video_like")->where("video_id", $id)
            ->where("u_id", $user["id"])->field("id")->find();
        if (empty($isLike)) {
            throw AppException::factory(AppException::DYNAMIC_IS_CANCEL_LIKE);
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
     * @param $user
     * @return array|mixed|null
     */
    public function cityList($startId, $pageSize, $city, $user)
    {
        $ret = [
            "video" => [],
            "likeVideoUserIds" => [],
            "userSetData" => [],
            "userFollow" => []
        ];

        // 获取视频数据
        $videoQuery = Db::name("video")->alias("v")
            ->leftJoin("user_info ui", "v.u_id = ui.u_id")
            ->leftJoin("video_count vc", "vc.video_id = v.id")
            ->where("ui.city", $city)
            ->where("v.is_delete", DbDataIsDeleteEnum::NO)
            ->where("v.transcode_status", VideoIsTransCodeEnum::SUCCESS)
            ->field("v.id,v.u_id,v.source,v.cover,vc.like_count,ui.portrait,ui.city,ui.nickname")
            ->order("v.id", "desc");
        if (!empty($startId)) {
            $videoQuery = $videoQuery->where("v.id", "<", $startId);
        }
        $videos = $videoQuery->limit($pageSize)->select()->toArray();

        if (empty($videos)) {
            return array_values($ret);
        }
        $ret["video"] = $videos;

        // 获取小视频点赞的用户ID
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

        // 获取小视频用户配置
        $uids = array_column($videos, 'u_id');
        $userSet = Db::name("user_set")->whereIn("u_id", $uids)
            ->select()->toArray();
        $userSetData = array_combine(array_column($userSet, 'u_id'), $userSet);
        $ret["userSetData"] = $userSetData;

        // 查看当前用户关注动态用户的记录
        $userFollow = Db::name("user_follow")->where("u_id", $user["id"])
            ->whereIn("follow_u_id", $uids)
            ->column("follow_u_id");
        $ret["userFollow"] = $userFollow;

        return array_values($ret);
    }

    /**
     * 获取城市小视频列表
     *
     * @param $startId
     * @param $pageSize
     * @param $user
     * @return array|mixed|null
     */
    public function recommendList($startId, $pageSize, $user)
    {
        $ret = [
            "video" => [],
            "likeVideoUserIds" => [],
            "userSetData" => [],
            "userFollow" => []
        ];

        // 获取动态数据
        $videoQuery = Db::name("video")->alias("v")
            ->leftJoin("user_info ui", "v.u_id = ui.u_id")
            ->leftJoin("video_count vc", "vc.video_id = v.id")
            ->where("v.is_delete", DbDataIsDeleteEnum::NO)
            ->where("v.transcode_status",VideoIsTransCodeEnum::SUCCESS)
            ->field("v.id,v.u_id,v.source,v.cover,vc.like_count,ui.portrait,ui.city,ui.nickname")
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

        // 获取小视频用户配置
        $uids = array_column($videos, 'u_id');
        $userSet = Db::name("user_set")->whereIn("u_id", $uids)
            ->select()->toArray();
        $userSetData = array_combine(array_column($userSet, 'u_id'), $userSet);
        $ret["userSetData"] = $userSetData;

        // 查看当前用户关注动态用户的记录
        $userFollow = Db::name("user_follow")->where("u_id", $user["id"])
            ->whereIn("follow_u_id", $uids)
            ->column("follow_u_id");
        $ret["userFollow"] = $userFollow;

        return array_values($ret);
    }

    /**
     * 获取个人小视频列表
     *
     * @param $startId
     * @param $pageSize
     * @param $requestUserId
     * @param $currentUserId
     * @return array
     */
    public function personal($startId, $pageSize, $requestUserId, $currentUserId)
    {
        $ret = [
            "video" => [],
            "currentUserLikeVideos" => [],
            "userSetData" => [],
            "userFollow" => 0
        ];

        // 获取请求用户动态数据
        $videoQuery = Db::name("video")->alias("v")
            ->leftJoin("user_info ui", "v.u_id = ui.u_id")
            ->leftJoin("video_count vc", "vc.video_id = v.id")
            ->where("v.is_delete", DbDataIsDeleteEnum::NO)
            ->where("v.u_id", $requestUserId)
            ->field("v.id,v.u_id,v.cover,v.source,v.transcode_status,vc.like_count,ui.portrait,ui.city,ui.nickname")
            ->order("v.id", "desc");
        // 不是查询自己的列表只展示转码成功的
        if ($requestUserId != $currentUserId) {
            $videoQuery = $videoQuery->where("v.transcode_status", VideoIsTransCodeEnum::SUCCESS);
        }
        if (!empty($startId)) {
            $videoQuery = $videoQuery->where("v.id", "<", $startId);
        }
        $videos = $videoQuery->limit($pageSize)->select()->toArray();

        if (empty($videos)) {
            return array_values($ret);
        }
        $ret["video"] = $videos;
        // 获取当前用户点赞的小视频ID
        $currentUserLikeVideos = Db::name("video_like")
            ->whereIn("video_id", array_column($videos, 'id'))
            ->where("u_id", $currentUserId)
            ->column("video_id");
        $ret["currentUserLikeVideos"] = $currentUserLikeVideos;

        // 获取小视频用户配置
        $userSet = Db::name("user_set")->where("u_id", $requestUserId)
            ->find();
        $ret["userSetData"] = $userSet;

        // 查看当前用户关注动态用户的记录
        $userFollow = Db::name("user_follow")->where("u_id", $currentUserId)
            ->where("follow_u_id", $requestUserId)->find();
        $ret["userFollow"] = empty($userFollow) ? 0 : 1;

        return array_values($ret);
    }
}