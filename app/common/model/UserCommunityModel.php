<?php

namespace app\common\model;

class UserCommunityModel extends Base
{
    protected $table = "user_community";

    protected $pk = 'id';

    public function findByUId($uId)
    {
        return $this->where("u_id", $uId)->find();
    }
}