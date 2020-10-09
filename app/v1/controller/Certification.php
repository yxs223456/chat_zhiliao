<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-09
 * Time: 11:20
 */

namespace app\v1\controller;

use app\common\AppException;
use app\common\service\CertificationService;

class Certification extends Base
{
    protected $beforeActionList = [
        "getUser" => [
            "except" => "",
        ],
        "checkSex" => [
            "except" => "",
        ]
    ];

    /**
     * 提交自拍认证
     */
    public function submit()
    {
        $request = $this->query["content"];
        $certificationImage = $request["certification_image"]??"";
        if (empty($certificationImage)) {
            throw AppException::factory(AppException::QUERY_PARAMS_ERROR);
        }

        $user = $this->query["user"];
        $service = new CertificationService();
        $returnData = $service->submit($user, $certificationImage);

        return $this->jsonResponse($returnData);
    }
}
