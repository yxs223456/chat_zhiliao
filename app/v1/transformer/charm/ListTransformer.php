<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\charm;

use app\common\transformer\TransformerAbstract;

class ListTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        $list = $data['list'] ?? [];
        if (empty($list)) {
            return $data;
        }
        $ret = [];

        foreach ($list as $item) {
            $tmp = [];
            $tmp['u_id'] = $item['u_id'] ?? '';
            $tmp['avatar'] = $item['portrait'] ?? '';
            $tmp['nickname'] = $item['nickname'] ?? '';
            $tmp['charm'] = $item['charm'] ?? 0;
            $tmp['rank'] = $item['rank'] ?? 0;
            $ret[] = $tmp;
        }

        $data['list'] = $ret;

        return $data;
    }

}