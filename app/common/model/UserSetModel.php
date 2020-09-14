<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-14
 * Time: 14:42
 */
namespace app\common\model;

class UserSetModel extends Base
{
    protected $table = "user_set";

    protected $pk = 'id';

    public function findByUId($uId)
    {
        return $this->where("u_id", $uId)->find();
    }
}