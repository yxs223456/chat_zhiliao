<?php
/**
 * redis key 统一前缀
 */
define("REDIS_KEY_PREFIX", "chat_zhiliao:");

/**
 * 城市缓存
 */
define("REDIS_KEY_CITY_CONFIG", REDIS_KEY_PREFIX . "cityConfig");

// 缓存城市配置，有效期15天
function cacheCityConfig(array $cityConfig, \Redis $redis)
{
    $key = REDIS_KEY_CITY_CONFIG;
    $redis->setex($key, 10 * 86400, json_encode($cityConfig));
}

// 获取城市配置
function getCityConfigByCache(\Redis $redis)
{
    $key = REDIS_KEY_CITY_CONFIG;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    } else {
        return [];
    }
}

/**
 * 首页推荐集合1
 */
define("REDIS_KEY_HOME_RECOMMEND_LIST", REDIS_KEY_PREFIX . "homeRecommendList:");

// 将用户放入首页推荐集合
function addUserToHomeRecommendList($userId, $condition, $score, \Redis $redis)
{
    $key = REDIS_KEY_HOME_RECOMMEND_LIST . $condition;
    $redis->zAdd($key, $score, $userId);
}

// 首页推荐集合删除用户
function deleteUserFromHomeRecommendList($userId, $condition, \Redis $redis)
{
    $key = REDIS_KEY_HOME_RECOMMEND_LIST . $condition;
    $redis->zRem($key, $userId);
}

// 首页推荐集合获取分页数据
function getUserListFromHomeRecommendList($condition, $pageNum, $pageSize, \Redis $redis)
{
    $key = REDIS_KEY_HOME_RECOMMEND_LIST . $condition;
    return $redis->zRange($key, ($pageNum-1)*$pageSize, $pageSize);
}


/**
 * 首页推荐集合2
 */
define("REDIS_KEY_HOME_RECOMMEND_LIST2", REDIS_KEY_PREFIX . "homeRecommendList2:");

// 缓存首页推荐集合
function cacheUserToHomeRecommendList2(array $list, $condition, \Redis $redis)
{
    $key = REDIS_KEY_HOME_RECOMMEND_LIST2 . $condition;
    $redis->setex($key, 86400, json_encode($list));
}

// 首页推荐集合获取分页数据
function getUserListFromHomeRecommendList2($condition, $pageNum, $pageSize, \Redis $redis)
{
    $key = REDIS_KEY_HOME_RECOMMEND_LIST2 . $condition;
    $data = $redis->get($key);
    if (!$data) {
        return [];
    }
    $data = json_decode($data, true);
    return array_slice($data, ($pageNum-1)*$pageSize, $pageSize);
}

/**
 * 首页新人集合
 */
define("REDIS_KEY_HOME_NEW_USER_LIST", REDIS_KEY_PREFIX . "homeNewUserList:");

// 将用户放入首页新人列表
function addUserToHomeNewUserList($userId, $condition, $score, \Redis $redis)
{
    $key = REDIS_KEY_HOME_NEW_USER_LIST . $condition;
    $redis->zAdd($key, $score, $userId);
}

// 首页新人集合删除用户
function deleteUserFromHomeNewUserList($userId, $condition, \Redis $redis)
{
    $key = REDIS_KEY_HOME_NEW_USER_LIST . $condition;
    $redis->zRem($key, $userId);
}

// 首页新人集合获取分页数据
function getUserListFromHomeNewUserList($condition, $pageNum, $pageSize, \Redis $redis)
{
    $key = REDIS_KEY_HOME_NEW_USER_LIST . $condition;
    return $redis->zRange($key, ($pageNum-1)*$pageSize, $pageSize);
}


/**
 * 首页新人集合2
 */
define("REDIS_KEY_HOME_NEW_USER_LIST2", REDIS_KEY_PREFIX . "homeNewUserList2:");

// 将用户放入首页新人列表
function cacheUserToHomeNewUserList2(array $list, $condition, \Redis $redis)
{
    $key = REDIS_KEY_HOME_NEW_USER_LIST2 . $condition;
    $redis->setex($key, 86400, json_encode($list));
}

// 首页新人集合获取分页数据
function getUserListFromHomeNewUserList2($condition, $pageNum, $pageSize, \Redis $redis)
{
    $key = REDIS_KEY_HOME_NEW_USER_LIST2 . $condition;
    $data = $redis->get($key);
    if (!$data) {
        return [];
    }
    $data = json_decode($data, true);
    return array_slice($data, ($pageNum-1)*$pageSize, $pageSize);
}

/**
 * 首页对应地区用户集合
 */
define("REDIS_KEY_HOME_SITE_LIST", REDIS_KEY_PREFIX . "homeSiteList:");

// 将用户放入首页对应地区用户集合
function addUserToHomeSiteList($userId, $site, $condition, $score, \Redis $redis)
{
    $key = REDIS_KEY_HOME_SITE_LIST . $site . ":" . $condition;
    $redis->zAdd($key, $score, $userId);
}

// 首页对应地区用户集合删除用户
function deleteUserFromHomeSiteList($userId, $site, $condition, \Redis $redis)
{
    $key = REDIS_KEY_HOME_SITE_LIST . $site . ":" . $condition;
    $redis->zRem($key, $userId);
}

// 首页对应地区用户集合获取分页数据
function getUserListFromHomeSiteList($site, $condition, $pageNum, $pageSize, \Redis $redis)
{
    $key = REDIS_KEY_HOME_SITE_LIST . $site . ":" . $condition;
    return $redis->zRange($key, ($pageNum-1)*$pageSize, $pageSize);
}

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

// 删除用户缓存
function deleteUserInfoById($userId,Redis $redis)
{
    $key = REDIS_USER_INFO_BY_ID . $userId;
    $redis->del($key);
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
 * 附近用户geohash缓存相关(并发)
 */
define("REDIS_ALL_USER_LONG_LAT_INFO", REDIS_KEY_PREFIX . 'allUserLongLatInfo:');
// 缓存数据
function cacheUserLongLatInfo($userId, $lat, $long, \Redis $redis)
{

    $key = REDIS_ALL_USER_LONG_LAT_INFO;
    // 添加当前用户经纬度
    $redis->rawCommand("geoadd", $key, $long, $lat, $userId);
}

// 获取某个用户查询200km半斤内用户ID和之间的距离
function getNearUserLongLatInfo($userId, \Redis $redis)
{
    $key = REDIS_ALL_USER_LONG_LAT_INFO;
    $ret = $redis->rawCommand("georadiusbymember", $key, $userId, \app\common\Constant::GEO_SEARCH_DISTANCE_KILOMETER, "km", "WITHDIST", "COUNT", "300", "ASC");
    if (empty($ret)) {
        return null;
    }

    return $ret;
}

// 计算两个用户间的距离
function getDistanceByTwoUserId($userId1, $userId2, Redis $redis)
{
    return $redis->rawCommand("geodist", REDIS_ALL_USER_LONG_LAT_INFO, $userId1, $userId2, "km");
}

// 删除所有用户坐标缓存
function deleteUserLongLatInfo(\Redis $redis)
{
    $redis->del(REDIS_ALL_USER_LONG_LAT_INFO);
}

// 删除一个用户坐标缓存
function deleteUserLongLatInfoByUserId($userId, \Redis $redis)
{
    $redis->zRem(REDIS_ALL_USER_LONG_LAT_INFO, $userId);
}

/**
 * 附近用户距离排序的有序集合(无并发)
 */
define("REDIS_NEAR_USER_SORT_SET", REDIS_KEY_PREFIX . "nearUserSortSet:");
// 缓存附近用户集合 一天
function cacheNearUserSortSet($userId, \Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . $userId;
    $userIdAndDistance = getNearUserLongLatInfo($userId, $redis);
    if (empty($userIdAndDistance)) {
        return;
    }

    // 计算距离放入有序集合
    foreach ($userIdAndDistance as $item) {
        if ($item[0] == $userId) {
            continue;
        }
        $redis->zAdd($key, $item[1], $item[0]);
    }
    $redis->expire($key, 86400); // 缓存一天
}

// 获取附近用户ID有序集合
function getNearUserSortSet($userId, $pageNum, $pageSize, \Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . $userId;
    return $redis->zRange($key, ($pageNum - 1) * $pageSize, $pageSize, true);
}

// 用户1分钟内只能刷新一次 加锁
function setNearUserSortSetLock($userId, \Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . 'Lock' . $userId;
    $redis->setex($key, 60, 1);
}

// 获取用户刷新附近人动态的锁
function getNearUserSortSetLock($userId, Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . 'Lock' . $userId;
    return $redis->get($key);
}

// 删除用户附近缓存数据
function deleteNearUserCache($userId, \Redis $redis)
{
    $key = REDIS_NEAR_USER_SORT_SET . $userId . "*";
    $keys = $redis->keys($key);
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
        return null;
    }
}

// 通过user_id删除用户黑名单数据
function deleteUserBlackListByUId($userId, Redis $redis)
{
    $key = REDIS_USER_BLACK_LIST_BY_UID . $userId;
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

// 获取用户今天总访问次数
function getUserVisitorTodayCount($userId, Redis $redis)
{
    $key = REDIS_USER_VISITOR_DAY_SET . $userId . ":" . date("Y-m-d");
    return $redis->sCard($key);
}

/**
 * 缓存用户访客数据信息 每日访客的集合（去重复，减少数据库查询次数）
 */
define("REDIS_USER_VISITOR_SUM_COUNT", REDIS_KEY_PREFIX . "userVisitorSumCount:");
//缓存用户访客总数，初始化
function cacheUserVisitorSumCount($userId, $count, Redis $redis)
{
    $key = REDIS_USER_VISITOR_SUM_COUNT . $userId;
    $redis->set($key, $count);
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


/**
 * 缓存用户本周守护人信息(上周角逐出来的)
 */
define("REDIS_USER_GUARD_INFO", REDIS_KEY_PREFIX . "userUserGuardInfo:");
//缓存用户本周守护
function cacheUserGuard($userId, $guardInfo, Redis $redis)
{
    $key = REDIS_USER_GUARD_INFO . $userId . ":" . getLastWeekStartDate() . "-" . getLastWeekEndDate();
    $redis->set($key, json_encode($guardInfo));
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 86400);// 缓存一天
    }
}

//获取用户本周守护
function getUserGuard($userId, Redis $redis)
{
    $key = REDIS_USER_GUARD_INFO . $userId . ":" . getLastWeekStartDate() . "-" . getLastWeekEndDate();
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    }
    return null;
}

/**
 * 男生正在守护的女神列表(缓存一天)
 */
define("REDIS_MALE_CURRENT_GUARD_PRETTY", REDIS_KEY_PREFIX . "user_male_current_guard_pretty:");
//男生正在守护的女神列表
function cacheMaleCurrentGuardPretty($uid, $data, Redis $redis)
{
    $key = REDIS_MALE_CURRENT_GUARD_PRETTY . $uid;
    $redis->set($key, json_encode($data));
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 86400);// 缓存一天
    }
}

//获取男生正在守护的女神列表
function getMaleCurrentGuardPretty($uid, Redis $redis)
{
    $key = REDIS_MALE_CURRENT_GUARD_PRETTY . $uid;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    }
    return null;
}

/**
 * 男生最近三个月守护的女神列表(缓存一天)
 */
define("REDIS_MALE_RECENTLY_GUARD_PRETTY", REDIS_KEY_PREFIX . "user_male_recently_guard_pretty:");
//男生最近三个月守护的女神列表
function cacheMaleRecentlyGuardPretty($uid, $data, Redis $redis)
{
    $key = REDIS_MALE_RECENTLY_GUARD_PRETTY. $uid;
    $redis->set($key, json_encode($data));
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 86400);// 缓存一天
    }
}

//获取男生最近三个月守护的女神列表
function getMaleRecentlyGuardPretty($uid, Redis $redis)
{
    $key = REDIS_MALE_RECENTLY_GUARD_PRETTY . $uid;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    }
    return null;
}

/**
 * 缓存女神魅力排行月榜
 */
define("REDIS_FEMALE_CHARM_SORT_SET_MONTH", REDIS_KEY_PREFIX . "femaleCharmSortSetMonth:");
//缓存女神魅力月榜，有效期一个月
function cacheFemaleCharmSortSetMonth($userId, $charm, Redis $redis)
{
    $startDate = date("Y-m-01");
    $endDate = date('Y-m-d', strtotime("$startDate +1 month -1 day"));
    $key = REDIS_FEMALE_CHARM_SORT_SET_MONTH . $startDate ."-" . $endDate;
    $redis->zIncrBy($key, $charm, $userId);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 2678400);// 缓存一月
    }
}

/**
 * 获取女神魅力月排行
 */
function getFemaleCharmSortSetMonth($start, $end, Redis $redis)
{
    $startDate = date("Y-m-01");
    $endDate = date('Y-m-d', strtotime("$startDate +1 month -1 day"));
    $key = REDIS_FEMALE_CHARM_SORT_SET_MONTH . $startDate ."-" . $endDate;
    $data = $redis->zRevRange($key, $start, $end, true);
    if ($data) {
        return $data;
    }
    return null;
}

/**
 * 返回用户月排名
 */
function getFemaleCharmSortSetMonthRank($userId, Redis $redis)
{
    $startDate = date("Y-m-01");
    $endDate = date('Y-m-d', strtotime("$startDate +1 month -1 day"));
    $key = REDIS_FEMALE_CHARM_SORT_SET_MONTH . $startDate . "-" . $endDate;
    return $redis->zRevRank($key, $userId);
}

/**
 * 返回用户月排行分值
 */
function getFemaleCharmSortSetMonthScore($userId, Redis $redis)
{
    $startDate = date("Y-m-01");
    $endDate = date('Y-m-d', strtotime("$startDate +1 month -1 day"));
    $key = REDIS_FEMALE_CHARM_SORT_SET_MONTH . $startDate . "-" . $endDate;
    return $redis->zScore($key, $userId);
}

/**
 * 缓存女神魅力排行周榜
 */
define("REDIS_FEMALE_CHARM_SORT_SET_WEEK", REDIS_KEY_PREFIX . "femaleCharmSortSetWeek:");
function cacheFemaleCharmSortSetWeek($userId, $charm, Redis $redis)
{
    list($start, $end) = getWeekStartAndEnd();
    $key = REDIS_FEMALE_CHARM_SORT_SET_WEEK . $start . "-" . $end;
    $redis->zIncrBy($key, $charm, $userId);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 604800); // 缓存一周
    }
}

/**
 * 获取女神魅力周排行
 */
function getFemaleCharmSortSetWeek($start, $end, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_FEMALE_CHARM_SORT_SET_WEEK . $startDate. "-" . $endDate;
    $data = $redis->zRevRange($key, $start, $end, true);
    if ($data) {
        return $data;
    }
    return null;
}

/**
 * 返回女神魅力周排名
 */
function getFemaleCharmSortSetWeekRank($userId, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_FEMALE_CHARM_SORT_SET_WEEK . $startDate ."-". $endDate;
    return $redis->zRevRank($key, $userId);
}

/**
 * 返回女神魅力周排行分值
 */
function getFemaleCharmSortSetWeekScore($userId, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_FEMALE_CHARM_SORT_SET_WEEK . $startDate ."-". $endDate;
    return $redis->zScore($key, $userId);
}

/**
 * 缓存女神魅力排行日榜
 */
define("REDIS_FEMALE_CHARM_SORT_SET_DAY", REDIS_KEY_PREFIX . "femaleCharmSortSetDay:");
function cacheFemaleCharmSortSetDay($userId, $charm, Redis $redis)
{
    $key = REDIS_FEMALE_CHARM_SORT_SET_DAY . date("Y-m-d");
    $redis->zIncrBy($key, $charm, $userId);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 86400); // 缓存一天
    }
}

/**
 * 获取女神魅力日排行
 */
function getFemaleCharmSortSetDay($start, $end, Redis $redis)
{
    $key = REDIS_FEMALE_CHARM_SORT_SET_DAY . date("Y-m-d");
    $data = $redis->zRevRange($key, $start, $end, true);
    if ($data) {
        return $data;
    }
    return null;
}

/**
 * 返回用户日排名
 */
function getFemaleCharmSortSetDayRank($userId, Redis $redis)
{
    $key = $key = REDIS_FEMALE_CHARM_SORT_SET_DAY . date("Y-m-d");
    return $redis->zRevRank($key, $userId);
}

/**
 * 返回用户日排行分值
 */
function getFemaleCharmSortSetDayScore($userId, Redis $redis)
{
    $key = $key = REDIS_FEMALE_CHARM_SORT_SET_DAY . date("Y-m-d");
    return $redis->zScore($key, $userId);
}


/**
 * 缓存女神贡献排行周榜，缓存两周，除了本周贡献，上周贡献列表也会用到
 */
define("REDIS_FEMALE_CONTRIBUTE_SORT_SET_WEEK", REDIS_KEY_PREFIX . "femaleContributeSortSetWeek:");
function cacheFemaleContributeSortSet($userId, $contributeId, $coin, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_FEMALE_CONTRIBUTE_SORT_SET_WEEK . $userId . ":" . $startDate . "-" . $endDate;
    $redis->zIncrBy($key, $coin, $contributeId);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 1209600); // 缓存两周
    }
}
/**
 * 获取女神贡献人本周排行
 */
function getFemaleContributeSortSetThisWeek($userId, $start, $end, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_FEMALE_CONTRIBUTE_SORT_SET_WEEK . $userId . ":" . $startDate . "-" . $endDate;
    $data = $redis->zRevRange($key, $start, $end, true);
    if ($data) {
        return $data;
    }
    return null;
}
/**
 * 返回当前用户在女神本周排名
 */
function getFemaleContributeSortSetThisWeekRank($userId, $contributeId, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_FEMALE_CONTRIBUTE_SORT_SET_WEEK . $userId . ":" . $startDate . "-" . $endDate;
    return $redis->zRevRank($key, $contributeId);
}
/**
 * 返回当前用户在女神本周排行分值
 */
function getFemaleContributeSortSetThisWeekScore($userId, $contributeId, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_FEMALE_CONTRIBUTE_SORT_SET_WEEK . $userId . ":" . $startDate . "-" . $endDate;
    return $redis->zScore($key, $contributeId);
}
/**
 * 获取女神贡献人上周排行
 */
function getFemaleContributeSortSetLastWeek($userId, $start, $end, Redis $redis)
{
    $key = REDIS_FEMALE_CONTRIBUTE_SORT_SET_WEEK . $userId . ":" . getLastWeekStartDate() . "-" . getLastWeekEndDate();
    $data = $redis->zRevRange($key, $start, $end, true);
    if ($data) {
        return $data;
    }
    return null;
}
/**
 * 返回当前用户在女神上周排名
 */
function getFemaleContributeSortSetLastWeekRank($userId, $contributeId, Redis $redis)
{
    $key = REDIS_FEMALE_CONTRIBUTE_SORT_SET_WEEK . $userId . ":" . getLastWeekStartDate() . "-" . getLastWeekEndDate();
    return $redis->zRevRank($key, $contributeId);
}
/**
 * 返回当前用户在女神上周排行分值
 */
function getFemaleContributeSortSetLastWeekScore($userId, $contributeId, Redis $redis)
{
    $key = REDIS_FEMALE_CONTRIBUTE_SORT_SET_WEEK . $userId . ":" . getLastWeekStartDate() . "-" . getLastWeekEndDate();
    return $redis->zScore($key, $contributeId);
}


/**
 * 缓存男的贡献排周榜，缓存一周。（一个男的给女的花钱的排行）
 */
define("REDIS_MALE_CONTRIBUTE_SORT_SET_WEEK", REDIS_KEY_PREFIX . "maleContributeSortSetWeek:");
function cacheMaleContributeSortSet($userId, $prettyId, $coin, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_MALE_CONTRIBUTE_SORT_SET_WEEK . $userId . ":" . $startDate . "-" . $endDate;
    $redis->zIncrBy($key, $coin, $prettyId);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 604800); // 缓存一周
    }
}
/**
 * 获取男的本周贡献的女神排行
 */
function getMaleContributeSortSetThisWeek($userId, $start, $end, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_MALE_CONTRIBUTE_SORT_SET_WEEK . $userId . ":" . $startDate . "-" . $endDate;
    $data = $redis->zRevRange($key, $start, $end, true);
    if ($data) {
        return $data;
    }
    return null;
}

/**
 * 缓存男的守护收入周榜
 */
define("REDIS_MALE_EARN_SORT_SET_WEEK", REDIS_KEY_PREFIX . "maleGuardEarnSortSetWeek:");
function cacheMaleGuardEarnSortSetWeek($userId, $charm, Redis $redis)
{
    list($start, $end) = getWeekStartAndEnd();
    $key = REDIS_MALE_EARN_SORT_SET_WEEK . $start . "-" . $end;
    $redis->zIncrBy($key, $charm, $userId);
    if ($redis->ttl($key) == -1) {
        $redis->expire($key, 604800); // 缓存一周
    }
}
/**
 * 获取男的守护收入周排行
 */
function getMaleGuardEarnSortSetWeek($start, $end, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_MALE_EARN_SORT_SET_WEEK . $startDate . "-" . $endDate;
    $data = $redis->zRevRange($key, $start, $end, true);
    if ($data) {
        return $data;
    }
    return null;
}
/**
 * 返回男的守护收入周排名
 */
function getMaleGuardEarnSortSetWeekRank($userId, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_MALE_EARN_SORT_SET_WEEK . $startDate . "-" . $endDate;
    return $redis->zRevRank($key, $userId);
}
/**
 * 返回男的守护收入周排行分值
 */
function getMaleGuardEarnSortSetWeekScore($userId, Redis $redis)
{
    list($startDate, $endDate) = getWeekStartAndEnd();
    $key = REDIS_MALE_EARN_SORT_SET_WEEK . $startDate . "-" . $endDate;
    return $redis->zScore($key, $userId);
}

/**
 * 缓存个人附近动态缓存数据
 */
define("REDIS_NEAR_DYNAMIC_SORT_DATA", REDIS_KEY_PREFIX . "nearDynamicSortData:");
function cacheNearDynamicSortData($userId, $data, Redis $redis)
{
    $key = REDIS_NEAR_DYNAMIC_SORT_DATA . $userId;
    $redis->set($key, json_encode($data), 600);
}

// 获取个人附近动态数据
function getNearDynamicSortData($userId, Redis $redis)
{
    $key = REDIS_NEAR_DYNAMIC_SORT_DATA . $userId;
    $data = $redis->get($key);
    if ($data) {
        return json_decode($data, true);
    }
    return null;
}