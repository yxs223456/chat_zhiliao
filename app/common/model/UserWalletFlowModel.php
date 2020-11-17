<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-18
 * Time: 10:43
 */

namespace app\common\model;

use app\common\enum\FlowTypeEnum;
use think\facade\Db;

class UserWalletFlowModel extends Base
{
    protected $table = "user_wallet_flow";

    protected $pk = 'id';

    /**
     * 纪录增加流水
     * @param $userId
     * @param $amount
     * @param $addType
     * @param $sourceId
     * @param $bBalance
     * @param $aBalance
     * @param $addUId int 增加流水相关用户ID
     * @param $logMsg
     */
    public static function addFlow($userId, $amount, $addType, $sourceId, $bBalance, $aBalance, $addUId, $logMsg)
    {
        Db::name("user_wallet_flow")->insert([
            "u_id" => $userId,
            "flow_type" => FlowTypeEnum::ADD,
            "amount" => $amount,
            "add_type" => $addType,
            "object_source_id" => $sourceId,
            "before_balance" => $bBalance,
            "after_balance" => $aBalance,
            "create_date" => date("Y-m-d"),
            "add_u_id" => $addUId,
            "log_msg" => $logMsg,
        ]);
    }

    /**
     * 纪录消耗流水
     * @param $userId
     * @param $amount
     * @param $reduceType
     * @param $sourceId
     * @param $bBalance
     * @param $aBalance
     * @param $logMsg
     */
    public static function reduceFlow($userId, $amount, $reduceType, $sourceId, $bBalance, $aBalance, $logMsg)
    {
        Db::name("user_wallet_flow")->insert([
            "u_id" => $userId,
            "flow_type" => FlowTypeEnum::REDUCE,
            "amount" => $amount,
            "reduce_type" => $reduceType,
            "object_source_id" => $sourceId,
            "before_balance" => $bBalance,
            "after_balance" => $aBalance,
            "log_msg" => $logMsg,
            "create_date" => date("Y-m-d"),
        ]);
    }
}