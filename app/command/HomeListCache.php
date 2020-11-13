<?php
/**
 * Created by PhpStorm.
 * User: yangxiushan
 * Date: 2020-11-13
 * Time: 14:30
 */
namespace app\command;

use app\common\helper\Redis;
use app\common\helper\WeChatWork;
use app\common\service\CityService;
use app\common\service\ScoreService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * 首页数据缓存
 */
class HomeListCache extends Command
{
    use CommandTrait;

    private $beginTime;

    protected function configure()
    {
        // setName 设置命令行名称
        $this->setName('chat_zhiliao:HomeListCache');
    }

    protected function execute(Input $input, Output $output)
    {

        try {
            $this->beginTime = time();
            while(time() - $this->beginTime <= $this->maxAllowTime) {
                $this->doWork();
                sleep(60);
            }
        } catch (\Throwable $e) {
            $error = [
                "script" => self::class,
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "message" => $e->getMessage(),
            ];
            Log::write(json_encode($error), "error");
            $errorMessage = "";
            foreach ($error as $key=>$value) {
                $errorMessage .= "$key: " . $value . "\n";
            }
            $this->sendWeChatWorkMessage($errorMessage, WeChatWork::$user["yangxiushan"]);
        }
    }

    private function doWork()
    {
        $redis = Redis::factory();
        $this->recommendList($redis);
        $this->newList($redis);
        $redis->close();
    }

    private function recommendList($redis)
    {
        $homeCondition1 = "0:0";
        $recommendList1 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("ui.photos", "<>", "[]")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList1 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList1[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList1[$key]["photos"]);

            $recommendList1[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList1[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList1[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList1[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList1[$key]["total_score"]);
            unset($recommendList1[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList1, $homeCondition1, $redis);

        $homeCondition2 = "0:100_200";
        $recommendList2 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", "<", "200")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList2 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList2[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList2[$key]["photos"]);

            $recommendList2[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList2[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList2[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList2[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList2[$key]["total_score"]);
            unset($recommendList2[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList2, $homeCondition2, $redis);

        $homeCondition3 = "0:200_350";
        $recommendList3 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", ">=", "200")
            ->where("uw.income_total_amount", "<", "350")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList3 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList3[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList3[$key]["photos"]);

            $recommendList3[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList3[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList3[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList3[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList3[$key]["total_score"]);
            unset($recommendList3[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList3, $homeCondition3, $redis);

        $homeCondition4 = "0:350_500";
        $recommendList4 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", ">=", "350")
            ->where("uw.income_total_amount", "<", "500")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList4 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList4[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList4[$key]["photos"]);

            $recommendList4[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList4[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList4[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList4[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList4[$key]["total_score"]);
            unset($recommendList4[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList4, $homeCondition4, $redis);

        $homeCondition5 = "1:0";
        $recommendList5 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "1")
            ->where("ui.photos", "<>", "[]")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList5 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList5[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList5[$key]["photos"]);

            $recommendList5[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList5[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList5[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList5[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList5[$key]["total_score"]);
            unset($recommendList5[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList5, $homeCondition5, $redis);

        $homeCondition6 = "1:100_200";
        $recommendList6 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "1")
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", "<", "200")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList6 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList6[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList6[$key]["photos"]);

            $recommendList6[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList6[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList6[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList6[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList6[$key]["total_score"]);
            unset($recommendList6[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList6, $homeCondition6, $redis);

        $homeCondition7 = "0:200_350";
        $recommendList7 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "1")
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", ">=", "200")
            ->where("uw.income_total_amount", "<", "350")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList7 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList7[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList7[$key]["photos"]);

            $recommendList7[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList7[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList7[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList7[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList7[$key]["total_score"]);
            unset($recommendList7[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList7, $homeCondition7, $redis);

        $homeCondition8 = "1:350_500";
        $recommendList8 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "1")
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", ">=", "350")
            ->where("uw.income_total_amount", "<", "500")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList8 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList8[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList8[$key]["photos"]);

            $recommendList8[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList8[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList8[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList8[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList8[$key]["total_score"]);
            unset($recommendList8[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList8, $homeCondition8, $redis);

        $homeCondition9 = "2:0";
        $recommendList9 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "2")
            ->where("ui.photos", "<>", "[]")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList9 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList9[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList9[$key]["photos"]);

            $recommendList9[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList9[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList9[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList9[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList9[$key]["total_score"]);
            unset($recommendList9[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList9, $homeCondition9, $redis);

        $homeCondition10 = "2:100_200";
        $recommendList10 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "2")
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", "<", "200")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList10 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList10[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList10[$key]["photos"]);

            $recommendList10[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList10[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList10[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList10[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList10[$key]["total_score"]);
            unset($recommendList10[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList10, $homeCondition10, $redis);

        $homeCondition11 = "2:200_350";
        $recommendList11 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "2")
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", ">=", "200")
            ->where("uw.income_total_amount", "<", "350")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList11 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList11[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList11[$key]["photos"]);

            $recommendList11[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList11[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList11[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList11[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList11[$key]["total_score"]);
            unset($recommendList11[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList11, $homeCondition11, $redis);

        $homeCondition12 = "2:350_500";
        $recommendList12 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "2")
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", ">=", "350")
            ->where("uw.income_total_amount", "<", "500")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList12 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList12[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList12[$key]["photos"]);

            $recommendList12[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList12[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList12[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList12[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList12[$key]["total_score"]);
            unset($recommendList12[$key]["total_users"]);
        }
        cacheUserToHomeRecommendList2($recommendList12, $homeCondition12, $redis);
    }

    private function newList($redis)
    {
        $homeCondition1 = "0:0";
        $recommendList1 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->where("ui.photos", "<>", "[]")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList1 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList1[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList1[$key]["photos"]);

            $recommendList1[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList1[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList1[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList1[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList1[$key]["total_score"]);
            unset($recommendList1[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList1, $homeCondition1, $redis);

        $homeCondition2 = "0:100_200";
        $recommendList2 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", "<", "200")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList2 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList2[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList2[$key]["photos"]);

            $recommendList2[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList2[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList2[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList2[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList2[$key]["total_score"]);
            unset($recommendList2[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList2, $homeCondition2, $redis);

        $homeCondition3 = "0:200_350";
        $recommendList3 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("ui.photos", "<>", "[]")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->where("uw.income_total_amount", ">=", "200")
            ->where("uw.income_total_amount", "<", "350")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList3 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList3[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList3[$key]["photos"]);

            $recommendList3[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList3[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList3[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList3[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList3[$key]["total_score"]);
            unset($recommendList3[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList3, $homeCondition3, $redis);

        $homeCondition4 = "0:350_500";
        $recommendList4 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("ui.photos", "<>", "[]")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->where("uw.income_total_amount", ">=", "350")
            ->where("uw.income_total_amount", "<", "500")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList4 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList4[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList4[$key]["photos"]);

            $recommendList4[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList4[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList4[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList4[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList4[$key]["total_score"]);
            unset($recommendList4[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList4, $homeCondition4, $redis);

        $homeCondition5 = "1:0";
        $recommendList5 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "1")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->where("ui.photos", "<>", "[]")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList1 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList5[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList5[$key]["photos"]);

            $recommendList5[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList5[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList5[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList5[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList5[$key]["total_score"]);
            unset($recommendList5[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList5, $homeCondition5, $redis);

        $homeCondition6 = "1:100_200";
        $recommendList6 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "1")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", "<", "200")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList6 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList6[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList6[$key]["photos"]);

            $recommendList6[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList6[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList6[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList6[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList6[$key]["total_score"]);
            unset($recommendList6[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList6, $homeCondition6, $redis);

        $homeCondition7 = "0:200_350";
        $recommendList7 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "1")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", ">=", "200")
            ->where("uw.income_total_amount", "<", "350")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList7 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList7[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList7[$key]["photos"]);

            $recommendList7[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList7[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList7[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList7[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList7[$key]["total_score"]);
            unset($recommendList7[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList7, $homeCondition7, $redis);

        $homeCondition8 = "1:350_500";
        $recommendList8 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "1")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", ">=", "350")
            ->where("uw.income_total_amount", "<", "500")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList8 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList8[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList8[$key]["photos"]);

            $recommendList8[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList8[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList8[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList8[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList8[$key]["total_score"]);
            unset($recommendList8[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList8, $homeCondition8, $redis);

        $homeCondition9 = "2:0";
        $recommendList9 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "2")
            ->where("ui.photos", "<>", "[]")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList9 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList9[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList9[$key]["photos"]);

            $recommendList9[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList9[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList9[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList9[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList9[$key]["total_score"]);
            unset($recommendList9[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList9, $homeCondition9, $redis);

        $homeCondition10 = "2:100_200";
        $recommendList10 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "2")
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", "<", "200")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList10 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList10[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList10[$key]["photos"]);

            $recommendList10[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList10[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList10[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList10[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList10[$key]["total_score"]);
            unset($recommendList10[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList10, $homeCondition10, $redis);

        $homeCondition11 = "2:200_350";
        $recommendList11 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "2")
            ->where("ui.photos", "<>", "[]")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->where("uw.income_total_amount", ">=", "200")
            ->where("uw.income_total_amount", "<", "350")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList11 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList11[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList11[$key]["photos"]);

            $recommendList11[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList11[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList11[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList11[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList11[$key]["total_score"]);
            unset($recommendList11[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList11, $homeCondition11, $redis);

        $homeCondition12 = "2:350_500";
        $recommendList12 = Db::name("user")->alias("u")
            ->leftJoin("user_info ui", "ui.u_id=u.id")
            ->leftJoin("user_wallet uw", "ui.u_id=u.id")
            ->leftJoin("user_set us", "u.id=us.u_id")
            ->leftJoin("user_score uss", "u.id=uss.u_id")
            ->where("u.sex", "=", "2")
            ->whereTime("ui.create_time", ">=", date("Y-m-d", strtotime("- 2 weeks")))
            ->where("ui.photos", "<>", "[]")
            ->where("uw.income_total_amount", ">=", "350")
            ->where("uw.income_total_amount", "<", "500")
            ->field("u.id,u.user_number,
                uss.total_score,uss.total_users,
                ui.photos,ui.city,ui.signatures,
                us.video_chat_switch,us.video_chat_price,us.voice_chat_switch,us.voice_chat_price")
            ->order("u.sex", "desc")
            ->order("uw.income_total_amount", "desc")
            ->limit(200)
            ->select()->toArray();
        foreach ($recommendList12 as $key => $user) {
            $photos = json_decode($user["photos"], true);
            $recommendList12[$key]["photo"] = isset($photos[0]) ? $photos[0] : "";
            unset($recommendList12[$key]["photos"]);

            $recommendList12[$key]["city"] = CityService::getCityByCode($user["city"]);

            $signatures = json_decode($user["signatures"], true);
            $recommendList12[$key]["signature"] = isset($signatures[0]) ? $signatures[0] : "";
            unset($recommendList12[$key]["signatures"]);

            $score = $user["total_users"] <= 0 ? 0 : (bcdiv($user["total_score"], $user["total_users"], 1));
            $recommendList12[$key]["score"] = ScoreService::getScoreByScore($score);
            unset($recommendList12[$key]["total_score"]);
            unset($recommendList12[$key]["total_users"]);
        }
        cacheUserToHomeNewUserList2($recommendList12, $homeCondition12, $redis);
    }
}