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
     */
    public static function addFlow($userId, $amount, $addType, $sourceId, $bBalance, $aBalance)
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
     */
    public static function reduceFlow($userId, $amount, $reduceType, $sourceId, $bBalance, $aBalance)
    {
        Db::name("user_wallet_flow")->insert([
            "u_id" => $userId,
            "flow_type" => FlowTypeEnum::REDUCE,
            "amount" => $amount,
            "reduce_type" => $reduceType,
            "object_source_id" => $sourceId,
            "before_balance" => $bBalance,
            "after_balance" => $aBalance,
            "create_date" => date("Y-m-d"),
        ]);
    }
}