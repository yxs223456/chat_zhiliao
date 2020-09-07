<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-07
 * Time: 11:23
 */

namespace app\common\model;

use app\common\enum\DbDataIsDeleteEnum;
use app\common\enum\GiftIsSaleEnum;

class ConfigGiftModel extends Base
{
    protected $table = "config_gift";

    protected $pk = 'id';

    public function getAll()
    {
        $data = $this
            ->where("is_sale", GiftIsSaleEnum::YES)
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