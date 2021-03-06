<?php

namespace app\common\model;


class SmsLogModel extends Base
{

    protected $pk = 'id';
    protected $table = "sms_log";

    /**
     * 记录发送短信日志
     * @param $areaCode
     * @param $mobile
     * @param $msgContent
     * @param $responseData
     * @param $scene
     * @return bool
     */
    public function sendCodeMS($areaCode, $mobile, $msgContent, $responseData, $scene)
    {
        $data = [];
        $data['area_code'] = $areaCode;
        $data['phone'] = $mobile;
        $data['scene'] = $scene;
        $data['content'] = json_encode($msgContent, JSON_UNESCAPED_UNICODE);
        $data['return_data'] = json_encode($responseData, JSON_UNESCAPED_UNICODE);
        $data['code'] = isset($msgContent['code']) ? $msgContent['code'] : "";
        $data['ip'] = request()->ip();
        return $this->data($data)->save();
    }

}