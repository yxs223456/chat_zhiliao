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
        $video = Db::name("video")->where("id", $id)->field("id,u_id")->find();
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
        Db::startTrans();
        try {
            Db::name("video_like")->where("video_id", $id)
                ->where("u_id", $user["id"])
                ->delete();
            Db::name("video_count")->where("video_id", $id)
                ->dec("like_count", 1)
                ->update();

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
     * @param $isFlush
     * @return array|mixed|null
     */
    public function cityList($startId, $pageSize, $city, $isFlush)
    {
        // 需要刷新更新缓存内容
        if ($isFlush) {
            return $this->cityListUpdate($pageSize, $city);
        }
        // 不需要刷新查询缓存数据
        return $this->cityListPage($startId, $pageSize, $city);
    }

    /**
     * 刷新最新动态缓存数据
     * 1. 判断是否需要更新缓存
     * 2. 需要更新缓存，删除所有缓存，返回新数据
     * 3. 不需要更新缓存，直接返回
     *
     * @param $pageSize int 分页大小
     * @param $city string 城市
     * @return array|mixed|null
     * @throws AppException
     */
    private function cityListUpdate($pageSize, $city)
    {
        // 获取最新动态ID
        $newVideoId = Db::name("dynamic")
            ->where("city", $city)
            ->where('is_delete', DbDataIsDeleteEnum::NO)
            ->order("id", "desc")
            ->value("id");
        // 为空，没有动态数据，返回缓存的第一页
        if (empty($newVideoId)) {
            return $this->cityListPage(0, $pageSize, $city);
        }

        // 不为空并且和缓存最新一条相同直接返回缓存数据
        $firstPage = $this->cityListPage(0, $pageSize, $city);
        $video = array_shift($firstPage);
        if (isset($video[0]["id"]) && $video[0]["id"] == $newVideoId) {
            return $this->cityListPage(0, $pageSize, $city);
        }

        // 不同删除城市小视频列表首页缓存
        deleteFirstCityVideoList($city, $pageSize, Redis::factory());
        return $this->cityListPage(0, $pageSize, $city);
    }

    /**
     * 获取最新的分页数据
     *
     * @param $startId int 起始查询ID
     * @param $pageSize int 分页大小
     * @param $city string 城市
     * @param int $retry 锁等待尝试次数
     * @return array|mixed|null
     * @throws AppException
     */
    private function cityListPage($startId, $pageSize, $city, $retry = 0)
    {
        // 读缓存
        $redis = Redis::factory();
        if ($data = getCityVideoList($city, $startId, $pageSize, $redis)) {
            return $data;
        }

        $lockKey = REDIS_KEY_PREFIX . "cityVideoListLock:" . $city . ":" . $startId . ":" . $pageSize;
        if ($redis->setnx($lockKey, 1)) {
            //设置锁过期时间防止失败后数据永修不更新
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
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
                $redis->del($lockKey);
                cacheCityVideoList($city, $startId, $pageSize, array_values($ret), $redis);
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

            cacheCityVideoList($city, $startId, $pageSize, array_values($ret), $redis);
            $redis->del($lockKey);
            return array_values($ret);
        } else {
            //设置锁过期时间防止失败后数据永修不更新
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME); // sleep 50 毫秒
            return $this->cityListPage($startId, $pageSize, $city, ++$retry);
        }
        throw AppException::factory(AppException::TRY_AGAIN_LATER);
    }
}