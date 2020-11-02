<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/7/31
 * Time: 下午1:12
 */

namespace app\v1\transformer\dynamic;

use app\common\helper\Redis;
use app\common\service\CityService;
use app\common\transformer\TransformerAbstract;
use think\facade\Db;

class InfoTransformer extends TransformerAbstract
{

    public function transformData(array $data): array
    {
        $info = $data["info"] ?? [];
        $comment = $data["comment"] ?? [];
        $likeUserIds = $data["likeUserIds"] ?? [];
        return [
            'id' => $info['id'] ?? 0,
            'u_id' => $info["u_id"] ?? 0,
            'avatar' => $info['portrait'] ?? "",
            'nickname' => $info['nickname'] ?? '',
            'sex' => $info['sex'] ?? 0,
            'age' => $this->getAge($info['birthday'] ?? ""),
            'distance' => (string)$this->getDistance($info['u_id']),
            'city' => empty($info['city']) ? "" : CityService::getCityByCode($info['city']),
            'create_time' => date("Y/m/d", strtotime($info["create_time"])),
            'content' => $info["content"] ?? "",
            'source' => json_decode($info["source"], true),
            'like_count' => $info['like_count'] ?? 0,
            'comment_count' => $info['comment_count'] ?? 0,
            'is_like' => $this->getIsLike($likeUserIds),
            'is_followed' => $this->getIsFollow($info["u_id"]),
            'comment_list' => $this->getComment($comment)
        ];
    }

    private function getAge($birthday)
    {
        return !empty($birthday) ? date('Y') - substr($birthday, 0, 4) : 0;
    }

    private function getDistance($uid)
    {
        if ($this->_queries['id'] == $uid) {
            return 0;
        }

        $redis = Redis::factory();
        return sprintf("%.2f", getDistanceByTwoUserId($uid,$this->_queries['id'],$redis));
    }

    private function getComment($comment)
    {
        $cidToUserNickname = array_column($comment, 'nickname', 'id');
        $commentPidPath = [];
        $ret = [];
        foreach ($comment as $item) {
            $tmp = [];
            $tmp['id'] = $item['id'] ?? 0;
            $tmp['u_id'] = $item['u_id'] ?? 0;
            $tmp["avatar"] = $item['portrait'] ?? '';
            $tmp["nickname"] = $item['nickname'] ?? '';
            $tmp['create_time'] = empty($item['create_time']) ? '' : substr($item["create_time"], 5, 11);
            $tmp['content'] = $item['content'] ?? '';
            $tmp['pid'] = $item['pid'] ?? 0;
            $tmp['show_to_user'] = 0;
            $tmp['to_user'] = isset($cidToUserNickname[$item['pid']]) ? $cidToUserNickname[$item['pid']] : "";
            $tmp['is_self'] = $this->getIsSelf($item['u_id']);
            $tmp['comment'] = [];

            if ($tmp['pid'] == 0) { // 直接评论
                $ret[$tmp["id"]] = $tmp;
                $commentPidPath[$tmp['id']] = $tmp['id'];
            } else {
                $pidPath = $commentPidPath[$tmp['pid']] ?? "";
                $pidPathArr = explode("-", $pidPath);
                $startPid = array_shift($pidPathArr);
                if ($startPid != $tmp['pid']) { // 当前评论父评论不是顶级评论的展示to_user
                    $tmp['show_to_user'] = 1;
                }
                array_unshift($ret[$startPid]['comment'], $tmp);
                $commentPidPath[$tmp['id']] = $pidPath . "-" . $tmp['id'];
            }
        }
        return array_reverse(array_values($ret));
    }

    private function getIsSelf($uid)
    {
        if ($this->_queries['id'] == $uid) {
            return 1;
        }
        return 0;
    }

    private function getIsLike($likeUserIds)
    {
        return in_array($this->_queries['id'], $likeUserIds) ? 1 : 0;
    }

    private function getIsFollow($uid)
    {
        $exists = Db::name("user_follow")->where("u_id", $this->_queries['id'])
            ->where("follow_u_id", $uid)
            ->field("id")->find();
        if (empty($exists)) {
            return 0;
        }
        return 1;
    }
}