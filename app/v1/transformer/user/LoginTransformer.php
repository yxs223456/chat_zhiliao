<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\user;

use app\common\transformer\TransformerAbstract;

class LoginTransformer extends TransformerAbstract
{

    public function __construct(array $params = null)
    {
        parent::__construct($params);
    }

    public function transformData(array $data): array
    {
        return [
            "user_number" => (string) $data["user_number"],
            "token" => (string) $data["token"],
            "sex" => (int) $data["sex"],
            "rc_token" => (string) $data["rc_token"],
        ];
    }
}