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
 * アカウント情報の更新バッチです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Batch_Tweets extends Vizualizer_Plugin_Batch
{

    public function getDaemonName()
    {
        return "tweets";
    }

    public function getName()
    {
        return "Post Tweets";
    }

    public function getFlows()
    {
        return array("postTweets");
    }

    /**
     * ツイートを投稿する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function postTweets($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("AccountStatus");

        // 本体の処理を実行
        $statuses = $model->findAllBy(array("le:next_tweet_time" => Vizualizer::now()->date("Y-m-d H:i:s")), "next_tweet_time", false);

        foreach ($statuses as $status) {
            $loader = new Vizualizer_Plugin("Twitter");
            $account = $status->account();
            if(!($account->account_id > 0)){
                continue;
            }
            $tweetSetting = $account->tweetSetting();

            // 日中のみフラグの場合は夜間スキップ
            if ($tweetSetting->daytime_flg == "1"){
                // 数値が設定されていない場合は7時から24時に設定
                if(!is_numeric($tweetSetting->daytime_start) || !is_numeric($tweetSetting->daytime_end)){
                    $tweetSetting->daytime_start = 7;
                    $tweetSetting->daytime_end = 0;
                }
                // 時間の指定が0時から23時の間に無い場合は0時に変更
                if(!($tweetSetting->daytime_start >= 0 && $tweetSetting->daytime_start < 24)){
                    $tweetSetting->daytime_start = 0;
                }
                if(!($tweetSetting->daytime_end >= 0 && $tweetSetting->daytime_end < 24)){
                    $tweetSetting->daytime_end = 0;
                }
                if($tweetSetting->daytime_start < $tweetSetting->daytime_end && (Vizualizer::now()->date("H") < $tweetSetting->daytime_start || Vizualizer::now()->date("H") >= $tweetSetting->daytime_end)){
                    // START < ENDの場合は、その間に含まれる場合のみツイートする。
                    Vizualizer_Logger::writeInfo($account->screen_name . " : Skip tweet for daytime.");
                    continue;
                }elseif($tweetSetting->daytime_end < $tweetSetting->daytime_start && (Vizualizer::now()->date("H") >= $tweetSetting->daytime_end && Vizualizer::now()->date("H") < $tweetSetting->daytime_start)){
                    // END < STARTの場合は、その間に含まれる場合のみツイートしない。
                    Vizualizer_Logger::writeInfo($account->screen_name . " : Skip tweet for daytime.");
                    continue;
                }
            }

            // アカウントのステータスが有効のアカウントのみを対象とする。
            if (
                $account->status()->tweet_status != "1" && $account->status()->original_status != "1" && $account->status()->advertise_status != "1" && $account->status()->rakuten_status != "1") {
                Vizualizer_Logger::writeInfo($account->screen_name . " : Account BOT is not active.");
                continue;
            }

            $today = Vizualizer::now()->date("Y-m-d");

            // ログを取得する。
            $tweetLogs = $account->tweetLogs();
            $count = 0;
            $lastTweetId = 0;
            foreach ($tweetLogs as $tweetLog) {
                if ($tweetLog->tweet_type != "2") {
                    if ($lastTweetId == 0) {
                        $lastTweetId = $tweetLog->tweet_id;
                    }
                    $count ++;
                } else {
                    break;
                }
                if ($tweetSetting->advertise_interval < $count) {
                    break;
                }
            }
            // トランザクションの開始
            $tweetLog = $loader->loadModel("TweetLog");
            $tweetLog->account_id = $account->account_id;
            $tweetLog->tweet_time = Vizualizer::now()->date("Y-m-d H:i:s");

            $application = $account->application();
            $tweetAdvertises = $account->tweetAdvertises();
            if ($application->suspended == 0) {
                if ($tweetSetting->advertise_interval <= $count && $tweetAdvertises->count() > 0) {
                    $advertise = $tweetAdvertises->current()->findByPrefer();
                    if ($advertise->advertise_id > 0) {
                        Vizualizer_Logger::writeInfo($account->screen_name . " : use advertise because " . $tweetSetting->advertise_interval . " < " . $count);
                        // 広告を取得し記事を作成
                        $tweetLog->tweet_id = 0;
                        $tweetLog->tweet_type = 2;
                        $tweetLog->tweet_text = $advertise->advertise_text;
                        if (!empty($advertise->fixed_advertise_url)) {
                            $tweetLog->tweet_text .= " " . $advertise->fixed_advertise_url;
                        }
                        Vizualizer_Logger::writeInfo($account->screen_name . " : prepare to Tweet advertise text.");
                    }
                } else {
                    // ツイートを取得し、記事を作成
                    $tweets = $account->tweets();
                    if ($tweets->count() > 0) {
                        $tweet = $tweets->current()->findByPrefer();
                        if ($tweet->tweet_id > 0) {
                            $tweetLog->tweet_id = $tweet->tweet_id;
                            $tweetLog->tweet_type = 1;
                            $tweetLog->tweet_text = $tweet->tweet_text;
                            $tweetLog->media_url = $tweet->media_url;
                            $tweetLog->media_filename = $tweet->media_filename;
                            Vizualizer_Logger::writeInfo($account->screen_name . " : prepare to Tweet normal text.");
                        }
                    }
                }

                try {
                    if (!empty($tweetLog->tweet_text)) {
                        if (!empty($tweetLog->media_filename)) {
                            $params = array("status" => str_replace(" " . $tweetLog->media_url, "", $tweetLog->tweet_text));
                            $params["media[]"] = VIZUALIZER_SITE_ROOT.Vizualizer_Configure::get("twitter_image_savepath")."/".$tweetLog->media_filename;
                            $result = $account->getTwitter()->statuses_updateWithMedia($params);
                        } else {
                            $result = $account->getTwitter()->statuses_update(array("status" => $tweetLog->tweet_text));
                        }
                        if (isset($result->errors)) {
                            if ($result->errors[0]->code == "261") {
                                // アプリ自体が凍結中の場合は
                                $connection = Vizualizer_Database_Factory::begin("twitter");
                                try {
                                    $application = $account->application();
                                    $application->suspended = "1";
                                    $application->save();
                                    Vizualizer_Logger::writeInfo("Tweet is blocked by not writable from " . $this->user_id . " in " . $account->screen_name);
                                    Vizualizer_Database_Factory::commit($connection);
                                } catch (Exception $e) {
                                    Vizualizer_Database_Factory::rollback($connection);
                                    throw new Vizualizer_Exception_Database($e);
                                }
                            } elseif ($result->errors[0]->code == "226") {
                                // ツイート処理がスパム扱いされた場合は、次のツイートを翌日にする。
                                $connection = Vizualizer_Database_Factory::begin("twitter");
                                try {
                                    Vizualizer_Logger::writeInfo("Tweet is blocked by spam-like from " . $this->user_id . " in " . $account->screen_name);
                                    $status->next_tweet_time = Vizualizer::now()->strToTime("+1 day")->date("Y-m-d H:i:s");
                                    Vizualizer_Logger::writeInfo($account->screen_name . " : Next tweet at : " . $status->next_tweet_time);
                                    $status->save();
                                    Vizualizer_Database_Factory::commit($connection);
                                } catch (Exception $e) {
                                    Vizualizer_Database_Factory::rollback($connection);
                                    throw new Vizualizer_Exception_Database($e);
                                }
                            } elseif ($result->errors[0]->code == "32") {
                                // アプリ自体が凍結中の場合は
                                $connection = Vizualizer_Database_Factory::begin("twitter");
                                try {
                                    $application = $account->application();
                                    $application->suspended = "2";
                                    $application->save();
                                    Vizualizer_Logger::writeInfo("Tweet is blocked by frozen from " . $this->user_id . " in " . $account->screen_name);
                                    Vizualizer_Database_Factory::commit($connection);
                                } catch (Exception $e) {
                                    Vizualizer_Database_Factory::rollback($connection);
                                    throw new Vizualizer_Exception_Database($e);
                                }
                            } elseif ($result->errors[0]->code == "64") {
                                // アカウントのステータスを凍結中に変更
                                $account->status()->updateStatus(VizualizerTwitter_Model_AccountStatus::ACCOUNT_SUSPENDED);
                            }else{
                                // アカウント凍結中を解除
                                $account->status()->updateStatus(VizualizerTwitter_Model_AccountStatus::ACCOUNT_OK);
                            }
                            Vizualizer_Logger::writeError("Failed to Tweet on " . $this->user_id . " in " . $account->screen_name . " by " . print_r($result->errors, true));
                        } elseif (!empty($result->id)) {
                            $connection = Vizualizer_Database_Factory::begin("twitter");
                            Vizualizer_Logger::writeInfo($account->screen_name . " : Post tweet(" . $result->id . ") : " . $tweetLog->tweet_text);
                            $tweetLog->twitter_id = $result->id;
                            $tweetLog->tweet_text = $result->text;
                            if(property_exists($result->entities, "media") && is_array($result->entities->media) && count($result->entities->media) > 0){
                                $media = $result->entities->media[0];
                                $tweetLog->media_url = $media->url;
                                $tweetLog->media_link = $media->media_url;
                            }else{
                                $tweetLog->media_url = "";
                                $tweetLog->media_link = "";
                            }
                            $tweetLog->save();

                            if($status->retweet_status == "1" && $tweetSetting->retweet_group_id > 0){
                                // 強化アカウントで、対象グループが設定されている場合は、リツイートの登録も併せて行う。
                                $group = $loader->loadModel("AccountGroup");
                                $groups = $group->findAllBy(array("group_id" => $tweetSetting->retweet_group_id));
                                foreach($groups as $group){
                                    if($account->account_id != $group->account_id){
                                        $model = $loader->loadModel("Retweet");
                                        $model->account_id = $group->account_id;
                                        $model->tweet_id = $result->id;
                                        if($tweetSetting->retweet_delay > 0){
                                            $model->scheduled_retweet_time = Vizualizer::now()->strToTime("+" . $tweetSetting->retweet_delay . "minute")->date("Y-m-d H:i:s");
                                        }else{
                                            $model->scheduled_retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                                        }
                                        if($tweetSetting->retweet_duration > 0){
                                            $model->scheduled_cancel_retweet_time = Vizualizer::now()->strToTime("+" . $tweetSetting->retweet_duration . "hour")->date("Y-m-d H:i:s");
                                        }
                                        $model->save();
                                    }
                                }
                            }

                            $interval = $tweetSetting->tweet_interval;
                            if ($tweetSetting->wavy_flg == "1") {
                                $interval = mt_rand(0, 20) + $interval - 10;
                            }

                            Vizualizer_Logger::writeInfo($account->screen_name . " : Use interval : " . $interval);
                            $status->next_tweet_time = Vizualizer::now()->strToTime("+" . $interval . " minute")->date("Y-m-d H:i:s");
                            Vizualizer_Logger::writeInfo($account->screen_name . " : Next tweet at : " . $status->next_tweet_time);
                            $status->save();

                            Vizualizer_Database_Factory::commit($connection);
                        } else {
                            Vizualizer_Logger::writeInfo($account->screen_name . " : error in Post tweet : " . $tweetLog->tweet_text);
                        }
                    }
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            }
        }

        return $data;
    }
}
