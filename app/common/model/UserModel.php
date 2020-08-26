<?php

namespace app\common\model;

class UserModel extends Base
{
    protected $table = "user";

    protected $pk = 'id';

    public function findByUserNumber($userNumber)
    {
        return $this->where("user_number", $userNumber)->find();
    }

    public function findByToken($token)
    {
        return $this->where("token", $token)->find();
    }

    public function findByMobilePhone($mobilePhone)
    {
        return $this->where("mobile_phone", $mobilePhone)->find();
    }
}