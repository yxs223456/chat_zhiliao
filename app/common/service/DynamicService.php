<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/8/26
 * Time: 下午2:09
 */

namespace app\common\service;

use app\common\AppException;
use app\common\enum\DbDataIsDeleteEnum;
use app\common\enum\DynamicIsReportEnum;
use app\common\enum\UserIsStealthEnum;
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
            if (BlackListService::inUserBlackList($info["info"]['u_id'], $user['id'])) {
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
     * @return array
     * @throws AppException
     */
    public function getInfo($id)
    {
        // 获取动态数据
        $dynamic = Db::name("dynamic")->alias("d")
            ->leftJoin("user_info ui", "d.u_id = ui.u_id")
            ->leftJoin("user u", "d.u_id = u.id")
            ->leftJoin("dynamic_count dc", "d.id = dc.dynamic_id")
            ->field("d.*,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday,ui.city,
        dc.like_count,dc.comment_count")
            ->where("d.is_delete", DbDataIsDeleteEnum::NO)
            ->where("d.id", $id)
            ->find();
        if (empty($dynamic)) {
            throw AppException::factory(AppException::DYNAMIC_NOT_EXISTS);
        }

        $ret = [];
        $ret["info"] = $dynamic;

        // 获取评论数据
        $dynamicComment = Db::name("dynamic_comment")->alias("dc")
            ->leftJoin("user_info ui", "dc.u_id = ui.u_id")
            ->field("dc.id,dc.pid,dc.u_id,dc.content,dc.source,dc.create_time,ui.portrait,ui.nickname")
            ->where("dc.dynamic_id", $id)
            ->where("dc.is_delete", DbDataIsDeleteEnum::NO)
            ->order("dc.id")
            ->select()->toArray();
        $ret["comment"] = $dynamicComment;

        // 获取点赞人ID
        $ret["likeUserIds"] = Db::name('dynamic_like')->where("dynamic_id", $id)->column("u_id");
        return $ret;
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
        $dynamic = Db::name("dynamic")->where('id', $id)
            ->where("is_delete",DbDataIsDeleteEnum::NO)->find();
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
     * @return array
     * @throws \Throwable
     */
    public function comment($id, $pid, $content, $user)
    {
        $dynamic = Db::name("dynamic")->where("id", $id)->field("id,u_id")->find();
        if (empty($dynamic)) {
            throw AppException::factory(AppException::DYNAMIC_NOT_EXISTS);
        }
        $uid = $dynamic["u_id"];
        if (BlackListService::inUserBlackList($uid, $user['id'])) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 添加访问记录队列
        VisitorService::addVisitorLog($uid, $user["id"]);

        // 父评论数据
        $pcomment = Db::name("dynamic_comment")->where("id", $pid)->find();

        Db::startTrans();
        try {
            $commentId = Db::name("dynamic_comment")->insertGetId([
                'dynamic_id' => $id,
                'pid' => $pid,
                'u_id' => $user["id"],
                'content' => $content,
            ]);
            // 更新pid_path
            $pPidPath = empty($pcomment["pid_path"]) ? "0" : $pcomment["pid_path"];
            $pidPath = $pPidPath . "-" . $commentId;
            Db::name("dynamic_comment")->where("id", $commentId)->update(['pid_path' => $pidPath]);

            Db::name("dynamic_count")->where("dynamic_id", $id)
                ->inc("comment_count", 1)
                ->update();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return [
            'id' => $commentId,
            'u_id' => $user['id'],
            'content' => $content,
            'pid' => $pid,
            'to_user' => $pcomment["u_id"] ?? 0,
            'pid_path' => $pidPath
        ];
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
        if (BlackListService::inUserBlackList($uid, $user['id'])) {
            throw AppException::factory(AppException::USER_IN_BLACK_LIST);
        }

        // 判断是否已点赞
        $isLike = Db::name("dynamic_like")->where("dynamic_id", $id)
            ->where("u_id", $user["id"])->field("id")->find();
        if ($isLike) {
            throw AppException::factory(AppException::DYNAMIC_IS_LIKE);
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
        $dynamic = Db::name("dynamic")->where("id", $id)->field("id")->find();
        if (empty($dynamic)) {
            throw AppException::factory(AppException::DYNAMIC_NOT_EXISTS);
        }
        $isCancel = Db::name("dynamic_like")->where("dynamic_id", $id)
            ->where("u_id", $user["id"])->field("id")->find();
        if (empty($isCancel)) {
            throw AppException::factory(AppException::DYNAMIC_IS_CANCEL_LIKE);
        }
        Db::startTrans();
        try {
            $ret = Db::name("dynamic_like")->where("dynamic_id", $id)
                ->where("u_id", $user["id"])
                ->delete();
            // 如果有删除才更新防止出现负的
            if ($ret) {
                Db::name("dynamic_count")->where("dynamic_id", $id)
                    ->dec("like_count", 1)
                    ->update();
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**************************************************最新动态列表相关***********************************************/
    /**
     * 获取最新动态列表
     *
     * @param $startId int 查询开始ID
     * @param $pageSize int 分页大小
     * @param $user array 当前登陆用户
     *
     * @return array|mixed|null
     * @throws AppException
     */
    public function newest($startId, $pageSize, $user)
    {
        // 数据搜索条件，redis key
        $searchSex = $user["sex"] == 1 ? 2 : 1;

        $ret = [
            "dynamic" => [],
            "userInfo" => [],
            "dynamicCount" => [],
            "likeDynamicUserIds" => [],
            "userFollow" => [],
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
            return array_values($ret);
        }
        $ret["dynamic"] = $dynamics;

        // 获取动态用户数据
        $userInfo = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id = ui.u_id")
            ->leftJoin("user_set us","u.id = us.u_id")
            ->field("u.id,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday,ui.city,
            us.voice_chat_switch,us.voice_chat_price,us.video_chat_switch,us.video_chat_price,us.direct_message_free,
            us.direct_message_price")
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

        // 获取用户关注用户ID
        $userFollow = Db::name("user_follow")->where("u_id", $user['id'])
            ->whereIn("follow_u_id", array_column($dynamics, 'u_id'))
            ->column("follow_u_id");
        $ret["userFollow"] = $userFollow;

        return array_values($ret);
    }

    /*************************************************用户动态列表相关**************************************************/
    /**
     * 获取最新动态列表
     *
     * @param $startId int 查询开始ID
     * @param $pageSize int 分页大小
     * @param $userId int 查询用户ID
     *
     * @return array|mixed|null
     * @throws AppException
     */
    public function personal($startId, $pageSize, $userId)
    {
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

        return array_values($ret);
    }

    /**************************************************关注用户动态列表相关***********************************************/
    /**
     * 关注用户动态列表
     *
     * @param $startId
     * @param $pageSize
     * @param $userId
     * @return array|mixed|null
     */
    public function concern($startId, $pageSize, $userId)
    {
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
            return array_values($ret);
        }
        $ret["dynamic"] = $dynamics;

        // 获取动态用户数据
        $userInfo = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id = ui.u_id")
            ->leftJoin("user_set us","us.u_id = u.id")
            ->field("u.id,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday,ui.city,
            us.voice_chat_switch,us.voice_chat_price,us.video_chat_switch,us.video_chat_price,us.direct_message_free,
            us.direct_message_price")
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

        return array_values($ret);
    }

    /**************************************************附近用户动态列表相关***********************************************/

    /**
     * 附近人动态列表
     *
     * @param $pageNum int 起始ID
     * @param $pageSize int 分页
     * @param $long int 经度
     * @param $lat int 纬度
     * @param $userId int 登陆用户ID
     * @return mixed
     */
    public function near($pageNum, $pageSize, $long, $lat, $userId, $isFlush)
    {
        // 经纬度不传返回空列表
        if (empty($long) || empty($lat)) {
            return [];
        }
        $redis = Redis::factory();
        $userSet = UserSetService::getUserSetByUId($userId, $redis);
        // 不隐身 缓存当前用户坐标
        if ($userSet["is_stealth"] == UserIsStealthEnum::NO) {
            cacheUserLongLatInfo($userId, $lat, $long, $redis);
        }

        // 需要更新
        if ($isFlush) {
            // 更新缓存
            $data = $this->updateAndGetNearData($userId, $redis);
            // 返回第一页数据
            $dynamicIds = array_slice($data, 0, $pageSize);
            return $this->getData($userId, $dynamicIds);
        }

        // 返回数据
        $dynamicIds = $this->nearPage($pageNum, $pageSize, $userId, $redis);
        return $this->getData($userId, $dynamicIds);
    }

    /**
     * 附近动态分页
     *
     * @param $pageNum
     * @param $pageSize
     * @param $userId
     * @param \Redis $redis
     * @return array
     */
    private function nearPage($pageNum, $pageSize, $userId, \Redis $redis)
    {
        // 缓存存在直接返回
        if (!empty($data = getNearDynamicSortData($userId, $redis))) {
            return array_slice($data, ($pageNum - 1) * $pageSize, $pageSize);
        }
        // 更新缓存
        $data = $this->updateAndGetNearData($userId, $redis);
        return array_slice($data, ($pageNum - 1) * $pageSize, $pageSize);
    }

    /**
     * 更新缓存
     *
     * @param $userId
     * @param \Redis $redis
     * @return array
     */
    private function updateAndGetNearData($userId, \Redis $redis)
    {
        // 获取附近用户ID
        $nearUserIds = getNearUserLongLatInfo($userId, $redis);
        $cacheData = [];

        if (empty($nearUserIds)) {
            cacheNearDynamicSortData($userId, $cacheData, $redis);
            return $cacheData;
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
            cacheNearDynamicSortData($userId, $cacheData, $redis);
            return $cacheData;
        }

        // 获取动态数据
        $dynamics = Db::name("dynamic")
            ->whereIn("u_id", array_keys($userIds))
            ->field("id,u_id")
            ->where("is_delete", DbDataIsDeleteEnum::NO)
//            ->where("create_time", ">", date("Y-m-d H:i:s", strtotime("-15 days")))
            ->select()->toArray();

        if (empty($dynamics)) {
            cacheNearDynamicSortData($userId, $cacheData, $redis);
            return $cacheData;
        }

        foreach ($dynamics as &$item) {
            // 计算距离
            $item["distance"] = $userIds[$item["u_id"]] ?? 0;
        }

        // 排序
        $cacheData = $this->sortArr($dynamics);
        cacheNearDynamicSortData($userId, $cacheData, $redis);
        return $cacheData;
    }

    /**
     * 距离排序
     *
     * @param $dynamics
     * @return array
     */
    private function sortArr($dynamics)
    {
        if (count($dynamics) <= 1) {
            return $dynamics;
        }
        $first = array_shift($dynamics);
        $left = [];
        $right = [];
        foreach ($dynamics as $item) {
            if ($item["distance"] >= $first["distance"]) {
                $right[] = $item;
            } else {
                $left[] = $item;
            }
        }
        return array_filter(array_merge($this->sortArr($left), [$first], $this->sortArr($right)));
    }

    /**
     * 根据排序动态ID获取数据
     *
     * @param $userId
     * @param $dynamicIds
     * @return array
     */
    private function getData($userId, $dynamicIds)
    {
        if (empty($dynamicIds)) {
            return [];
        }
        // 获取动态数据 id 倒叙
        $dynamics = Db::name("dynamic")
            ->whereIn("id", array_column($dynamicIds, "id"))
            ->where("is_delete", DbDataIsDeleteEnum::NO)
            ->select()->toArray();

        if (empty($dynamics)) {
            return [];
        }

        // 获取动态查用户数据
        $userInfo = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "u.id = ui.u_id")
            ->leftJoin("user_set us", "us.u_id = ui.u_id")
            ->field("u.id,u.sex,u.user_number,ui.portrait,ui.nickname,ui.birthday,ui.city,
            us.voice_chat_switch,us.voice_chat_price,us.video_chat_switch,us.video_chat_price,us.direct_message_free,
            us.direct_message_price")
            ->whereIn("u.id", array_column($dynamics, 'u_id'))
            ->select()->toArray();
        $userIdToUserInfo = array_combine(array_column($userInfo, 'id'), $userInfo);

        // 获取动态统计数据
        $dynamicCount = Db::name("dynamic_count")
            ->whereIn("dynamic_id", array_column($dynamics, 'id'))
            ->select()->toArray();
        $dynamicIdToDynamicCount = array_combine(array_column($dynamicCount, 'dynamic_id'), $dynamicCount);

        // 获取当前用户点赞的动态ID
        $currentUserLikeDynamicIds = Db::name("dynamic_like")
            ->whereIn("dynamic_id", array_column($dynamics, 'id'))
            ->where("u_id", $userId)
            ->column("dynamic_id");
        // 获取用户是否关注数据
        $userFollow = Db::name("user_follow")->where("u_id", $userId)
            ->whereIn("follow_u_id", array_column($dynamics, 'u_id'))
            ->column("follow_u_id");

        $dynamicIdsToDistance = array_column($dynamicIds, "distance", "id");
        foreach ($dynamics as &$item) {
            $item["userInfo"] = isset($userIdToUserInfo[$item['u_id']]) ? $userIdToUserInfo[$item['u_id']] : [];
            $item["dynamicCount"] = isset($dynamicIdToDynamicCount[$item['id']]) ? $dynamicIdToDynamicCount[$item['id']] : [];
            $item["is_like"] = in_array($item["id"], $currentUserLikeDynamicIds) ? 1 : 0;
            $item["is_followed"] = in_array($item["u_id"], $userFollow) ? 1 : 0;
            $item["distance"] = $dynamicIdsToDistance[$item["id"]] ?? 0;
        }

        return $this->sortArr($dynamics);
    }
}