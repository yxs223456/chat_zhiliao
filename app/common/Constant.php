<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-02-26
 * Time: 17:21
 */

namespace app\common;

class Constant
{
    // 默认头像
    const USER_DEFAULT_PORTRAIT = "";

    // 提现手续费比例
    const WITHDRAW_POUNDAGE_RATE = 0.01;

    // 回写缓存锁时间 单位秒
    const CACHE_LOCK_SECONDS = 10;

    // 获取缓存尝试次数
    const GET_CACHE_TIMES = 3;

    // 获取缓存等待时间 单位 微秒
    const GET_CACHE_WAIT_TIME = 50000; // 50 毫秒

    // 邀请男生奖励人民币（元）
    const INVITE_MALE_REWARD_MONEY = 1;

    // 邀请女生成为女神奖励人民币（元）
    const INVITE_PRETTY_FEMALE_REWARD_MONEY = 3;
}