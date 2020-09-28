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
        $amountList = $data['amountList'] ?? [];
        $userInfoList = $data['userInfoList'] ?? [];

        $retData = [
            'guard' => new \stdClass(),
            'list' => []
        ];

        if (!empty($guard)) {
            $retData["guard"]["avatar"] = $guard["portrait"] ?? "";
        }

        $userIdToInfo = [];
        if (!empty($userInfoList)) {
            $userIdToInfo = array_combine(array_column($userInfoList, 'u_id'), $userInfoList);
        }

        $ret = [];
        foreach ($amountList as $item) {
            $tmp = [];
            $tmp['u_id'] = $item['guard_u_id'] ?? '';
            $tmp['avatar'] = $userIdToInfo[$item['guard_u_id']]["portrait"] ?? "";
            $tmp['nickname'] = $userIdToInfo[$item['guard_u_id']]['nickname'] ?? '';
            $tmp['charm'] = $this->getCharm($item['total_amount']);
            $tmp['is_enough'] = $this->getIsEnough($item['total_amount']);
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