<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-18
 * Time: 10:43
 */

namespace app\common\model;

use think\facade\Db;

class UserIncomeLogModel extends Base
{
    protected $table = "user_income_log";

    protected $pk = 'id';

    /**
     * 添加一条收入纪录
     * @param $userId
     * @param $amount
     * @param $addType
     * @param $sourceId
     * @param $logMsg
     * @param $bonusRate
     */
    public static function addLog($userId, $amount, $addType, $sourceId, $logMsg, $bonusRate)
    {
        Db::name("user_income_log")->insert([
            "u_id" => $userId,
            "type" => $addType,
            "amount" => $amount,
            "msg" => $logMsg,
            "bonus_rate" => $bonusRate,
            "source_id" => $sourceId,
        ]);
    }
}