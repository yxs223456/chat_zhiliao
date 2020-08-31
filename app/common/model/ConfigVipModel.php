<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-31
 * Time: 10:45
 */

namespace app\common\model;

use app\common\enum\DbDataIsDeleteEnum;
use app\common\enum\VipIsSaleEnum;

class ConfigVipModel extends Base
{
    protected $table = "config_vip";

    protected $pk = 'id';

    public function getAll()
    {
        $data = $this
            ->where("is_sale", VipIsSaleEnum::YES)
            ->where("is_delete", DbDataIsDeleteEnum::NO)
            ->order("sort", "asc")
            ->select();
        if ($data) {
            return $data->toArray();
        } else {
            return [];
        }
    }
}