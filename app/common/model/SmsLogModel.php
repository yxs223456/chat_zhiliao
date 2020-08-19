<?php

namespace app\common\model;


class SmsLogModel extends Base
{

    protected $pk = 'id';
    protected $table = "sms_log";

    /**
     * 记录发送短信日志
     *
     * @param $areaCode
     * @param $mobile
     * @param $msgContent
     * @param $responseData
     * @return bool
     */
    public function sendCodeMS($areaCode, $mobile, $msgContent, $responseData)
    {
        $data = [];
        $data['area_code'] = $areaCode;
        $data['phone'] = $mobile;
        $data['content'] = json_encode($msgContent);
        $data['return_data'] = json_encode($responseData);
        $data['code'] = isset($msgContent['code']) ? $msgContent['code'] : "";
        $data['ip'] = request()->ip();
        $data['create_time'] = time();
        return $this->data($data)->save();
    }

}