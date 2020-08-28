<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午2:09
 */

namespace app\common\service;


use app\common\AppException;
use app\common\Constant;
use app\common\enum\DbDataIsDeleteEnum;
use app\common\enum\DynamicIsReportEnum;
use app\common\helper\Redis;
use think\facade\Db;

class DynamicService extends Base
{

    /**
     * 发布动态
     *
     * @param $content
     * @param $source
     * @param $user
     * @return int|string
     * @throws \Throwable
     */
    public function post($content, $source, $user)
    {
        Db::startTrans();
        try {
            // 添加动态
            $dynamicData = [
                'u_id' => $user["id"],
                'content' => $content,
                'source' => json_encode($source),
                'create_time' => date("Y-m-d H:i:s")
            ];
            $id = Db::name("dynamic")->insertGetId($dynamicData);
            $dynamicCountData = [
                'u_id' => $user["id"],
                'user_dynamic_id' => $id,
                'create_time' => date("Y-m-d H:i:s")
            ];
            Db::name("dynamic_count")->insert($dynamicCountData);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return $id;
    }

    /**
     * 删除动态
     *
     * @param $id
     * @param $user
     * @return int
     * @throws AppException
     */
    public function delete($id, $user)
    {
        $dynamic = Db::name("dynamic")->where("id", $id)->find();
        if (empty($dynamic)) {
            throw AppException::factory(AppException::USER_DYNAMIC_NOT_EXISTS);
        }

        if ($dynamic["u_id"] != $user["id"]) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        // 删除缓存
        deleteUserDynamicInfo($id, Redis::factory());

        return Db::name("dynamic")->where("id", $id)->update(["is_delete" => DbDataIsDeleteEnum::YES]);

    }

    /**
     * 获取用户动态列表
     *
     * @param $startId
     * @param $pageSize
     * @param $userId
     * @return array
     */
    public function personal($startId, $pageSize, $userId)
    {
        $ret = [
            "dynamic" => [],
            "userInfo" => [],
            "dynamicCount" => []
        ];
        // 获取动态数据
        $dynamicQuery = Db::name("dynamic")
            ->where("u_id", $userId)
            ->where("is_delete", DbDataIsDeleteEnum::NO)
            ->order("create_time", "desc");
        if (!empty($startId)) {
            $dynamicQuery = $dynamicQuery->where("id", "<", $startId);
        }
        $dynamics = $dynamicQuery->limit($pageSize)->select()->toArray();

        if (empty($dynamics)) {
            return array_values($ret);
        }
        $ret["dynamic"] = $dynamics;

        // 获取动态用户数据
        $userInfo = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id = ui.u_id")
            ->field("u.id,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday")
            ->where("u.id", $userId)
            ->find();
        $ret["userInfo"] = $userInfo;

        // 获取动态统计数据
        $dynamicCount = Db::name("dynamic_count")
            ->whereIn("dynamic_id", array_column($dynamics, 'id'))
            ->select()->toArray();
        $ret["dynamicCount"] = $dynamicCount;

        return array_values($ret);
    }

    /**
     * 获取动态详情
     *
     * @param $id
     * @param $retry int
     * @return array
     * @throws AppException
     */
    public function info($id, $retry = 0)
    {
        // 读缓存
        $redis = Redis::factory();
        if ($data = getUserDynamicInfo($id, $redis)) {
            return $data;
        }

        // 读db回写缓存
        $lockKey = REDIS_KEY_PREFIX . "userDynamicInfoLock:" . $id;
        if ($redis->setnx($lockKey, 1)) {
            //设置锁过期时间防止失败后数据永修不更新
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
            // 获取动态数据
            $dynamic = Db::name("dynamic")->alias("d")
                ->leftJoin("user_info ui", "d.u_id = ui.id")
                ->leftJoin("user u", "d.u_id = u.id")
                ->leftJoin("dynamic_count dc", "d.id = dc.dynamic_id")
                ->field("d.*,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday,
            dc.like_count,dc.comment_count")
                ->where("d.is_delete", DbDataIsDeleteEnum::NO)
                ->where("d.id", $id)
                ->find();
            if (empty($dynamic)) {
                $redis->del($lockKey);
                throw AppException::factory(AppException::USER_DYNAMIC_NOT_EXISTS);
            }

            $ret = [];
            $ret["info"] = $dynamic;

            // 获取评论数据
            $dynamicComment = Db::name("dynamic_comment")->alias("dc")
                ->leftJoin("user_info ui", "dc.u_id = ui.id")
                ->field("dc.id,dc.pid,dc.u_id,dc.content,dc.source,dc.create_time,ui.portrait,ui.nickname")
                ->order("dc.id")
                ->select()->toArray();
            $ret["comment"] = $dynamicComment;
            cacheUserDynamicInfo($id, $ret, $redis);
            $redis->del($lockKey);
            return $ret;
        } else {
            //设置锁过期时间防止失败后数据永修不更新
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME); // sleep 50 毫秒
            return $this->info($id, ++$retry);
        }
        throw AppException::factory(AppException::TRY_AGAIN_LATER);
    }

    /**
     * 举报
     *
     * @param $id
     * @param $user
     * @return int
     * @throws AppException
     */
    public function report($id, $user)
    {
        $dynamic = Db::name("dynamic")->where('id', $id)->find();
        if (empty($dynamic)) {
            throw AppException::factory(AppException::USER_DYNAMIC_NOT_EXISTS);
        }

        return Db::name("dynamic")->where("id", $id)->update(["is_report" => DynamicIsReportEnum::YES, 'report_u_id' => $user["id"]]);
    }

    /**
     * 评论动态
     *
     * 1.添加评论
     * 2.修改评论数量
     * 3.清缓存
     *
     * @param $id int 动态ID
     * @param $pid int 父评论ID
     * @param $content string 评论内容
     * @param $user array 评论人
     *
     * @throws \Throwable
     */
    public function comment($id, $pid, $content, $user)
    {
        Db::startTrans();
        try {
            Db::name("dynamic_comment")->insertGetId([
                'dynamic_id' => $id,
                'pid' => $pid,
                'u_id' => $user["id"],
                'content' => $content,
            ]);

            Db::name("dynamic_count")->where("dynamic_id", $id)
                ->inc("comment_count", 1)
                ->update();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        // 删除缓存
        deleteUserDynamicInfo($id, Redis::factory());
    }
}