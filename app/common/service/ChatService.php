<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-14
 * Time: 14:35
 */

namespace app\common\service;

use app\common\helper\Redis;
use app\common\model\UserSetModel;

class ChatService extends Base
{
    public function init($user, $tUId, $chatType)
    {
        $redis = Redis::factory();
        $tUSet = UserSetService::getUserSetByUId($tUId, $redis);
    }
}