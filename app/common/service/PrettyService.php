<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-10-12
 * Time: 10:15
 */

namespace app\common\service;

use app\common\enum\UserSexEnum;
use app\common\helper\Redis;

class PrettyService extends Base
{
    public function conditionInfo($user)
    {

    }

    /**
     * 更新女神月，周，日排行redis集合,更新女神周贡献，更新男神周贡献
     *
     * @param $pretty
     * @param $spend
     * @param $coin
     */
    public static function updatePrettySortList($pretty, $spend, $coin)
    {
        // 男的忽略
        if ($pretty["sex"] == UserSexEnum::MALE) {
            return;
        }
        $redis = Redis::factory();
        // 更新女的魅力月榜
        cacheFemaleCharmSortSetMonth($pretty['id'], $coin, $redis);
        // 更新女的魅力周榜
        cacheFemaleCharmSortSetWeek($pretty['id'], $coin, $redis);
        // 更新女的魅力日榜
        cacheFemaleCharmSortSetDay($pretty['id'], $coin, $redis);

        // 如果花钱的是男的
        if ($spend["sex"] == UserSexEnum::MALE) {
            // 更新女神贡献周排名
            cacheFemaleContributeSortSet($pretty["id"], $spend["id"], $coin, $redis);
            // 更新男的贡献周排名
            cacheMaleContributeSortSet($spend['id'], $pretty['id'], $coin, $redis);
        }
    }
}