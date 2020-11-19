<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\earn;

use app\common\transformer\TransformerAbstract;

class RankTransformer extends TransformerAbstract
{
    private $pageSize = 20;
    private $pageNum = 1;

    public function __construct(array $params = null)
    {
        parent::__construct($params);
        $this->pageSize = $this->_queries["pageSize"] ?? 20;
        $this->pageNum = $this->_queries["pageNum"] ?? 1;

    }

    public function transformData(array $data): array
    {
        $currentUser = $data['user'] ?? [];
        $list = $data['list'] ?? [];

        $retData = [
            'user' => [
                'u_id' => (int)$currentUser["u_id"] ?? 0,
                'avatar' => $currentUser['avatar'] ?? '',
                'total_amount' => (string)($currentUser['total_amount'] ?? 0),
                'rank' => $currentUser['rank'] ?? 0,
                'nickname' => $currentUser['nickname'] ?? ''
            ],
            'list' => []
        ];

        $start = ($this->pageNum - 1) * $this->pageSize;
        $ret = [];
        foreach ($list as $item) {
            $tmp = [];
            $tmp['u_id'] = $item['u_id'] ?? 0;
            $tmp['avatar'] = $item["portrait"] ?? "";
            $tmp['user_number'] = $this->getHiddenUserNumber($item['user_number'] ?? '');
            $tmp['nickname'] = $item['nickname'] ?? '';
            $tmp['total_amount'] = (string)$item['total_amount'];
            $tmp['guard_count'] = (int)$item['guard_count'];
            $tmp['rank'] = ++$start;
            $ret[] = $tmp;
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