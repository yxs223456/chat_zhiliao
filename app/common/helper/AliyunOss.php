<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/10/18
 * Time: 10:29
 */
namespace app\common\helper;

use OSS\OssClient;
use Sts\Request\V20150401\AssumeRoleRequest;
use think\facade\App;

include_once App::getRootPath() . "/extend/sts-server/aliyun-php-sdk-core/Config.php";

class AliyunOss
{
    protected static $accessKeyId = "";
    protected static $accessKeySecret = "";
    protected static $endpoint = "";
    protected static $bucket = "";
    protected static $roleArn = "";
    protected static $tokenExpireTime = 900;
    protected static $policyFile = "";
    protected static $regionId = "cn-hangzhou";

    private static function getConfig()
    {
        $config = config("account.oss");
        self::$accessKeyId = $config["key_id"];
        self::$accessKeySecret = $config["key_secret"];
        self::$endpoint = $config["endpoint"];
        self::$bucket = $config["bucket"];
        self::$roleArn = $config["role_arn"];
        self::$tokenExpireTime = $config["token_expire_time"];
        self::$regionId = $config["region_id"];
        self::$policyFile = app()->getRootPath() . "/extend/" . trim($config["policy_file"], "/");
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

    /**
     * 前端上传获取token
     *
     * @return array
     */
    public static function getToken()
    {
        // 加载配置文件
        self::getConfig();

        $policy = read_file(self::$policyFile);
        $iClientProfile = \DefaultProfile::getProfile(self::$regionId, self::$accessKeyId, self::$accessKeySecret);
        $client = new \DefaultAcsClient($iClientProfile);

        $request = new AssumeRoleRequest();
        $request->setRoleSessionName("client_name");
        $request->setRoleArn(self::$roleArn);
        $request->setPolicy($policy);
        $request->setDurationSeconds(self::$tokenExpireTime);
        $response = $client->doAction($request);

        $rows = array();
        $body = $response->getBody();
        $content = json_decode($body);

        if ($response->getStatus() == 200) {
            $rows['StatusCode'] = 200;
            $rows['AccessKeyId'] = $content->Credentials->AccessKeyId;
            $rows['AccessKeySecret'] = $content->Credentials->AccessKeySecret;
            $rows['Expiration'] = $content->Credentials->Expiration;
            $rows['SecurityToken'] = $content->Credentials->SecurityToken;
        } else {
            $rows['StatusCode'] = 500;
            $rows['ErrorCode'] = $content->Code;
            $rows['ErrorMessage'] = $content->Message;
        }
        return $rows;
    }
}