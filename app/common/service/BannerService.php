<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-02
 * Time: 10:10
 */

namespace app\common\service;

use app\common\enum\BannerPositionEnum;
use app\common\enum\DbDataIsDeleteEnum;
use app\common\enum\IsShowEnum;
use app\common\helper\Redis;
use think\facade\Db;

class BannerService extends Base
{
    public function home()
    {
        // 先从缓存中获取banner数据，缓存失效从数据库获取并重新生成缓存
        $redis = Redis::factory();
        $bannerList = getBannerListByPosition(BannerPositionEnum::APP_HOME, $redis);

        if (empty($bannerList)) {
            $bannerList = Db::name("banner")
                ->where("position", BannerPositionEnum::APP_HOME)
                ->where("is_show", IsShowEnum::YES)
                ->where("is_delete", DbDataIsDeleteEnum::NO)
                ->order("sort", "asc")
                ->select();
        }

        $returnData = [];
        foreach ($bannerList as $item) {
            $returnData[] = [
                "id" => $item["id"],
                "image_url" => $item["image_url"],
                "link_type" => $item["link_type"],
                "link" => json_decode($item["link"], true),
                "params" => json_decode($item["params"], true),
                "description" => $item["description"],
            ];
        }

        return $returnData;
    }
}