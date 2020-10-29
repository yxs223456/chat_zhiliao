<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-26
 * Time: 18:21
 */

namespace app\common\service;

use app\common\helper\Redis;
use think\facade\Db;

class FeedbackService extends Base
{

    /**
     * 提交意见反馈
     *
     * @param $user
     * @param $msg
     * @return int|string
     */
    public function post($user, $msg)
    {
        $userInfo = UserInfoService::getUserInfoById($user['id'], Redis::factory());
        $insert = [
            'u_id' => $user['id'],
            'name' => $userInfo['nickname'] ?? '',
            'content' => $msg
        ];
        return Db::name("feedback")->insert($insert);
    }
}