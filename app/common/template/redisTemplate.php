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
 * 礼物 详情
 */
define("REDIS_KEY_GIFT_BY_ID", REDIS_KEY_PREFIX . "giftById:");

// 缓存礼物详情，有效期3天
function cacheGiftById(array $giftInfo, \Redis $redis)
{
    $key = REDIS_KEY_GIFT_BY_ID . $giftInfo["id"];
    $redis->setex($key, 259200, json_encode($giftInfo));
}

// 获取礼物详情
function getGiftByIdOnRedis($giftId, \Redis $redis)
{
    $key = REDIS_KEY_GIFT_BY_ID . $giftId;

    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
}

/**
 *  用户今日免费接听时长
 */
define("REDIS_KEY_CHAT_FREE_MINUTES", REDIS_KEY_PREFIX . "chatFreeMinutes:");

// 用户今日免费接听数
function getUserChatFreeMinutes($userId, $date, \Redis $redis)
{
    $key = REDIS_KEY_CHAT_FREE_MINUTES . $date . ":$userId";
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
}

// 更新用户今日免费接听数
function cacheUserChatFreeMinutes($userId, $date, array $info, \Redis $redis)
{
    $key = REDIS_KEY_CHAT_FREE_MINUTES . $date . ":$userId";
    $redis->setex($key, 86400, json_encode($info));
}

/**
 * 礼物 配置
 */
define("REDIS_KEY_ALL_SALE_GIFT", REDIS_KEY_PREFIX . "allSaleGift");

// 缓存所有上架的礼物，有效期3天
function cacheAllSaleGift(array $allSaleGift, \Redis $redis)
{
    $redis->setex(REDIS_KEY_ALL_SALE_GIFT, 259200, json_encode($allSaleGift));
}

// 获取所有上架的礼物
function getAllSaleGift(\Redis $redis)
{
    $data = $redis->get(REDIS_KEY_ALL_SALE_GIFT);
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
 * 缓存用户信息 user_number
 */
define("REDIS_USER_INFO_BY_NUMBER", REDIS_KEY_PREFIX . "userInfoByNumber:");
//缓存用户信息，有效期3天
function cacheUserInfoByNumber(array $userInfo, Redis $redis)
{
    $key = REDIS_USER_INFO_BY_NUMBER . $userInfo["user_number"];
    $redis->setex($key, 259200, json_encode($userInfo));
}

//通过user_number获取用户信息
function getUserInfoByNumber($userNumber, Redis $redis)
{
    $key = REDIS_USER_INFO_BY_NUMBER . $userNumber;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
}

/**
 * 缓存用户设置信息 user_id
 */
define("REDIS_USER_SET_BY_UID", REDIS_KEY_PREFIX . "userSetByUId:");
//缓存用户设置信息，有效期3天
function cacheUserSetByUId(array $userSet, Redis $redis)
{
    $key = REDIS_USER_SET_BY_UID . $userSet["u_id"];
    $redis->setex($key, 259200, json_encode($userSet));
}

//通过user_id获取用户设置信息
function getUserSetByUId($userId, Redis $redis)
{
    $key = REDIS_USER_SET_BY_UID . $userId;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
}

// 通过user_id删除用户设置信息
function deleteUserSetByUId($userId, Redis $redis)
{
    $key = REDIS_USER_SET_BY_UID . $userId;
    $redis->del($key);
}

/**
 * 缓存用户信息 user_id
 */
define("REDIS_USER_INFO_BY_ID", REDIS_KEY_PREFIX . "userInfoById:");
//缓存用户信息，有效期3天
function cacheUserInfoById(array $userInfo, Redis $redis)
{
    $key = REDIS_USER_INFO_BY_ID . $userInfo["id"];
    $redis->setex($key, 259200, json_encode($userInfo));
}

//通过user_id获取用户信息
function getUserInfoById($userId, Redis $redis)
{
    $key = REDIS_USER_INFO_BY_ID . $userId;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
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
        $keyArr = explode("|", $item);
        $ret[$keyArr[1] . "|" . $keyArr[2]] = $keyArr[2];
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

// 删除一个用户坐标缓存
function deleteUserLongLatInfoByUserId($userId, \Redis $redis)
{
    $keys = $redis->keys(REDIS_USER_LONG_LAT_INFO . "|*|" . $userId);
    $redis->del($keys);
}

/**
 * 附近用户距离排序的有序集合(无并发)
 */
define("REDIS_NEAR_USER_SORT_SET", REDIS_KEY_PREFIX . "nearUserSortSet:");
// 缓存附近用户集合 一天
function cacheNearUserSortSet($userId, \Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . $userId;
    $geoHash = getUserLongLatInfo($userId, $redis);
    if (empty($geoHash)) {
        return;
    }
    $searchKey = REDIS_USER_LONG_LAT_INFO . "|" . substr($geoHash, 0, 1) . "*";
    $keys = $redis->keys($searchKey);
    if (empty($keys)) {
        return;
    }

    // 计算距离放入有序集合
    foreach ($keys as $item) {
        $keyArr = explode("|", $item);
        // 排除当前用户自己
        if ($keyArr[2] == $userId) {
            continue;
        }
        $geotools = app('geotools');
        $decodedDynamicUser = $geotools->geohash()->decode($keyArr[1]);
        $userLat = $decodedDynamicUser->getCoordinate()->getLatitude();
        $userLong = $decodedDynamicUser->getCoordinate()->getLongitude();
        $dynamicCoordUser = new \League\Geotools\Coordinate\Coordinate([$userLat, $userLong]);

        $loginUser = $geotools->geohash()->decode($geoHash);
        $lat = $loginUser->getCoordinate()->getLatitude();
        $long = $loginUser->getCoordinate()->getLongitude();
        $loginCoordUser = new \League\Geotools\Coordinate\Coordinate([$lat, $long]);

        $distance = $geotools->distance()->setFrom($dynamicCoordUser)->setTo($loginCoordUser);
        $km = sprintf("%.2f", $distance->in('km')->haversine());

        $redis->zAdd($key, $km, $keyArr[2]);
    }
    $redis->expire($key, 86400); // 缓存一天
}

// 获取附近用户ID有序集合
function getNearUserSortSet($userId, $pageNum, $pageSize, \Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . $userId;
    return $redis->zRange($key, ($pageNum - 1) * $pageSize, $pageSize, true);
}

// 用户5分钟内只能刷新一次 加锁
function setNearUserSortSetLock($userId, \Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . 'Lock' . $userId;
    $redis->setex($key, 300, 1);
}

// 获取用户刷新附近人动态的锁
function getNearUserSortSetLock($userId, Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . 'Lock' . $userId;
    return $redis->get($key);
}

// 缓存附近用户分页列表数据 // 一天
function cacheNearUserPage($userId, $pageNum, $pageSize, $data, \Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . $userId . ":" . $pageNum . ":" . $pageSize;
    $redis->set($key, json_encode($data), 86400);
}

// 获取附近用户分页列表缓存
function getNearUserPage($userId, $pageNum, $pageSize, \Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . $userId . ":" . $pageNum . ":" . $pageSize;
    $data = $redis->get($key);
    return $data ? json_decode($data, true) : null;
}

// 删除用户附近缓存数据
function deleteNearUserCache($userId, \Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . $userId . "*";
    $keys = $redis->keys($key);
    $redis->del($keys);
}

/**
 * 用户关系关注用户列表缓存相关(无并发)
 */
define("REDIS_USER_RELATION_FOLLOW_INFO", REDIS_KEY_PREFIX . 'userRelationFollowInfo:');
// 缓存关注用户数据
function cacheUserRelationFollowInfo($userId, $startId, $pageSize, $data, \Redis $redis)
{
    $key = REDIS_USER_RELATION_FOLLOW_INFO . $userId . ":" . $startId . ":" . $pageSize;
    $redis->set($key, json_encode($data), 3600);
}

// 获取关注用户缓存
function getUserRelationFollowInfo($userId, $startId, $pageSize, \Redis $redis)
{
    $key = REDIS_USER_RELATION_FOLLOW_INFO . $userId . ":" . $startId . ":" . $pageSize;
    $data = $redis->get($key);
    return $data ? json_decode($data, true) : null;
}

// 删除关注用户数据所有缓存
function deleteUserRelationFollowInfo($userId, \Redis $redis)
{
    $keys = $redis->keys(REDIS_USER_RELATION_FOLLOW_INFO . $userId . "*");
    $redis->del($keys);
}

/**
 * 用户关系用户被关注列表缓存相关(无并发)
 */
define("REDIS_USER_RELATION_FANS_INFO", REDIS_KEY_PREFIX . 'userRelationFansInfo:');
// 缓存用户被关注数据
function cacheUserRelationFansInfo($userId, $startId, $pageSize, $data, \Redis $redis)
{
    $key = REDIS_USER_RELATION_FANS_INFO . $userId . ":" . $startId . ":" . $pageSize;
    $redis->set($key, json_encode($data), 3600);
}

// 获取用户被关注缓存
function getUserRelationFansInfo($userId, $startId, $pageSize, \Redis $redis)
{
    $key = REDIS_USER_RELATION_FANS_INFO . $userId . ":" . $startId . ":" . $pageSize;
    $data = $redis->get($key);
    return $data ? json_decode($data, true) : null;
}

// 删除用户被关注所有缓存
function deleteUserRelationFansInfo($userId, \Redis $redis)
{
    $keys = $redis->keys(REDIS_USER_RELATION_FANS_INFO . $userId . "*");
    $redis->del($keys);
}

/**
 * 用户关系用户好友列表缓存相关(无并发)
 */
define("REDIS_USER_RELATION_FRIEND_INFO", REDIS_KEY_PREFIX . 'userRelationFriendInfo:');
// 缓存用户好友数据
function cacheUserRelationFriendInfo($userId, $pageSize, $data, \Redis $redis)
{
    $key = REDIS_USER_RELATION_FRIEND_INFO . $userId . ":" . $pageSize;
    $redis->set($key, json_encode($data), 3600);
}

// 获取用户好友缓存
function getUserRelationFriendInfo($userId, $pageSize, \Redis $redis)
{
    $key = REDIS_USER_RELATION_FRIEND_INFO . $userId . ":" . $pageSize;
    $data = $redis->get($key);
    return $data ? json_decode($data, true) : null;
}

// 删除用户好友数据所有缓存
function deleteUserRelationFriendInfo($userId, \Redis $redis)
{
    $keys = $redis->keys(REDIS_USER_RELATION_FRIEND_INFO . $userId . "*");
    $redis->del($keys);
}

/**
 * 缓存用户info信息
 */
define("REDIS_USER_INFO_DATA_BY_UID", REDIS_KEY_PREFIX . "userInfoDataByUId:");
//缓存user_info数据，有效期3天
function cacheUserInfoDataByUId(array $userInfo, Redis $redis)
{
    $key = REDIS_USER_INFO_DATA_BY_UID . $userInfo["u_id"];
    $redis->setex($key, 259200, json_encode($userInfo));
}

//通过user_id获取用户info数据
function getUserInfoDataByUId($userId, Redis $redis)
{
    $key = REDIS_USER_INFO_DATA_BY_UID . $userId;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
}

// 通过user_id删除用户info数据
function deleteUserInfoDataByUId($userId, Redis $redis)
{
    $key = REDIS_USER_INFO_DATA_BY_UID . $userId;
    $redis->del($key);
}


/**
 * 缓存用户blackUserId信息
 */
define("REDIS_USER_BLACK_LIST_BY_UID", REDIS_KEY_PREFIX . "userBlackListByUId:");
//缓存black_list数据，有效期3天
function cacheUserBlackListByUId(array $blackUids, Redis $redis)
{
    $key = REDIS_USER_BLACK_LIST_BY_UID . $blackUids["userId"];
    $redis->setex($key, 259200, json_encode($blackUids));
}

//通过user_id获取用户黑名单数据
function getUserBlackListByUId($userId, Redis $redis)
{
    $key = REDIS_USER_BLACK_LIST_BY_UID . $userId;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
}

// 通过user_id删除用户黑名单数据
function deleteUserBlackListByUId($userId, Redis $redis)
{
    $key = REDIS_USER_BLACK_LIST_BY_UID . $userId;
    $redis->del($key);
}

/**
 * 缓存用户blackUserListData信息
 */
define("REDIS_USER_BLACK_LIST_DATA_BY_UID", REDIS_KEY_PREFIX . "userBlackListDataByUId:");
//缓存black_list数据，有效期3天
function cacheUserBlackListDataByUId(array $blackList, Redis $redis)
{
    $key = REDIS_USER_BLACK_LIST_DATA_BY_UID . $blackList["userId"];
    $redis->setex($key, 259200, json_encode($blackList));
}

//通过user_id获取用户黑名单数据
function getUserBlackListDataByUId($userId, Redis $redis)
{
    $key = REDIS_USER_BLACK_LIST_DATA_BY_UID . $userId;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
}

// 通过user_id删除用户黑名单数据
function deleteUserBlackListDataByUId($userId, Redis $redis)
{
    $key = REDIS_USER_BLACK_LIST_DATA_BY_UID . $userId;
    $redis->del($key);
}

/**
 * 缓存用户个人主页信息
 */
define("REDIS_USER_INDEX_DATA_BY_UID", REDIS_KEY_PREFIX . "userIndexDataByUId:");
//缓存用户个人主页数据，有效期2小时
function cacheUserIndexDataByUId($userId, array $data, Redis $redis)
{
    $key = REDIS_USER_INDEX_DATA_BY_UID . $userId;
    $redis->setex($key, 7200, json_encode($data));
}

//通过user_id获取用户主页数据
function getUserIndexDataByUId($userId, Redis $redis)
{
    $key = REDIS_USER_INDEX_DATA_BY_UID . $userId;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
}

// 通过user_id删除用户主页数据
function deleteUserIndexDataByUId($userId, Redis $redis)
{
    $key = REDIS_USER_INDEX_DATA_BY_UID . $userId;
    $redis->del($key);
}


/**
 * 缓存用户访客数据信息 每日访客的集合（去重复，减少数据库查询次数）
 */
define("REDIS_USER_VISITOR_DAY_SET", REDIS_KEY_PREFIX . "userVisitorDaySet:");
//缓存用户访客用户ID，有效期一天
function cacheUserVisitorIdData($userId, $visitorId, Redis $redis)
{
    $key = REDIS_USER_VISITOR_DAY_SET . $userId . ":" . date("Y-m-d");
    $redis->sadd($key, $visitorId);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 86400);// 缓存一天
    }
}

//查看当前用户今天是否已经访问过
function getUserVisitorExists($userId, $visitorId, Redis $redis)
{
    $key = REDIS_USER_VISITOR_DAY_SET . $userId . ":" . date("Y-m-d");
    return $redis->sIsMember($key, $visitorId);
}

/**
 * 用户访客分页数据
 */
define("REDIS_USER_VISITOR_PAGE_DATA", REDIS_KEY_PREFIX . "userVisitorPageData:");
function cacheUserVisitorPageData($userId, $page, $pageSize, $data, Redis $redis)
{
    $key = REDIS_USER_VISITOR_PAGE_DATA . $userId . ":" . $pageSize . ":" . $page;
    $redis->setex($key, 7200, json_encode($data));
}

// 获取用户访客分页数据
function getUserVisitorPageData($userId, $page, $pageSize, Redis $redis)
{
    $key = REDIS_USER_VISITOR_PAGE_DATA . $userId . ":" . $pageSize . ":" . $page;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    }
    return [];
}

// 删除用户所有访客分页数据缓存
function deleteUserVisitorPageData($userId, Redis $redis)
{
    $key = REDIS_USER_VISITOR_PAGE_DATA . $userId . ":*";
    $keys = $redis->keys($key);
    $redis->del($keys);
}

/**
 * 缓存用户访客数据信息 每日访客的集合（去重复，减少数据库查询次数）
 */
define("REDIS_USER_VISITOR_SUM_COUNT", REDIS_KEY_PREFIX . "userVisitorSumCount:");
//缓存用户访客总数，初始化
function cacheUserVisitorSumCount($userId,$count, Redis $redis)
{
    $key = REDIS_USER_VISITOR_SUM_COUNT . $userId;
    $redis->set($key,$count);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 86400);// 缓存一天
    }
}

// 更新
function addUserVisitorSumCount($userId, Redis $redis)
{
    $key = REDIS_USER_VISITOR_SUM_COUNT . $userId;
    $redis->incr($key);
}

// 获取当前用户访问总人数
function getUserVisitorSumCount($userId, Redis $redis)
{
    $key = REDIS_USER_VISITOR_DAY_SET . $userId;
    return $redis->get($key);
}

