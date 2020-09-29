<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\guard;

use app\common\transformer\TransformerAbstract;

class RecentlyTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        $data = $data["data"] ?? [];

        if (empty($data)) {
            return [
                'list' => []
            ];
        }

        $ret = [];
        foreach ($data as $item) {
            $tmp = [];
            $tmp['u_id'] = $item['guard_u_id'] ?? '';
            $tmp['avatar'] = $item["portrait"] ?? "";
            $tmp['nickname'] = $item['nickname'] ?? '';
            $tmp['charm'] = $this->getCharm($item['charm_amount']);
            $ret[] = $tmp;
        }

        return [
            "list" => $ret
        ];
    }

    // 处理魅力值
    private function getCharm($number)
    {
        return bcdiv($number, 100, 2);
    }

}