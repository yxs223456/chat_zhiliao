<?php
/**
 * Created by PhpStorm.
 * User: yanglichao
 * Date: 2020-11-12
 * Time: 15:15
 */
namespace app\common\service;

use app\common\AppException;
use app\common\helper\JPush;

class JPushService extends Base
{
    /**
     * 用户离线推送消息
     * @param $userId int 推送目标用户ID
     * @param $title string 标题
     * @param $message string 内容
     * @param $extra array 其他业务参数
     * @return array
     * @throws AppException
     * @throws \Throwable
     */
    public function pushMessage($userId, $title, $message, $extra = [])
    {
        $userInfo = UserInfoService::getUserInfoById($userId);
        $deviceNo = $userInfo["device_no"] ?? "";
        JPush::pushOne($deviceNo, $message, $title, $extra);
    }
}