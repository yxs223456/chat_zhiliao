<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/10/18
 * Time: 10:29
 */
namespace app\common\helper;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
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
    protected static $regionId = "cn-beijing";
    protected static $pipelineId = "";
    protected static $templateId= "";
    protected static $ossLocation= "oss-cn-beijing";

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
        self::$pipelineId = $config["pipeline_id"];
        self::$templateId = $config["template_id"];
        self::$ossLocation = $config["oss_location"];
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

//        $policy = read_file(self::$policyFile);
        $iClientProfile = \DefaultProfile::getProfile(self::$regionId, self::$accessKeyId, self::$accessKeySecret);
        $client = new \DefaultAcsClient($iClientProfile);

        $request = new AssumeRoleRequest();
        $request->setRoleSessionName("client_name");
        $request->setRoleArn(self::$roleArn);
//        $request->setPolicy($policy);
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

    public static function getToken1()
    {
        // 加载配置文件
        self::getConfig();
        AlibabaCloud::accessKeyClient(self::$accessKeyId, self::$accessKeySecret)
            ->regionId(self::$regionId)
            ->asDefaultClient();

        try {
            $result = AlibabaCloud::rpc()
                ->product('Sts')
                 ->scheme('https') // https | http
                ->version('2015-04-01')
                ->action('AssumeRole')
                ->method('POST')
                ->host('sts.aliyuncs.com')
                ->options([
                    'query' => [
                        'RegionId' => self::$regionId,
                        'RoleArn' => self::$roleArn,
                        'RoleSessionName' => "chat_zhiliao",
                        'DurationSeconds' => self::$tokenExpireTime
                    ],
                ])
                ->request();
            return $result->toArray();
        } catch (ClientException $e) {
            echo $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            echo $e->getErrorMessage() . PHP_EOL;
        }
    }

    public static function mtsRequest()
    {
        // 加载配置文件
        self::getConfig();
        AlibabaCloud::accessKeyClient(self::$accessKeyId, self::$accessKeySecret)
            ->regionId(self::$regionId)
            ->asDefaultClient();

        try {

            $result = AlibabaCloud::rpc()
                ->product('Mts')
                ->scheme('https') // https | http
                ->version('2014-06-18')
                ->action('SubmitJobs')
                ->method('POST')
                ->host('mts.cn-beijing.aliyuncs.com')
                ->debug(true)
                ->options([
                    'query' => [
                        'Input' => json_encode([
                            'Location' => self::$ossLocation,
                            'Bucket' => self::$bucket,
                            "Object" => urlencode("1602656808842121.mp4")
                        ]),
                        'Outputs' => json_encode([
                            [
                                'OutputObject' => urlencode("1602656808842121-test.mp4"),
                                'TemplateId' => self::$templateId
                            ]
                        ]),
                        'OutputBucket' => self::$bucket,
                        'OutputLocation' => self::$ossLocation,
                        'PipelineId' => self::$pipelineId,
                    ]
                ])
                ->request();
            return $result->toArray();
        } catch (ClientException $e) {
            echo $e->getErrorMessage() . PHP_EOL;
        } catch (ServerException $e) {
            echo $e->getErrorMessage() . PHP_EOL;
        }

    }
}