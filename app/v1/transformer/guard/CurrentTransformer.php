<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\guard;

use app\common\transformer\TransformerAbstract;

class CurrentTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        $data = $data["data"] ?? [];
        if(empty($data)) {
            return [
                "list" => []
            ];
        }

        $ret = [];
        foreach ($data as $item)
        {
            $tmp = [];
            $tmp["u_id"] = $item["u_id"] ?? 0;
            $tmp["avatar"] = $item["portrait"] ?? "";
            $tmp["nickname"] = $item["nickname"] ?? "";
            $tmp["charm"] = $this->getCharm($item["charm_amount"]);
            $tmp['voice_chat_switch'] = $item['voice_chat_switch'] ?? 0;
            $tmp['voice_chat_price'] = $item['voice_chat_price'] ?? 0;
            $tmp['video_chat_switch'] = $item['video_chat_switch'] ?? 0;
            $tmp['video_chat_price'] = $item['video_chat_price'] ?? 0;
            $tmp['direct_message_free'] = $item['direct_message_free'] ?? 0;
            $tmp['direct_message_price'] = $item['direct_message_price'] ?? 0;
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