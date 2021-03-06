<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/9/21
 * Time: 下午2:13
 */

namespace app\common\service;


use app\common\helper\Redis;
use think\facade\Db;

class VisitorService extends Base
{

    /**
     * 添加访问记录
     *
     * @param $userId int 被访问用户ID
     * @param $visitorId int 访问用户ID
     * @return array|mixed
     */
    public static function addVisitorLog($userId, $visitorId)
    {
        if ($userId == $visitorId) { // 同一个人不添加记录
            return;
        }
        // 放入操作队列
        userVisitorCallbackProduce($userId, $visitorId, Redis::factory());
    }

    /**
     * 更新总访问次数缓存
     *
     * @param $userId
     * @param null $redis \
     */
    public static function updateVisitorSumCount($userId, $redis = null)
    {
        $redis = $redis === null ? Redis::factory() : $redis;
        $count = getUserVisitorSumCount($userId, $redis);
        // 没有缓存数据库回写
        if ($count === false) {
            $c = Db::name("visitor_count")->where("u_id", $userId)->value("count");
            cacheUserVisitorSumCount($userId, $c, $redis);
            return;
        }
        // 有缓存，更新
        addUserVisitorSumCount($userId, $redis);
        return;
    }

    /**
     * 获取访客用户列表
     *
     * @param $startId int  查询起始ID
     * @param $pageSize int 分页大小
     * @param $userId int 查询用户ID
     * @return array|mixed
     */
    public function user($startId, $pageSize, $userId)
    {
        $isVip = VipService::isVip($userId);
        $redis = Redis::factory();
        $ret = [
            "is_vip" => $isVip ? 1 : 0,
            "today_count" => getUserVisitorTodayCount($userId, $redis), // 今天访问数
            "count" => $this->getVisitorSumCount($userId, $redis), // 总访问次数
            "list" => [] // 列表
        ];
        if (!$isVip) {
            return $ret;
        }
        $query = Db::name("visitor_log")->alias("vl")
            ->leftJoin("user u", "vl.visitor_u_id = u.id")
            ->leftJoin("user_info ui", "vl.visitor_u_id = ui.u_id")
            ->field("vl.id,vl.visitor_u_id,vl.create_time,
            u.sex,u.user_number,
            ui.portrait,ui.nickname,ui.birthday,ui.city,ui.signatures")
            ->where("vl.u_id", $userId)
            ->order("vl.create_time", 'desc');
        if (!empty($startId)) {
            $query = $query->where("vl.id", "<", $startId);
        }

        $ret["list"] = $query->limit($pageSize)->select()->toArray();
        return $ret;
    }

    /**
     * 获取总访问次数缓存
     *
     * @param $userId
     * @param null $redis
     * @return bool|mixed|string
     */
    public function getVisitorSumCount($userId, $redis = null)
    {
        $redis = $redis === null ? Redis::factory() : $redis;
        $count = getUserVisitorSumCount($userId, $redis);
        if ($count !== false) {
            return $count;
        }
        $c = Db::name("visitor_count")->where("u_id", $userId)->value("count");
        cacheUserVisitorSumCount($userId, $c, $redis);
        return $c;
    }
}