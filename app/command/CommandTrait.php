<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-06-24
 * Time: 11:19
 */

namespace app\command;

use app\common\helper\Redis;
use app\common\helper\SendMail;
use app\common\helper\WeChatWork;
use think\facade\Db;

trait CommandTrait
{
    private $maxAllowTime = 600;            //PHP脚本单次运行时间上限
    private $maxAllowMemory = 104857600;    //分配给当前 PHP 脚本的最大允许内存量 100M

    public function sendWeChatWorkMessage($message, $user = null)
    {
        $redis = Redis::factory();
        $key = md5(json_encode($message));
        $ret = getMailSendExp($key, $redis);
        if (empty($ret)) {
            setMailSendExp($key, $redis);
            WeChatWork::sendMessageToUser($message, $user);
        }
        $redis->close();
    }

    public function dbWrite($updateData = [], $insertAllData = [], $deleteData = [])
    {
        foreach ($updateData as $data) {
            $query = null;
            $query = Db::name($data["table"])->where($data["where"]);
            if (isset($data["inc"])) {
                foreach ($data["inc"] as $field => $step) {
                    $query->inc($field, $step);
                }
            }
            if (isset($data["dec"])) {
                foreach ($data["dec"] as $field => $step) {
                    $query->dec($field, $step);
                }
            }
            $query->update($data["updateFields"]);
        }

        foreach ($insertAllData as $tableName => $data) {
            Db::name($tableName)->insertAll($data);
        }

        foreach ($deleteData as $data) {
            Db::name($data["table"])->where($data["where"])->delete();
        }
    }
}