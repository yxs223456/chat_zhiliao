<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/10/18
 * Time: 10:29
 */
namespace app\common\helper;

use OSS\OssClient;

class AliyunOss
{
    protected static $accessKeyId = "";
    protected static $accessKeySecret = "";
    protected static $endpoint = "";
    protected static $bucket = "";

    private static function getConfig()
    {
        $config = config("account.oss");
        self::$accessKeyId = $config["key_id"];
        self::$accessKeySecret = $config["key_secret"];
        self::$endpoint = $config["endpoint"];
        self::$bucket = $config["bucket"];
    }

    /**
     * @param $filename string 文件名称
     * @param string $content The content object
     * @return string
     * @throws \OSS\Core\OssException
     */
    public static function putObject($filename, $content)
    {
        self::getConfig();

        $ossClient = new OssClient(self::$accessKeyId, self::$accessKeySecret, self::$endpoint);
        $result = $ossClient->putObject(self::$bucket, $filename, $content);
        if (isset($result['info']['url']) && strpos($result['info']['url'], "https") === false) {
            $result['info']['url'] = str_replace("http", "https", $result['info']['url']);
            return $result['info']['url'];
        } else {
            return "";
        }
    }
}