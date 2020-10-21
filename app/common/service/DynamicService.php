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
                'u_sex' => $user['sex'],
                'content' => $content,
                'source' => json_encode($source),
                'create_time' => date("Y-m-d H:i:s")
            ];
            $id = Db::name("dynamic")->insertGetId($dynamicData);
            $dynamicCountData = [
                'u_id' => $user["id"],
                'dynamic_id' => $id,
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
            throw AppException::factory(AppException::DYNAMIC_NOT_EXISTS);
        }

        if ($dynamic["u_id"] != $user["id"]) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        // 删除缓存
        $redis = Redis::factory();
        deleteUserDynamicInfo($id, $redis);
        deleteUserIndexDataByUId($user["id"], $redis);

        return Db::name("dynamic")->where("id", $id)->update(["is_delete" => DbDataIsDeleteEnum::YES]);

    }

    /**
     * 获取动态详情信息
     *
     * @param $id int 动态ID
     * @param $user array 登陆用户
     * @return array
     * @throws AppException
     */
    public function info($id, $user)
    {
        $info = $this->getInfo($id);
        // 如果有数据
        if (!empty($info["info"]["u_id"])) {
            // 判断是否黑名单
            if (BlackListService::inUserBlackList($user['id'], $info["info"]['u_id'])) {
                throw AppException::factory(AppException::USER_IN_BLACK_LIST);
            }
            // 添加访问记录队列
            VisitorService::addVisitorLog($info["info"]['u_id'], $user["id"]);
        }
        return $info;
    }

    /**
     * 获取动态详情（缓存）
     *
     * @param $id int 动态ID
     * @param $retry int 尝试次数
     * @return array
     * @throws AppException
     */
    public function getInfo($id, $retry = 0)
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
                ->field("d.*,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday,ui.city,
            dc.like_count,dc.comment_count")
                ->where("d.is_delete", DbDataIsDeleteEnum::NO)
                ->where("d.id", $id)
                ->find();
            if (empty($dynamic)) {
                $redis->del($lockKey);
                throw AppException::factory(AppException::DYNAMIC_NOT_EXISTS);
            }

            $ret = [];
            $ret["info"] = $dynamic;

            // 获取评论数据
            $dynamicComment = Db::name("dynamic_comment")->alias("dc")
                ->leftJoin("user_info ui", "dc.u_id = ui.id")
                ->field("dc.id,dc.pid,dc.u_id,dc.content,dc.source,dc.create_time,ui.portrait,ui.nickname")
                ->where("dc.dynamic_id", $id)
                ->order("dc.id")
                ->select()->toArray();
            $ret["comment"] = $dynamicComment;

            // 获取点赞人ID
            $ret["likeUserIds"] = Db::name('dynamic_like')->where("dynamic_id", $id)->column("u_id");
            cacheUserDynamicInfo($id, $ret, $redis);
            $redis->del($lockKey);
            return $ret;
        } else {
            //设置锁过期时间防止失败后数据永修不更新
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME); // sleep 50 毫秒
            return $this->getInfo($id, ++$retry);
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
            throw AppException::factory(AppException::DYNAMIC_NOT_EXISTS);
        }

        return Db::name("dynamic")->where("id", $id)
            ->update(["is_report" => DynamicIsReportEnum::YES, 'report_u_id' => $user["id"]]);
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
        $dynamic = Db::name("dynamic")->where("id", $id)->field("id,u_id")->find();
        if (empty($dynamic)) {
            throw AppException::factory(AppException::DYNAMIC_NOT_EXISTS);
        }
        $uid = $dynamic["u_id"];
        if (BlackListService::inUserBlackList($user['id'], $uid)) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 添加访问记录队列
        VisitorService::addVisitorLog($uid, $user["id"]);

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

    /**
     * 点赞动态
     *
     * @param $id
     * @param $user
     * @throws \Throwable
     */
    public function like($id, $user)
    {
        $dynamic = Db::name("dynamic")->where("id", $id)->field("id,u_id")->find();
        if (empty($dynamic)) {
            throw AppException::factory(AppException::DYNAMIC_NOT_EXISTS);
        }
        $uid = $dynamic["u_id"];
        if (BlackListService::inUserBlackList($user['id'], $uid)) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 添加访问记录队列
        VisitorService::addVisitorLog($uid, $user["id"]);

        Db::startTrans();
        try {
            Db::name("dynamic_like")->insertGetId([
                "dynamic_id" => $id,
                'u_id' => $user['id'],
            ]);
            Db::name("dynamic_count")->where("dynamic_id", $id)
                ->inc("like_count", 1)
                ->update();

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        // 删除缓存
        deleteUserDynamicInfo($id, Redis::factory());
    }

    /**
     * 动态取消点赞
     *
     * @param $id
     * @param $user
     * @throws \Throwable
     */
    public function unlike($id, $user)
    {
        Db::startTrans();
        try {
            Db::name("dynamic_like")->where("dynamic_id", $id)
                ->where("u_id", $user["id"])
                ->delete();
            Db::name("dynamic_count")->where("dynamic_id", $id)
                ->dec("like_count", 1)
                ->update();

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        // 删除缓存
        deleteUserDynamicInfo($id, Redis::factory());
    }

    /**************************************************最新动态列表相关***********************************************/
    /**
     * 获取最新动态列表
     *
     * @param $startId int 查询开始ID
     * @param $pageSize int 分页大小
     * @param $isFlush int 是否刷新缓存
     * @param $user array 当前登陆用户
     *
     * @return array|mixed|null
     * @throws AppException
     */
    public function newest($startId, $pageSize, $isFlush, $user)
    {
        // 需要刷新更新缓存内容
        if ($isFlush) {
            return $this->newestUpdate($pageSize, $user);
        }
        // 不需要刷新查询缓存数据
        return $this->newestPage($startId, $pageSize, $user);
    }

    /**
     * 刷新最新动态缓存数据
     * 1. 判断是否需要更新缓存
     * 2. 需要更新缓存，删除所有缓存，返回新数据
     * 3. 不需要更新缓存，直接返回
     *
     * @param $pageSize int 分页大小
     * @param $user array  用户
     * @return array|mixed|null
     * @throws AppException
     */
    private function newestUpdate($pageSize, $user)
    {
        $searchSex = $user["sex"] == 1 ? 2 : 1;
        // 获取最新动态ID
        $newDynamicId = Db::name("dynamic")
            ->where("u_sex", $searchSex)
            ->where('is_delete', DbDataIsDeleteEnum::NO)
            ->order("id", "desc")
            ->value("id");
        // 为空，没有动态数据，返回缓存的第一页
        if (empty($newDynamicId)) {
            return $this->newestPage(0, $pageSize, $user);
        }

        // 不为空并且和缓存最新一条相同直接返回缓存数据
        $firstPage = $this->newestPage(0, $pageSize, $user);
        $dynamic = array_shift($firstPage);
        if (isset($dynamic[0]["id"]) && $dynamic[0]["id"] == $newDynamicId) {
            return $this->newestPage(0, $pageSize, $user);
        }

        // 不同删除首页缓存
        deleteFirstNewestDynamicInfo($searchSex, $pageSize, Redis::factory());
        return $this->newestPage(0, $pageSize, $user);
    }

    /**
     * 获取最新的分页数据
     *
     * @param $startId int 起始查询ID
     * @param $pageSize int 分页大小
     * @param $user array 当前用户
     * @param int $retry 锁等待尝试次数
     * @return array|mixed|null
     * @throws AppException
     */
    private function newestPage($startId, $pageSize, $user, $retry = 0)
    {
        // 数据搜索条件，redis key
        $searchSex = $user["sex"] == 1 ? 2 : 1;

        // 读缓存
        $redis = Redis::factory();
        if ($data = getNewestDynamicInfo($searchSex, $startId, $pageSize, $redis)) {
            return $data;
        }

        $lockKey = REDIS_KEY_PREFIX . "newestDynamicInfoLock:" . $searchSex . ":" . $startId . ":" . $pageSize;
        if ($redis->setnx($lockKey, 1)) {
            //设置锁过期时间防止失败后数据永修不更新
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
            $ret = [
                "dynamic" => [],
                "userInfo" => [],
                "dynamicCount" => [],
                "likeDynamicUserIds" => []
            ];

            // 获取动态数据
            $dynamicQuery = Db::name("dynamic")
                ->where("u_sex", $searchSex)
                ->where("is_delete", DbDataIsDeleteEnum::NO)
                ->order("id", "desc");
            if (!empty($startId)) {
                $dynamicQuery = $dynamicQuery->where("id", "<", $startId);
            }
            $dynamics = $dynamicQuery->limit($pageSize)->select()->toArray();

            if (empty($dynamics)) {
                $redis->del($lockKey);
                cacheNewestDynamicInfo($searchSex, $startId, $pageSize, array_values($ret), $redis);
                return array_values($ret);
            }
            $ret["dynamic"] = $dynamics;

            // 获取动态用户数据
            $userInfo = Db::name("user")->alias("u")
                ->leftJoin("user_info ui", "u.id = ui.u_id")
                ->field("u.id,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday,ui.city")
                ->whereIn("u.id", array_column($dynamics, 'u_id'))
                ->select()->toArray();
            $ret["userInfo"] = $userInfo;

            // 获取动态统计数据
            $dynamicCount = Db::name("dynamic_count")
                ->whereIn("dynamic_id", array_column($dynamics, 'id'))
                ->select()->toArray();
            $ret["dynamicCount"] = $dynamicCount;

            // 获取动态点赞的用户ID
            $dynamicIdToUserIds = Db::name("dynamic_like")
                ->whereIn("dynamic_id", array_column($dynamics, 'id'))
                ->field("dynamic_id,u_id")
                ->select()->toArray();
            $likeDynamicUserIds = [];
            // 点赞用户ID根据动态ID分组
            array_map(function ($item) use (&$likeDynamicUserIds) {
                $likeDynamicUserIds[$item['dynamic_id']][] = $item["u_id"];
            }, $dynamicIdToUserIds);
            $ret["likeDynamicUserIds"] = $likeDynamicUserIds;

            cacheNewestDynamicInfo($searchSex, $startId, $pageSize, array_values($ret), $redis);
            $redis->del($lockKey);
            return array_values($ret);
        } else {
            //设置锁过期时间防止失败后数据永修不更新
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME); // sleep 50 毫秒
            return $this->newestPage($startId, $pageSize, $user, ++$retry);
        }
        throw AppException::factory(AppException::TRY_AGAIN_LATER);
    }

    /*************************************************用户动态列表相关**************************************************/
    /**
     * 获取最新动态列表
     *
     * @param $startId int 查询开始ID
     * @param $pageSize int 分页大小
     * @param $isFlush int 是否刷新缓存
     * @param $userId int 查询用户ID
     *
     * @return array|mixed|null
     * @throws AppException
     */
    public function personal($startId, $pageSize, $isFlush, $userId)
    {
        // 需要刷新更新缓存内容
        if ($isFlush) {
            return $this->personalUpdate($pageSize, $userId);
        }
        // 不需要刷新查询缓存数据
        return $this->personalPage($startId, $pageSize, $userId);
    }

    /**
     * 刷新最新动态缓存数据
     * 1. 判断是否需要更新缓存
     * 2. 需要更新缓存，删除所有缓存，返回新数据
     * 3. 不需要更新缓存，直接返回
     *
     * @param $pageSize int 分页大小
     * @param $userId int  查询用户ID
     * @return array|mixed|null
     * @throws AppException
     */
    private function personalUpdate($pageSize, $userId)
    {
        // 获取最新动态ID
        $newDynamicId = Db::name("dynamic")
            ->where("u_id", $userId)
            ->where('is_delete', DbDataIsDeleteEnum::NO)
            ->order("id", "desc")
            ->value("id");
        // 为空，没有动态数据，返回缓存的第一页
        if (empty($newDynamicId)) {
            return $this->personalPage(0, $pageSize, $userId);
        }

        // 不为空并且和缓存最新一条相同直接返回缓存数据
        $firstPage = $this->personalPage(0, $pageSize, $userId);
        $dynamic = array_shift($firstPage);
        if (isset($dynamic[0]["id"]) && $dynamic[0]["id"] == $newDynamicId) {
            return $this->personalPage(0, $pageSize, $userId);
        }

        // 不同删除首页缓存
        deleteFirstPersonalDynamicInfo($userId, $pageSize, Redis::factory());
        return $this->personalPage(0, $pageSize, $userId);
    }

    /**
     * 获取用户动态列表
     *
     * @param $startId int 开始查询ID
     * @param $pageSize int 分页数据大小
     * @param $userId int 查询用户ID
     * @param int $retry 尝试次数
     * @return array|mixed|null
     * @throws AppException
     */
    private function personalPage($startId, $pageSize, $userId, $retry = 0)
    {
        // 读缓存
        $redis = Redis::factory();
        if ($data = getPersonalDynamicInfo($userId, $startId, $pageSize, $redis)) {
            return $data;
        }

        $lockKey = REDIS_KEY_PREFIX . "personalDynamicInfoLock:" . $userId . ":" . $startId . ":" . $pageSize;
        if ($redis->setnx($lockKey, 1)) {
            //设置锁过期时间防止失败后数据永修不更新
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);

            $ret = [
                "dynamic" => [],
                "userInfo" => [],
                "dynamicCount" => [],
                "likeDynamicUserIds" => []
            ];
            // 获取动态数据
            $dynamicQuery = Db::name("dynamic")
                ->where("u_id", $userId)
                ->where("is_delete", DbDataIsDeleteEnum::NO)
                ->order("id", "desc");
            if (!empty($startId)) {
                $dynamicQuery = $dynamicQuery->where("id", "<", $startId);
            }
            $dynamics = $dynamicQuery->limit($pageSize)->select()->toArray();

            if (empty($dynamics)) {
                $redis->del($lockKey);
                cachePersonalDynamicInfo($userId, $startId, $pageSize, array_values($ret), $redis);
                return array_values($ret);
            }
            $ret["dynamic"] = $dynamics;

            // 获取动态用户数据
            $userInfo = Db::name("user")->alias("u")
                ->leftJoin("user_info ui", "u.id = ui.u_id")
                ->field("u.id,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday,ui.city")
                ->where("u.id", $userId)
                ->find();
            $ret["userInfo"] = $userInfo;

            // 获取动态统计数据
            $dynamicCount = Db::name("dynamic_count")
                ->whereIn("dynamic_id", array_column($dynamics, 'id'))
                ->select()->toArray();
            $ret["dynamicCount"] = $dynamicCount;

            // 获取动态点赞的用户ID
            $dynamicIdToUserIds = Db::name("dynamic_like")
                ->whereIn("dynamic_id", array_column($dynamics, 'id'))
                ->field("dynamic_id,u_id")
                ->select()->toArray();
            $likeDynamicUserIds = [];
            // 点赞用户ID根据动态ID分组
            array_map(function ($item) use (&$likeDynamicUserIds) {
                $likeDynamicUserIds[$item['dynamic_id']][] = $item["u_id"];
            }, $dynamicIdToUserIds);
            $ret["likeDynamicUserIds"] = $likeDynamicUserIds;

            cachePersonalDynamicInfo($userId, $startId, $pageSize, array_values($ret), $redis);
            $redis->del($lockKey);
            return array_values($ret);
        } else {
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME); // sleep 50 毫秒
            return $this->personalPage($startId, $pageSize, $userId, ++$retry);
        }
        throw AppException::factory(AppException::TRY_AGAIN_LATER);
    }

    /**************************************************关注用户动态列表相关***********************************************/
    /**
     * 关注用户动态列表
     *
     * @param $startId
     * @param $pageSize
     * @param $isFlush
     * @param $userId
     * @return array|mixed|null
     */
    public function concern($startId, $pageSize, $isFlush, $userId)
    {
        // 需要刷新更新缓存内容
        if ($isFlush) {
            return $this->concernUpdate($pageSize, $userId);
        }
        // 不需要刷新查询缓存数据
        return $this->concernPage($startId, $pageSize, $userId);
    }

    /**
     * 刷新关注用户动态缓存数据
     * 1. 判断是否需要更新缓存
     * 2. 需要更新缓存，删除所有缓存，返回新数据
     * 3. 不需要更新缓存，直接返回
     *
     * @param $pageSize int 分页大小
     * @param $userId int  用户id
     * @return array|mixed|null
     * @throws AppException
     */
    private function concernUpdate($pageSize, $userId)
    {
        // 获取最新动态ID
        $followUserIds = Db::name("user_follow")
            ->where("u_id", $userId)
            ->column("follow_u_id");
        if (empty($followUserIds)) {
            return $this->concernPage(0, $pageSize, $userId);
        }
        $newDynamicId = Db::name("dynamic")
            ->whereIn("u_id", $followUserIds)
            ->where('is_delete', DbDataIsDeleteEnum::NO)
            ->order("id", "desc")
            ->value("id");
        // 为空，没有动态数据，返回缓存的第一页
        if (empty($newDynamicId)) {
            return $this->concernPage(0, $pageSize, $userId);
        }

        // 不为空并且和缓存最新一条相同直接返回缓存数据
        $firstPage = $this->concernPage(0, $pageSize, $userId);
        $dynamic = array_shift($firstPage);
        if (isset($dynamic[0]["id"]) && $dynamic[0]["id"] == $newDynamicId) {
            return $this->concernPage(0, $pageSize, $userId);
        }

        // 不同删除当前用户关注动态所有缓存
        deleteUserFollowDynamicInfo($userId, Redis::factory());
        return $this->concernPage(0, $pageSize, $userId);
    }

    /**
     * 获取关注用户的分页数据
     *
     * @param $startId int 起始查询ID
     * @param $pageSize int 分页大小
     * @param $userId int 当前用户id
     * @param int $retry 锁等待尝试次数
     * @return array|mixed|null
     * @throws AppException
     */
    private function concernPage($startId, $pageSize, $userId, $retry = 0)
    {
        // 读缓存
        $redis = Redis::factory();
        if ($data = getUserFollowDynamicInfo($userId, $startId, $pageSize, $redis)) {
            return $data;
        }

        $lockKey = REDIS_KEY_PREFIX . "userFollowDynamicInfoLock:" . $userId . ":" . $startId . ":" . $pageSize;
        if ($redis->setnx($lockKey, 1)) {
            //设置锁过期时间防止失败后数据永修不更新
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
            $ret = [
                "dynamic" => [],
                "userInfo" => [],
                "dynamicCount" => [],
                "likeDynamicUserIds" => []
            ];

            // 获取关注用户ID
            $followUserIds = Db::name("user_follow")
                ->where("u_id", $userId)
                ->column("follow_u_id");
            if (empty($followUserIds)) {
                $redis->del($lockKey);
                cacheUserFollowDynamicInfo($userId, $startId, $pageSize, array_values($ret), $redis);
                return array_values($ret);
            }

            // 获取动态数据
            $dynamicQuery = Db::name("dynamic")
                ->whereIn("u_id", $followUserIds)
                ->where("is_delete", DbDataIsDeleteEnum::NO)
                ->order("id", "desc");
            if (!empty($startId)) {
                $dynamicQuery = $dynamicQuery->where("id", "<", $startId);
            }
            $dynamics = $dynamicQuery->limit($pageSize)->select()->toArray();

            if (empty($dynamics)) {
                $redis->del($lockKey);
                cacheUserFollowDynamicInfo($userId, $startId, $pageSize, array_values($ret), $redis);
                return array_values($ret);
            }
            $ret["dynamic"] = $dynamics;

            // 获取动态用户数据
            $userInfo = Db::name("user")->alias("u")
                ->leftJoin("user_info ui", "u.id = ui.u_id")
                ->field("u.id,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday,ui.city")
                ->whereIn("u.id", array_column($dynamics, 'u_id'))
                ->select()->toArray();
            $ret["userInfo"] = $userInfo;

            // 获取动态统计数据
            $dynamicCount = Db::name("dynamic_count")
                ->whereIn("dynamic_id", array_column($dynamics, 'id'))
                ->select()->toArray();
            $ret["dynamicCount"] = $dynamicCount;

            // 获取动态点赞的用户ID
            $dynamicIdToUserIds = Db::name("dynamic_like")
                ->whereIn("dynamic_id", array_column($dynamics, 'id'))
                ->field("dynamic_id,u_id")
                ->select()->toArray();
            $likeDynamicUserIds = [];
            // 点赞用户ID根据动态ID分组
            array_map(function ($item) use (&$likeDynamicUserIds) {
                $likeDynamicUserIds[$item['dynamic_id']][] = $item["u_id"];
            }, $dynamicIdToUserIds);
            $ret["likeDynamicUserIds"] = $likeDynamicUserIds;

            cacheUserFollowDynamicInfo($userId, $startId, $pageSize, array_values($ret), $redis);
            $redis->del($lockKey);
            return array_values($ret);
        } else {
            //设置锁过期时间防止失败后数据永修不更新
            $redis->expire($lockKey, Constant::CACHE_LOCK_SECONDS);
        }

        if ($retry < Constant::GET_CACHE_TIMES) {
            usleep(Constant::GET_CACHE_WAIT_TIME); // sleep 50 毫秒
            return $this->concernPage($startId, $pageSize, $userId, ++$retry);
        }
        throw AppException::factory(AppException::TRY_AGAIN_LATER);
    }

    /**************************************************附近用户动态列表相关***********************************************/
    /**
     * 附近人动态列表
     *
     * @param $pageNum int 起始ID
     * @param $pageSize int 分页
     * @param $long int 经度
     * @param $lat int 纬度
     * @param $isFlush int 是否刷新
     * @param $userId int 用户ID
     * @return mixed
     */
    public function near($pageNum, $pageSize, $long, $lat, $isFlush, $userId)
    {
        // 需要刷新更新缓存内容
        if ($isFlush) {
            return $this->nearUpdate($pageSize, $long, $lat, $userId);
        }
        // 不需要刷新查询缓存数据
        return $this->nearPage($pageNum, $pageSize, $long, $lat, $userId);
    }

    /**
     * 刷新附近动态缓存数据
     * 1. 判断是否需要更新缓存
     * 2. 需要更新缓存，删除所有缓存，返回新数据
     * 3. 不需要更新缓存，直接返回
     *
     * @param $pageSize int 分页大小
     * @param $long string 经度
     * @param $lat string 纬度
     * @param $userId int  用户id
     * @return array|mixed|null
     * @throws AppException
     */
    private function nearUpdate($pageSize, $long, $lat, $userId)
    {
        // 获取更新锁 不为空5分钟内更新过，直接返回
        $redis = Redis::factory();
        $lock = getNearUserDynamicInfoLock($userId, $redis);
        if (!empty($lock)) {
            return $this->nearPage(1, $pageSize, $long, $lat, $userId);
        }

        // 没有加锁 获取数据库数据 并排序
        $dynamics = $this->getDbData($pageSize, $lat, $long, $userId);

        // 添加缓存
        cacheNearUserDynamicInfo($userId, $pageSize, $dynamics, $redis);
        return array_slice($dynamics, 0, $pageSize);
    }

    /**
     * 获取数据库数据并且排序
     *
     * @param $pageSize int 分页大小
     * @param $lat int 纬度
     * @param $long int 经度
     * @param $userId int 用户ID
     * @return array
     */
    private function getDbData($pageSize, $lat, $long, $userId)
    {
        $redis = Redis::factory();
        // 更新前删除之前的缓存
        deleteNearUserDynamicInfo($userId, $pageSize, $redis);
        // 缓存当前用户坐标
        cacheUserLongLatInfo($userId, $lat, $long, $redis);
        // 获取附近用户ID
        $nearUserIds = getNearUserLongLatInfo($userId, $redis);

        if (empty($nearUserIds)) {
            cacheNearUserDynamicInfo($userId, $pageSize, [], $redis);
            return [];
        }

        // 重新整理 删除当前用户的ID $value[0] = userId $value[1] = distance
        $userIds = [];
        foreach ($nearUserIds as $value) {
            if ($value[0] == $userId) {
                continue;
            }
            $userIds[$value[0]] = $value[1];
        }

        if (empty($userIds)) {
            cacheNearUserDynamicInfo($userId, $pageSize, [], $redis);
            return [];
        }
        // 获取动态数据
        $dynamics = Db::name("dynamic")
            ->whereIn("u_id", array_keys($userIds))
            ->where("is_delete", DbDataIsDeleteEnum::NO)
            ->limit(600)
            ->select()->toArray();

        if (empty($dynamics)) {
            cacheNearUserDynamicInfo($userId, $pageSize, [], $redis);
            return [];
        }

        // 获取动态用户数据
        $userInfo = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id = ui.u_id")
            ->field("u.id,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday,ui.city")
            ->whereIn("u.id", array_column($dynamics, 'u_id'))
            ->select()->toArray();
        $userIdToUserInfo = array_combine(array_column($userInfo, 'id'), $userInfo);

        // 获取动态统计数据
        $dynamicCount = Db::name("dynamic_count")
            ->whereIn("dynamic_id", array_column($dynamics, 'id'))
            ->select()->toArray();
        $dynamicIdToDynamicCount = array_combine(array_column($dynamicCount, 'dynamic_id'), $dynamicCount);

        // 获取动态点赞的用户ID
        $dynamicIdToUserIds = Db::name("dynamic_like")
            ->whereIn("dynamic_id", array_column($dynamics, 'id'))
            ->field("dynamic_id,u_id")
            ->select()->toArray();
        $likeDynamicUserIds = [];
        // 点赞用户ID根据动态ID分组
        array_map(function ($item) use (&$likeDynamicUserIds) {
            $likeDynamicUserIds[$item['dynamic_id']][] = $item["u_id"];
        }, $dynamicIdToUserIds);


        foreach ($dynamics as &$item) {
            $item["userInfo"] = isset($userIdToUserInfo[$item['u_id']]) ? $userIdToUserInfo[$item['u_id']] : [];
            $item["dynamicCount"] = isset($dynamicIdToDynamicCount[$item['id']]) ? $dynamicIdToDynamicCount[$item['id']] : [];
            $item["likeDynamicUserIds"] = isset($likeDynamicUserIds[$item['id']]) ? $likeDynamicUserIds[$item['id']] : [];

            // 计算距离
            $item["distance"] = $userIds[$item["u_id"]] ?? 0;
        }

        $distanceSort = array_column($dynamics, 'distance');
        array_multisort($distanceSort, SORT_ASC, $dynamics);

        // 添加更新锁
        setNearUserDynamicInfoLock($userId, $redis);
        return $dynamics;
    }

    /**
     * 获取附近人动态的分页数据
     *
     * @param $pageNum int 分页
     * @param $pageSize int 分页大小
     * @param $long int 经度
     * @param $lat int 纬度
     * @param $userId int 当前用户id
     * @return array|mixed|null
     * @throws AppException
     */
    private function nearPage($pageNum, $pageSize, $long, $lat, $userId)
    {
        // 读缓存
        $redis = Redis::factory();
        $key = REDIS_NEAR_USER_DYNAMIC_INFO . $userId . ":" . $pageSize;
        // 缓存不存在生成缓存
        if (!$redis->exists($key)) {
            // 没有加锁 获取数据库数据 并排序
            $dynamics = $this->getDbData($pageSize, $lat, $long, $userId);
            // 添加缓存
            cacheNearUserDynamicInfo($userId, $pageSize, $dynamics, $redis);
            return array_slice($dynamics, 0, $pageSize);
        }

        $data = getNearUserDynamicInfo($userId, $pageSize, $redis);

        if (empty($data)) {
            return [];
        }

        return array_slice($data, ($pageNum - 1) * $pageSize, $pageSize);
    }
}