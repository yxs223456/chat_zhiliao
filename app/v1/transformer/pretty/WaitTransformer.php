<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\pretty;

use app\common\Constant;
use app\common\transformer\TransformerAbstract;

class WaitTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        $guard = $data['guard'] ?? [];
        $list = $data['list'] ?? [];

        $retData = [
            'have_angle' => empty($guard) ? 0 : 1,
            'angle' => empty($guard) ? new \stdClass() : [],
            'list' => []
        ];

        if (!empty($guard)) {
            $retData["angle"]["avatar"] = $guard["portrait"] ?? "";
        }

        $ret = [];
        foreach ($list as $item) {
            $tmp = [];
            $tmp['u_id'] = $item['u_id'] ?? 0;
            $tmp['avatar'] = $item["portrait"] ?? "";
            $tmp['nickname'] = $item['nickname'] ?? '';
            $tmp['charm'] = $this->getCharm($item['charm']);
            $tmp['is_enough'] = $this->getIsEnough($item['charm']);
            $tmp['voice_chat_switch'] = $item['voice_chat_switch'] ?? 0;
            $tmp['voice_chat_price'] = $item['voice_chat_price'] ?? 0;
            $tmp['video_chat_switch'] = $item['video_chat_switch'] ?? 0;
            $tmp['video_chat_price'] = $item['video_chat_price'] ?? 0;
            $tmp['direct_message_free'] = $item['direct_message_free'] ?? 0;
            $tmp['direct_message_price'] = $item['direct_message_price'] ?? 0;
            $ret[] = $tmp;
        }

        $retData['list'] = $ret;
        return $retData;
    }

    // 处理魅力值
    private function getCharm($number)
    {
        if ($number < Constant::GUARD_MIN_AMOUNT) {
            return bcdiv(Constant::GUARD_MIN_AMOUNT - $number, 100, 2);
        }

        return bcdiv($number, 100, 2);
    }

    /**
     * 是否超过守护最低限制金额
     *
     * @param $number
     * @return int
     */
    private function getIsEnough($number)
    {
        if ($number > Constant::GUARD_MIN_AMOUNT) {
            return 1;
        }
        return 0;
    }

}