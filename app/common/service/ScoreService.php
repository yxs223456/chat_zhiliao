<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-14
 * Time: 14:35
 */

namespace app\common\service;

use app\common\AppException;
use think\facade\Db;

class ScoreService extends Base
{
    /**
     * 评分
     *
     * @param $user
     * @param $chatId
     * @param $score
     * @return bool
     * @throws \Throwable
     */
    public function appraises($user, $chatId, $score)
    {
        // 验证聊天是否存在
        $chat = Db::name("chat")->where("id", $chatId)->find();
        if (empty($chat)) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }
        $suid = $chat['s_u_id']; // 拨打人id
        $tuid = $chat['t_u_id']; // 接听人id
        if ($suid == $user["id"]) {
            $userId = $tuid;
        } else {
            $userId = $suid;
        }
        Db::startTrans();
        try {
            // 添加评分记录
            $data = [
                'u_id' => $userId,
                'appraises_u_id' => $user['id'],
                'chat_id' => $chatId,
                'service_score' => $score
            ];
            Db::name("user_score_log")->insert($data);

            // 更新用户评分总表
            Db::name("user_score")->where("u_id", $userId)->inc("total_score", $score)
                ->inc("total_users", 1)->update();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return true;
    }

    /**
     * 获取用户评分
     *
     * @param $uid
     * @return string
     */
    public static function getScore($uid)
    {
        $score = Db::name("user_score")->where("u_id", $uid)->find();
        if (empty($score) || empty($score["total_users"])) {
            return "0.0";
        }

        return bcdiv($score["total_score"], $score["total_users"], 1);
    }

    /**
     * 获取分数的字符串表示
     * @param $score
     * @return string
     */
    public static function getScoreByScore($score)
    {
        if (bccomp($score, 0, 1) <= 0) {
            return "0.0";
        } elseif (bccomp($score, 4.5, 1) > 0) {
            return "5.0";
        } elseif (bccomp($score, 4, 1) > 0) {
            return "4.5";
        } elseif (bccomp($score, 3.5, 1) > 0) {
            return "4.0";
        } elseif (bccomp($score, 3, 1) > 0) {
            return "3.5";
        } elseif (bccomp($score, 2.5, 1) > 0) {
            return "3.0";
        } elseif (bccomp($score, 2, 1) > 0) {
            return "2.5";
        } elseif (bccomp($score, 1.5, 1) >=0) {
            return "2.0";
        } elseif (bccomp($score, 1, 1) > 0) {
            return "1.5";
        } elseif (bccomp($score, 0.5, 1) > 0) {
            return "1.0";
        } else{
            return "0.5";
        }
    }
}