<?php
define("REDIS_KEY_PREFIX", "chat_zhiliao:");


// 邮件发送纪录，缓存有效期6小时
function setMailSendExp($key, \Redis $redis)
{
    $key = REDIS_KEY_PREFIX . $key;
    $redis->setex($key, 21600, 1);
}

// 获取发送缓存查看是否已发送过，6个小时内相同数据只发送一次。
function getMailSendExp($key, \Redis $redis)
{
    $key = REDIS_KEY_PREFIX . $key;
    return $redis->get($key);
}

//缓存用户信息
function cacheUserInfoByToken(array $userInfo, Redis $redis, $oldToken = "")
{
    $key = REDIS_KEY_PREFIX . "userInfoByToken:" . $userInfo["token"];
    $redis->hMSet($key, $userInfo);
    //缓存有效期72小时
    $redis->expire($key, 259200);

    if ($oldToken != "") {
        $oldKey = REDIS_KEY_PREFIX . "userInfoByToken:" . $oldToken;
        $redis->del($oldKey);
    }
}

//通过token获取用户信息
function getUserInfoByToken($token, Redis $redis)
{
    if ($token == "") {
        return [];
    }
    $key = REDIS_KEY_PREFIX . "userInfoByToken:$token";
    return $redis->hGetAll($key);
}

// 缓存短信验证码
function setSmsCode(array $data, Redis $redis)
{
    $key = REDIS_KEY_PREFIX . "verifycode:" . $data['phone'];
    $redis->set($key, $data['code'], 300);
}

// 获取短信验证码
function getSmsCode($phone, Redis $redis)
{
    if (empty($phone)) {
        return '';
    }
    $key = REDIS_KEY_PREFIX . "verifycode:$phone";
    return $redis->get($key);
}

// 接口调用手机发送短信次数 一个手机号一天发5条
function getMobileSendMsgTimes($mobile, \Redis $redis)
{
    $key = REDIS_KEY_PREFIX . "mobileSendMsgTimes:$mobile";
    $times = $redis->incr($key);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 86400);// 缓存一天
    }
    return $times;
}

// 接口调用手机发送短信次数 一个ip一天发50条
function getIpSendMsgTimes($ip, \Redis $redis)
{
    $key = REDIS_KEY_PREFIX . "ipSendMsgTimes:$ip";
    $times = $redis->incr($key);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 86400);// 缓存一天
    }
    return $times;
}
