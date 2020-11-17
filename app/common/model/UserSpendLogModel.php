<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-18
 * Time: 10:43
 */

namespace app\common\model;

use think\facade\Db;

class UserSpendLogModel extends Base
{
    protected $table = "user_spend_log";

    protected $pk = 'id';

    /**
     * 添加一条支出纪录
     * @param $userId
     * @param $amount
     * @param $spendType
     * @param $sourceId
     * @param $logMsg
     */
    public static function addLog($userId, $amount, $spendType, $sourceId, $logMsg)
    {
        Db::name("user_spend_log")->insert([
            "u_id" => $userId,
            "type" => $spendType,
            "amount" => $amount,
            "msg" => $logMsg,
            "source_id" => $sourceId,
        ]);
    }
}