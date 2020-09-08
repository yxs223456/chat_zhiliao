<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-08
 * Time: 14:02
 */

namespace app\v1\controller;

class Chat extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ]
    ];
}