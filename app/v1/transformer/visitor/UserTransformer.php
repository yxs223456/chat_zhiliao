<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\visitor;

use app\common\service\CityService;
use app\common\transformer\TransformerAbstract;

class UserTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        return [
            'is_vip' => $data['is_vip'] ?? 0,
            'count' => $data["count"] ?? 0,
            'today_count' => $data["today_count"]??0,
            'list' => $this->getList($data["list"])
        ];
    }

    private function getList($list)
    {
        $ret = [];
        foreach ($list as $item) {
            $tmp = [];
            $tmp["id"] = $item["id"] ?? 0;
            $tmp["u_id"] = $item["visitor_u_id"] ?? 0;
            $tmp["sex"] = $item["sex"] ?? 0;
            $tmp["avatar"] = $item["portrait"] ?? "";
            $tmp["nickname"] = $item["nickname"] ?? "";
            $tmp["age"] = (int)$this->getUserAge($item["birthday"] ?? "");
            $tmp["city"] = empty($item["city"]) ? "" : CityService::getCityByCode($item['city']);
            $tmp['signatures'] = empty($item['signatures']) ? [] : json_decode($item['signatures'],true);
            $tmp["time"] = $item["create_time"] ?? 0;
            $ret[] = $tmp;
        }
        return $ret;
    }

    /**
     * 获取年龄
     *
     * @param $birthday
     * @return false|int|string
     */
    private function getUserAge($birthday)
    {
        if (empty($birthday)) {
            return 0;
        }
        return date('Y') - substr($birthday, 0, 4);
    }
}