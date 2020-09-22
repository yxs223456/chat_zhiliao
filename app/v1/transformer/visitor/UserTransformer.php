<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\visitor;

use app\common\transformer\TransformerAbstract;

class UserTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        return [
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
            $tmp["u_id"] = $item["u_id"] ?? 0;
            $tmp["sex"] = $item["sex"] ?? 0;
            $tmp["avatar"] = $item["portrait"] ?? "";
            $tmp["nickname"] = $item["nickname"] ?? "";
            $tmp["age"] = (int)$this->getUserAge($item["birthday"] ?? "");
            $tmp["city"] = $item["city"] ?? "";
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