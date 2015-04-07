<?php

/**
 * Copyright (C) 2012 Vizualizer All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Naohisa Minagawa <info@vizualizer.jp>
 * @copyright Copyright (c) 2010, Vizualizer
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 * @since PHP 5.3
 * @version   1.0.0
 */

/**
 * ルーティン設定からリツイートの予約を行うバッチです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Batch_RoutineRetweet extends Vizualizer_Plugin_Batch
{

    public function getName()
    {
        return "Routine Retweets";
    }

    public function getFlows()
    {
        return array("routineRetweets");
    }

    /**
     * リツイート予約を登録する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function routineRetweets($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("RetweetRoutine");

        // 当日と翌日の曜日名を取得する。
        $todayName = strtolower(Vizualizer::now()->date("l"));
        $tomorrowName = strtolower(Vizualizer::now()->strToTime("+1 day")->date("l"));

        // 本体の処理を実行
        $routines = $model->findAllBy(array("gt:" . $todayName . "+" . $tomorrowName => "0"));
        foreach($routines as $routine){
            unset($reserveTime);
            if ($routine->$todayName == "1" && Vizualizer::now()->date("H:i:s") < $routine->schedule_retweet_time) {
                // 当日の予約を設定可能
                $reserveTime = Vizualizer::now()->strToTime($routine->schedule_retweet_time);
            } elseif ($routine->$tomorrowName == "1" && $routine->schedule_retweet_time <= Vizualizer::now()->date("H:i:s")) {
                $reserveTime = Vizualizer::now()->strToTime("+1 day")->strToTime($routine->schedule_retweet_time);
            }
            if (isset($reserveTime)) {
                Vizualizer_Logger::writeInfo("Check Reservaton for " . $routine->routine_id . " in " . $reserveTime->date("Y-m-d"));
                // 同日に同じルーティンのIDの予約が存在するかチェック
                $reservation = $loader->loadModel("RetweetReservation");
                $reservation->findBy(array("routine_id" => $routine->routine_id, "for:scheduled_retweet_time" => $reserveTime->date("Y-m-d")));
                if (!($reservation->reservation_id > 0)) {
                    // 同日に同じルーティンが予約されていない場合は登録を実行
                    $accountIds = array();

                    // 該当のグループのアカウントを取得する。
                    if(isset($routine->retweet_group)){
                        $group = $loader->loadModel("AccountGroup");
                        if ($routine->retweet_group > 0) {
                            $groups = $group->findAllBy(array("group_id" => $routine->retweet_group));
                        } else {
                            $groups = $group->findAllBy(array());
                        }
                        foreach($groups as $group){
                            $accountIds[$group->account_id] = $group->account_id;
                        }
                    }
                    /*
                    shuffle($accountIds);
                    if($post["max_accounts"] > 0 && $post["max_accounts"] < count($accountIds)){
                        $accountIds = array_slice($accountIds, 0, $post["max_accounts"]);
                    }
                    */

                    if (count($accountIds) > 0) {
                        Vizualizer_Logger::writeDebug("Target URL : " . $routine->retweet_url);
                        if(preg_match("/^https?:\\/\\/twitter\\.com\\/([a-zA-Z0-9_]+)\\/status\\/([0-9]+)\\/?$/", trim($routine->retweet_url), $p) > 0){
                            $tweetId = $p[2];
                            $tweet = $loader->loadModel("TweetLog");
                            $tweet->findBy(array("twitter_id" => $tweetId));

                            // トランザクションの開始
                            $connection = Vizualizer_Database_Factory::begin("twitter");
                            try {
                                if (!($tweet->tweet_id > 0)) {
                                    // ツイートが未登録の場合は自動でツイートを登録
                                    $account = $loader->loadModel("Account");
                                    $accountIdArray = array_values($accountIds);
                                    $account->findByPrimaryKey($accountIdArray[0]);
                                    $tweetData = $account->getTwitter()->statuses_show_ID(array("id" => $tweetId));
                                    $tweet->twitter_id = $tweetData->id_str;
                                    $tweet->screen_name = $tweetData->user->screen_name;
                                    $tweet->tweet_time = Vizualizer::now()->strToTime($tweetData->created_at)->date("Y-m-d H:i:s");
                                    $tweet->tweet_type = 4;
                                    if(is_array($tweetData->entities->media)){
                                        foreach($tweetData->entities->media as $media){
                                            if($media->type == "photo"){
                                                $tweet->media_link = $media->media_url;
                                                $tweet->media_url = $media->url;
                                                $tweetData->text = str_replace($media->url, "", $tweetData->text);
                                                break;
                                            }
                                        }
                                    }
                                    $tweet->tweet_text = $tweetData->text;
                                    $tweet->retweet_count = $tweetData->retweet_count;
                                    $tweet->favorite_count = $tweetData->favorite_count;
                                    Vizualizer_Logger::writeInfo("Update Tweet for ".$tweet->twitter_id);
                                    $tweet->save();
                                }

                                // RT予約を登録
                                $reservation = $loader->loadModel("RetweetReservation");
                                $reservation->routine_id = $routine->routine_id;
                                if($tweet->tweet_log_id > 0){
                                    $reservation->retweet_url = "https://twitter.com/".$tweet->account()->screen_name."/status/".$tweetId;
                                }else{
                                    $reservation->retweet_url = $routine->retweet_url;
                                }
                                $reservation->retweet_group = $routine->retweet_group;
                                $reservation->max_accounts = 0;
                                $reservation->scheduled_retweet_time = $reserveTime->date("Y-m-d H:i:s");
                                if($routine->retweet_duration > 0){
                                    $reservation->scheduled_cancel_retweet_time = $reserveTime->strToTime("+" . $routine->retweet_duration . " hour")->date("Y-m-d H:i:s");
                                }
                                $reservation->save();

                                foreach($accountIds as $accountId){
                                    if($accountId != $tweet->account_id){
                                        $model = $loader->loadModel("Retweet");
                                        $model->account_id = $accountId;
                                        $model->tweet_id = $tweetId;
                                        $model->reservation_id = $reservation->reservation_id;
                                        $model->scheduled_retweet_time = $reservation->scheduled_retweet_time;
                                        $model->scheduled_cancel_retweet_time = $reservation->scheduled_cancel_retweet_time;
                                        $model->save();
                                    }
                                }

                                // エラーが無かった場合、処理をコミットする。
                                Vizualizer_Database_Factory::commit($connection);
                            } catch (Exception $e) {
                                Vizualizer_Database_Factory::rollback($connection);
                                throw new Vizualizer_Exception_Database($e);
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }
}
