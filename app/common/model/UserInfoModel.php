<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-31
 * Time: 10:32
 */

namespace app\common\model;

class UserInfoModel extends Base
{
    protected $table = "user_info";

    protected $pk = 'id';

    public function findByUId($uId)
    {
        return $this->where("u_id", $uId)->find();
    }
}