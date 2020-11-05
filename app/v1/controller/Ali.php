<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-27
 * Time: 16:26
 */

namespace app\v1\controller;


use app\BaseController;
use think\facade\Log;

class Ali extends BaseController
{
    public function notice()
    {
        // 1. get the headers and check the signature
        $tmpHeaders = array();
        $headers = $this->getallheaders();
        foreach ($headers as $key => $value) {
            if (0 === strpos($key, 'x-mns-')) {
                $tmpHeaders[$key] = $value;
            }
        }
        ksort($tmpHeaders);
        $canonicalizedMNSHeaders = implode("\n", array_map(function ($v, $k) {
            return $k . ":" . $v;
        }, $tmpHeaders, array_keys($tmpHeaders)));

        $method = $_SERVER['REQUEST_METHOD'];
        $canonicalizedResource = $_SERVER['REQUEST_URI'];
        error_log($canonicalizedResource);

        $contentMd5 = '';
        if (array_key_exists('Content-MD5', $headers)) {
            $contentMd5 = $headers['Content-MD5'];
        } else if (array_key_exists('Content-md5', $headers)) {
            $contentMd5 = $headers['Content-md5'];
        }

        $contentType = '';
        if (array_key_exists('Content-Type', $headers)) {
            $contentType = $headers['Content-Type'];
        }
        $date = $headers['Date'];


        $stringToSign = strtoupper($method) . "\n" . $contentMd5 . "\n" . $contentType . "\n" . $date . "\n" . $canonicalizedMNSHeaders . "\n" . $canonicalizedResource;
        error_log($stringToSign);

        $publicKeyURL = base64_decode($headers['x-mns-signing-cert-url']);
        $publicKey = $this->getByUrl($publicKeyURL);
        $signature = $headers['Authorization'];

        $pass = $this->verify($stringToSign, $signature, $publicKey);
        if (!$pass) {
            error_log("verify signature fail");
            http_response_code(400);
            return;
        }

        // 2. now parse the content
        $content = file_get_contents("php://input");
        error_log($content);

        if (!empty($contentMd5) && $contentMd5 != base64_encode(md5($content))) {
            error_log("md5 mismatch");
            http_response_code(401);
            return;
        }

        $msg = new \SimpleXMLElement($content);
        Log::info("mts", $msg);
        http_response_code(200);
    }

    private function getByUrl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        $output = curl_exec($ch);

        curl_close($ch);

        return $output;
    }

    private function verify($data, $signature, $pubKey)
    {
        $res = openssl_get_publickey($pubKey);
        $result = (bool)openssl_verify($data, base64_decode($signature), $res);
        openssl_free_key($res);

        return $result;
    }


    private function getallheaders()
    {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}