<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-28
 * Time: 10:21
 */

namespace app\common\model;

use app\common\enum\FlowTypeEnum;
use think\facade\Db;

class InviteRewardModel extends Base
{
    protected $table = 'invite_reward';

    protected $pk = 'id';

    // 获取邀请奖励，外层需开启数据库事务
    public function add($userId, $addType, $money, $sourceId = 0, $oldInviteReward = null)
    {
        if ($oldInviteReward == null) {
            $oldInviteReward = Db::name($this->table)->where("u_id", $userId)->lock(true)->find();
        }

        // 增加奖励金额
        Db::name($this->table)
            ->where("u_id", $userId)
            ->inc("amount", $money)
            ->inc("history_amount", $money)
            ->update();

        // 纪录流水日志
        $flowModel = new InviteRewardFlowModel();
        $flowData = [
            "u_id" => $userId,
            "flow_type" => FlowTypeEnum::ADD,
            "amount" => $money,
            "add_type" => $addType,
            "object_source_id" => $sourceId,
            "before_balance" => $oldInviteReward["amount"],
            "after_balance" => bcadd($money, $oldInviteReward["amount"], 2),
            "create_date" => date("Y-m-d"),
        ];
        Db::name($flowModel->getTable())->insert($flowData);
    }
}