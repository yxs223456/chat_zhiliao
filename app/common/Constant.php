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
    const USER_DEFAULT_PORTRAIT = "https://wx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTI2ypuOuCibDFf8xy6ktq5wZM2iamlkibbib0tv78hoicbdL7XsZMXasiaRvTApuzvHGo64qZcCiavicTiaoyw/132";

    // 红包最小金额
    const RED_PACKAGE_MIN_AMOUNT = 10;

    // 红包配置
    const RED_PACKAGE_CONFIG = [
        "name" => "红包",
        "image_url" => "",
    ];

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

    // 红包分润比例
    const RED_PACKAGE_BONUS_RATE = 0.6;

    // 收礼人礼物分润比例
    const GIFT_BONUS_RATE = 0.6;

    // 普通人通话分润比例
    const ORDINARY_CHAT_DIVIDED_RATE = 0.5;

    // 女、男神通话分润比例
    const PRETTY_CHAT_DIVIDED_RATE = 0.8;

    // 女神通话，视频等级金额限制(聊币)
    // 普通女性
    const PRETTY_FEMALE_LEVEL_COMMON = 100;

    // 见习女神
    const PRETTY_FEMALE_LEVEL_TRAINEE = 100;

    // 铁牌女神
    const PRETTY_FEMALE_LEVEL_IRON = 150;

    // 铜牌女神
    const PRETTY_FEMALE_LEVEL_COPPER = 200;

    // 银牌女神
    const PRETTY_FEMALE_LEVEL_SILVER = 250;

    // 金牌女神
    const PRETTY_FEMALE_LEVEL_GOLD = 350;

    // 皇冠女神
    const PRETTY_FEMALE_LEVEL_CROWN = 500;

    // 男神通话，视频等级金额限制(聊币) 必须是vip
    // 普通男性
    const PRETTY_MALE_LEVEL_COMMON = 30;

    // 见习男神
    const PRETTY_MALE_LEVEL_TRAINEE = 100;

    // 铁牌男神
    const PRETTY_MALE_LEVEL_IRON = 150;

    // 铜牌男神
    const PRETTY_MALE_LEVEL_COPPER = 200;

    // 银牌男神
    const PRETTY_MALE_LEVEL_SILVER = 250;

    // 金牌男神
    const PRETTY_MALE_LEVEL_GOLD = 350;

    // 皇冠男神
    const PRETTY_MALE_LEVEL_CROWN = 500;

    // 男神女神私聊收费金额 条/10聊币
    const PRETTY_MESSAGE_PRICE_COIN = 10;
}