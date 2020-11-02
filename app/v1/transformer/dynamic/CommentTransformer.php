<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-08-27
 * Time: 14:15
 */

namespace app\v1\transformer\dynamic;

use app\common\service\UserInfoService;
use app\common\transformer\TransformerAbstract;

class CommentTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        $current = UserInfoService::getUserInfoById($data['u_id']);
        $toUser = UserInfoService::getUserInfoById($data['to_user']);
        $pidPath = $data["pid_path"] ?? "";
        $c = count(explode("-", $pidPath));
        return [
            'id' => (int)$data["id"] ?? 0,
            'u_id' => (int)$data['u_id'] ?? 0,
            'avatar' => (string)$current["portrait"] ?? "",
            'nickname' => (string)$current['nickname'] ?? '',
            'create_time' => date("m-d H:i"),
            'content' => (string)$data["content"],
            'pid' => (int)$data['pid'],
            'show_to_user' => $c >= 4 ? 1 : 0,
            'to_user' => (string)$toUser["nickname"] ?? "",
            'is_self' => 1,
            'comment' => []
        ];
    }
}