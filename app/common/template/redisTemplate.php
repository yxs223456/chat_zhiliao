<?php
/**
 * redis key 统一前缀
 */
define("REDIS_KEY_PREFIX", "chat_zhiliao:");

/**
 * banner 列表
 */
define("REDIS_KEY_BANNER_LIST_BY_POSITION", REDIS_KEY_PREFIX . "bannerListByPosition:");

//缓存banner列表，有效期1小时
function cacheBannerListByPosition(array $list, $position, \Redis $redis)
{
    $key = REDIS_KEY_BANNER_LIST_BY_POSITION . $position;
    $redis->setex($key, 3600, json_encode($list));
}

// 获取banner列表
function getBannerListByPosition($position, \Redis $redis)
{
    $key = REDIS_KEY_BANNER_LIST_BY_POSITION . $position;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
}

/**
 * vip 套餐
 */
define("REDIS_KEY_VIP_CONFIG", REDIS_KEY_PREFIX . "vipConfig");

//缓存vip套餐配置，有效期3天
function cacheVipConfig(array $vipConfig, \Redis $redis)
{
    $redis->setex(REDIS_KEY_VIP_CONFIG, 259200, json_encode($vipConfig));
}

//获取vip套餐配置
function getVipConfigByCache(\Redis $redis)
{
    $data = $redis->get(REDIS_KEY_VIP_CONFIG);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
}

/**
 * 企业微信access_token
 */
define("REDIS_KEY_WE_CHAT_WORK_ACCESS_TOKEN", REDIS_KEY_PREFIX . "weChatWorkAccessToken");
function getWeChatWorkAccessToken(\Redis $redis)
{
    return $redis->get(REDIS_KEY_WE_CHAT_WORK_ACCESS_TOKEN);
}

//缓存企业微信access_token
function setWeChatWorkAccessToken($accessToken, $expire, \Redis $redis)
{
    $redis->setex(REDIS_KEY_WE_CHAT_WORK_ACCESS_TOKEN, $expire, $accessToken);
}

/**
 * 报警邮件发送纪录
 */
define("REDIS_KEY_MAIL_SEND_EXP", REDIS_KEY_PREFIX . "mailSendExp:");

// 邮件发送纪录，缓存有效期6小时
function setMailSendExp($key, \Redis $redis)
{
    $key = REDIS_KEY_MAIL_SEND_EXP . $key;
    $redis->setex($key, 21600, 1);
}

// 获取发送缓存查看是否已发送过，6个小时内相同数据只发送一次。
function getMailSendExp($key, \Redis $redis)
{
    $key = REDIS_KEY_MAIL_SEND_EXP . $key;
    return $redis->get($key);
}


/**
 * 缓存用户信息 token
 */
define("REDIS_USER_INFO_BY_TOKEN", REDIS_KEY_PREFIX . "userInfoByToken:");
//缓存用户信息
function cacheUserInfoByToken(array $userInfo, Redis $redis, $oldToken = "")
{
    $key = REDIS_USER_INFO_BY_TOKEN . $userInfo["token"];
    $redis->hMSet($key, $userInfo);
    //缓存有效期72小时
    $redis->expire($key, 259200);

    if ($oldToken != "") {
        $oldKey = REDIS_USER_INFO_BY_TOKEN . $oldToken;
        $redis->del($oldKey);
    }
}

//通过token获取用户信息
function getUserInfoByToken($token, Redis $redis)
{
    if ($token == "") {
        return [];
    }
    $key = REDIS_USER_INFO_BY_TOKEN . $token;
    return $redis->hGetAll($key);
}

/**
 * 短信验证码
 */
// 缓存短信验证码
define("REDIS_SMS_CODE", REDIS_KEY_PREFIX . 'verifyCode:');
function setSmsCode(array $data, Redis $redis)
{
    $key = REDIS_SMS_CODE . $data["scene"] . ":" . $data['phone'];
    $redis->set($key, $data['code'], 1800);
}

// 获取短信验证码
function getSmsCode($phone, $scene, Redis $redis)
{
    if (empty($phone)) {
        return '';
    }
    $key = REDIS_SMS_CODE . $scene . ":" . $phone;
    return $redis->get($key);
}

// 接口调用手机发送短信次数 一个手机号一天发5条
define("REDIS_MOBILE_SEND_MSG_TIMES", REDIS_KEY_PREFIX . 'mobileSendMsgTimes:');
function getMobileSendMsgTimes($mobile, \Redis $redis)
{
    $key = REDIS_MOBILE_SEND_MSG_TIMES . $mobile;
    $times = $redis->incr($key);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 86400);// 缓存一天
    }
    return $times;
}

// 接口调用手机发送短信次数 一个ip一天发50条
define("REDIS_IP_SEND_MSG_TIMES", REDIS_KEY_PREFIX . 'ipSendMsgTimes:');
function getIpSendMsgTimes($ip, \Redis $redis)
{
    $key = REDIS_IP_SEND_MSG_TIMES . $ip;
    $times = $redis->incr($key);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 86400);// 缓存一天
    }
    return $times;
}

/**
 * 动态详情缓存相关
 */
// 动态缓存
define("REDIS_USER_DYNAMIC_INFO", REDIS_KEY_PREFIX . 'userDynamicInfo:');
function cacheUserDynamicInfo($id, $data, \Redis $redis)
{
    $key = REDIS_USER_DYNAMIC_INFO . $id;
    $redis->set($key, json_encode($data), 3600);
}

// 动态获取
function getUserDynamicInfo($id, \Redis $redis)
{
    $key = REDIS_USER_DYNAMIC_INFO . $id;
    $data = $redis->get($key);
    return $data ? json_decode($data, true) : null;
}

// 动态删除
function deleteUserDynamicInfo($id, \Redis $redis)
{
    $key = REDIS_USER_DYNAMIC_INFO . $id;
    return $redis->del($key);
}

/**
 * 最新动态列表缓存相关(并发)
 */
define("REDIS_NEWEST_DYNAMIC_INFO", REDIS_KEY_PREFIX . 'newestDynamicInfo:');
// 缓存数据
function cacheNewestDynamicInfo($sex, $startId, $pageSize, $data, \Redis $redis)
{
    $key = REDIS_NEWEST_DYNAMIC_INFO . $sex . ":" . $startId . ":" . $pageSize;
    $redis->set($key, json_encode($data), 3600);
}

// 获取缓存
function getNewestDynamicInfo($sex, $startId, $pageSize, \Redis $redis)
{
    $key = REDIS_NEWEST_DYNAMIC_INFO . $sex . ":" . $startId . ":" . $pageSize;
    $data = $redis->get($key);
    return $data ? json_decode($data, true) : null;
}

// 删除所有缓存
function deleteNewestDynamicInfo(\Redis $redis)
{
    $keys = $redis->keys(REDIS_NEWEST_DYNAMIC_INFO . "*");
    $redis->del($keys);
}

// 删除首页缓存
function deleteFirstNewestDynamicInfo($sex, $pageSize, \Redis $redis)
{
    $key = REDIS_NEWEST_DYNAMIC_INFO . $sex . ":0:" . $pageSize;
    $redis->del($key);
}

/**
 * 用户动态列表缓存相关(并发)
 */
define("REDIS_PERSONAL_DYNAMIC_INFO", REDIS_KEY_PREFIX . 'personalDynamicInfo:');
// 缓存数据
function cachePersonalDynamicInfo($userId, $startId, $pageSize, $data, \Redis $redis)
{
    $key = REDIS_PERSONAL_DYNAMIC_INFO . $userId . ":" . $startId . ":" . $pageSize;
    $redis->set($key, json_encode($data), 3600);
}

// 获取缓存
function getPersonalDynamicInfo($userId, $startId, $pageSize, \Redis $redis)
{
    $key = REDIS_PERSONAL_DYNAMIC_INFO . $userId . ":" . $startId . ":" . $pageSize;
    $data = $redis->get($key);
    return $data ? json_decode($data, true) : null;
}

// 删除所有缓存
function deletePersonalDynamicInfo($userId, \Redis $redis)
{
    $keys = $redis->keys(REDIS_PERSONAL_DYNAMIC_INFO . $userId . "*");
    $redis->del($keys);
}

// 删除首页缓存
function deleteFirstPersonalDynamicInfo($userId, $pageSize, \Redis $redis)
{
    $key = REDIS_PERSONAL_DYNAMIC_INFO . $userId . ":0:" . $pageSize;
    $redis->del($key);
}

/**
 * 用户关注用户动态列表缓存相关(无并发)
 */
define("REDIS_USER_FOLLOW_DYNAMIC_INFO", REDIS_KEY_PREFIX . 'userFollowDynamicInfo:');
// 缓存数据
function cacheUserFollowDynamicInfo($userId, $startId, $pageSize, $data, \Redis $redis)
{
    $key = REDIS_USER_FOLLOW_DYNAMIC_INFO . $userId . ":" . $startId . ":" . $pageSize;
    $redis->set($key, json_encode($data), 3600);
}

// 获取缓存
function getUserFollowDynamicInfo($userId, $startId, $pageSize, \Redis $redis)
{
    $key = REDIS_USER_FOLLOW_DYNAMIC_INFO . $userId . ":" . $startId . ":" . $pageSize;
    $data = $redis->get($key);
    return $data ? json_decode($data, true) : null;
}

// 删除所有缓存
function deleteUserFollowDynamicInfo($userId, \Redis $redis)
{
    $keys = $redis->keys(REDIS_USER_FOLLOW_DYNAMIC_INFO . $userId . "*");
    $redis->del($keys);
}

/**
 * 附近用户动态列表缓存相关(无并发)
 */
define("REDIS_NEAR_USER_DYNAMIC_INFO", REDIS_KEY_PREFIX . 'nearUserDynamicInfo:');
// 缓存数据
function cacheNearUserDynamicInfo($userId, $pageSize, $data, \Redis $redis)
{
    $key = REDIS_NEAR_USER_DYNAMIC_INFO . $userId . ":" . $pageSize;
    $redis->set($key, json_encode($data), 3600);
}

// 获取缓存
function getNearUserDynamicInfo($userId, $pageSize, \Redis $redis)
{
    $key = REDIS_NEAR_USER_DYNAMIC_INFO . $userId . ":" . $pageSize;
    $data = $redis->get($key);
    return $data ? json_decode($data, true) : null;
}

// 删除所有缓存
function deleteNearUserDynamicInfo($userId, $pageSize, \Redis $redis)
{
    $redis->del(REDIS_NEAR_USER_DYNAMIC_INFO . $userId . ":" . $pageSize);
}

// 用户5分钟内只能刷新一次 加锁
function setNearUserDynamicInfoLock($userId, \Redis $redis)
{
    $key = REDIS_NEAR_USER_DYNAMIC_INFO . 'Lock' . $userId;
    $redis->setex($key, 300, 1);
}

// 获取用户刷新附近人动态的锁
function getNearUserDynamicInfoLock($userId, Redis $redis)
{
    $key = REDIS_NEAR_USER_DYNAMIC_INFO . 'Lock' . $userId;
    return $redis->get($key);
}

/**
 * 附近用户geohash缓存相关(并发)
 */
define("REDIS_USER_LONG_LAT_INFO", REDIS_KEY_PREFIX . 'userLongLatInfo:');
// 缓存数据
function cacheUserLongLatInfo($userId, $lat, $long, \Redis $redis)
{
    $geotools = new \League\Geotools\Geotools();
    $coordToGeohash = new \League\Geotools\Coordinate\Coordinate([$lat, $long]);
    $geoHash = $geotools->geohash()->encode($coordToGeohash, 12)->getGeohash();
    $key = REDIS_USER_LONG_LAT_INFO . '|' . $geoHash . "|" . $userId;
    $redis->set($key, $userId, 86400);
}

// 获取附近用户坐标缓存
function getNearUserLongLatInfo($lat, $long, \Redis $redis)
{
    $geotools = new \League\Geotools\Geotools();
    $coordToGeohash = new \League\Geotools\Coordinate\Coordinate([$lat, $long]);
    $geoHash = $geotools->geohash()->encode($coordToGeohash, 1)->getGeohash();
    $key = REDIS_USER_LONG_LAT_INFO . '|' . $geoHash . "*";
    $keys = $redis->keys($key);
    if (empty($keys)) {
        return null;
    }
    $ret = [];
    foreach ($keys as $item) {
        $keyArr = explode("|",$item);
        $ret[$keyArr[1]."|".$keyArr[2]] = $redis->get($item);
    }
    return $ret;
}

// 获取某个用户坐标缓存
function getUserLongLatInfo($userId, \Redis $redis)
{
    $key = REDIS_USER_LONG_LAT_INFO . "|*|" . $userId;
    $keys = $redis->keys($key);
    if (empty($keys)) {
        return null;
    }
    $first = array_shift($keys);
    $keyArr = explode("|", $first);
    return $keyArr[1];
}

// 删除所有用户坐标缓存
function deleteUserLongLatInfo(\Redis $redis)
{
    $keys = $redis->keys(REDIS_USER_LONG_LAT_INFO . "*");
    $redis->del($keys);
}

