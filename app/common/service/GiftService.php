<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-09-07
 * Time: 11:15
 */

namespace app\common\service;

use app\common\AppException;
use app\common\enum\ChatStatusEnum;
use app\common\enum\DbDataIsDeleteEnum;
use app\common\enum\GiftIsSaleEnum;
use app\common\helper\Redis;
use app\common\model\ChatModel;
use app\common\model\ConfigGiftModel;
use think\facade\Db;

class GiftService extends Base
{
    /**
     * 所有上架的礼物
     * @return array
     */
    public function getAll()
    {
        // 首先从缓存读取数据
        $redis = Redis::factory();
        $gifts = getAllSaleGift($redis);

        // 缓存失效从数据库获取数据，并重新生成缓存
        if (empty($gifts)) {
            $giftConfigModel = new ConfigGiftModel();
            $gifts = $giftConfigModel->getAll();
            cacheAllSaleGift($gifts, $redis);
        }

        // 格式化返回数据
        $returnData = [];
        foreach ($gifts as $gift) {
            $returnData[] = [
                "id" => $gift["id"],
                "name" => $gift["name"],
                "image_url" => $gift["image_url"],
                "price" => $gift["price"],
            ];
        }
        return $returnData;
    }

    public function give($user, $rUNumber, $giftId)
    {
        $redis = Redis::factory();

        // 礼物详情
        $gift = self::getGiftById($giftId, $redis);

        // 礼物接收用户
        $receiveUser = UserService::getUserByNumber($rUNumber, $redis);
        if (empty($receiveUser)) {
            throw AppException::factory(AppException::QUERY_INVALID);
        }

        // 用户处于聊天中，不允许调用该接口发送礼物
        $chatModel = new ChatModel();
        $chat = $chatModel->where("t_u_id", $user["id"])
            ->where("status", ChatStatusEnum::CALLING)
            ->find();
        if ($chat) {
            throw AppException::factory(AppException::QUERY_URL_ERROR);
        }

        // 礼物赠送过程
        Db::startTrans();
        try {

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

    }

    public static function getGiftById($giftId, $redis)
    {
        $gift = getGiftByIdOnRedis($giftId, $redis);
        if (empty($gift)) {
            $giftConfigModel = new ConfigGiftModel();
            $gift = $giftConfigModel->findById($giftId);
            if (empty($gift)) {
                throw AppException::factory(AppException::QUERY_INVALID);
            }
            if ($gift["is_sale"] == GiftIsSaleEnum::NO ||
                $gift["is_delete"] == DbDataIsDeleteEnum::YES)
            {
                throw AppException::factory(AppException::GIFT_OFFLINE);
            }
            $gift = $gift->toArray();
        }
        return $gift;
    }
}