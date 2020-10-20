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
}