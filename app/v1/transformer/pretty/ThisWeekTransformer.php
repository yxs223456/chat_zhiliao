<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\pretty;

use app\common\transformer\TransformerAbstract;

class ThisWeekTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        $list = $data['list'] ?? [];
        $user = $data['user'] ?? [];
        $guard = $data['guard'] ?? [];

        $retData = [
            'guard' => [
                'avatar' => ''
            ],
            'user' => [
                'u_id' => $user['u_id'] ?? 0,
                'avatar' => $user['avatar'] ?? "",
                'charm' => bcdiv($user['charm'],100,2),
                'rank' => 0,
                'user_number' => $user['user_number'] ?? ''
            ],
            'list' => []
        ];

        if ($guard) {
            $retData['guard']['avatar'] = $guard['portrait'] ?? '';
        }

        $ret = [];
        foreach ($list as $key => $item) {
            $tmp = [];
            $tmp['u_id'] = $item['u_id'] ?? '';
            $tmp['avatar'] = $item['portrait'] ?? '';
            $tmp['user_number'] = $this->getHiddenUserNumber($item['user_number']);
            $tmp['charm'] = bcdiv($item['charm'], 100, 2) ?? "0.00";
            $tmp['rank'] = (int)$item['rank'];
            $ret[] = $tmp;
            if ($item['u_id'] == $user['u_id']) {
                $retData['user']['rank'] = $key + 1;
            }
        }

        $retData['list'] = $ret;
        return $retData;
    }

    // 处理字符串
    private function getHiddenUserNumber($number)
    {
        $len = strlen($number);
        if (!$len) {
            return "";
        }
        return $number[0] . str_repeat("*", $len - 2) . $number[$len - 1];
    }

}