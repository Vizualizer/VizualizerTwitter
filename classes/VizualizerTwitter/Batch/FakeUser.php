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
 * ツイッターへのアクセスをユーザー擬態させるためのバッチスクリプト
 * UpdateAccounts、SearchFollowAccounts、FollowAccounts、FollowedAccounts、FollowingAccounts、
 * UnfollowAccounts、Tweets、Retweets、UpdateTweets
 * のバッチの代用として動作させることを目的とする。
 * ただし、個別のバッチと比較した場合、アカウントごとに順次実行となるため、アカウント数が少ない場合、処理件数が十分でなくなる可能性がある。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Batch_FakeUser extends Vizualizer_Plugin_Batch
{
    // 作成する最大プロセス数
    const PROCESSES_MAX = 6;

    // アカウントごとの処理状況の管理データ
    private static $statuses = array();

    // プロセスIDを管理する変数
    private static $pids = array();

    // 検索用のページデータ
    private static $pages = array();

    public function getDaemonName()
    {
        return "fake_user";
    }

    public function getName()
    {
        return "Fake User access";
    }

    public function getFlows()
    {
        return array("fakeUser");
    }

    /**
     * ユーザー擬態処理のメイン
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function fakeUser($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("Account");
        $accounts = $model->findAllBy(array());
        $maxProcesses = (self::PROCESSES_MAX < $accounts->count())?self::PROCESSES_MAX:$accounts->count();

        foreach ($accounts as $account) {
            // アカウントIDが正しくない場合はスキップ
            if (!($account->account_id > 0)) {
                continue;
            }

            // Twitterのプロキシをリセット
            $account->getTwitter(true);

            // プロセスをフォークできるかどうか確認
            if (function_exists("pcntl_fork")) {
                //子プロセス生成
                $pid = pcntl_fork();
                if ($pid == -1) {
                    // fork失敗
                    Vizualizer_Logger::writeError("プロセスの作成に失敗しました。");
                    exit(1);
                } elseif ($pid) {
                    $this->pids[$pid] = TRUE;
                    while(($pid = pcntl_wait($status, WNOHANG)) > 0){
                        unset($this->pids[$pid]);
                    }
                    if ( count($this->pids) >= $maxProcesses ) {
                        unset($this->pids[pcntl_wait($status)]);
                    }
                } else {
                    // メイン処理の実行
                    Vizualizer_Logger::writeInfo("Start subprocess for account(".$account->account_id.")");
                    // 本番処理の前に最大30秒待機
                    sleep(mt_rand(1, 15));
                    $this->process($account);
                    Vizualizer_Logger::writeInfo("Finish subprocess for account(".$account->account_id.")");
                    exit(0);
                }
            } else {
                $this->process($account);
            }
        }

        return $data;
    }

    /**
     * ツイッターのメイン処理
     *
     * @param unknown $account
     */
    private function process($account)
    {
        if (!array_key_exists($account->account_id, self::$statuses) || self::$statuses[$account->account_id] == 0) {
            self::$statuses[$account->account_id] = 1;
            Vizualizer_Logger::writeInfo("Start Twitter Process for account(".$account->account_id.")");

            // アカウントの情報を更新
            $this->updateAccount($account);

            // アカウントがツイートを行う必要があるか調べる。
            $accountStatus = $account->status();
            if (time() < strtotime($accountStatus->next_tweet_time) || !$this->tweet($account)) {
                $loader = new Vizualizer_Plugin("Twitter");
                $model = $loader->loadModel("Retweet");
                $retweets = $model->findAllBy(array("account_id" => $account->account_id, "le:scheduled_retweet_time" => Vizualizer::now()->date("Y-m-d H:i:s"), "retweet_time" => "0000-00-00 00:00:00"), "scheduled_retweet_time", false);
                $cancelRetweets = $model->findAllBy(array("account_id" => $account->account_id, "ne:retweet_tweet_id" => "", "le:scheduled_cancel_retweet_time" => Vizualizer::now()->date("Y-m-d H:i:s"), "cancel_retweet_time" => "0000-00-00 00:00:00"), "scheduled_retweet_time", false);
                if ($retweets->count() > 0 || $cancelRetweets->count() > 0) {
                    // リツイートを実行
                    $this->retweet($account, $retweets, $cancelRetweets);
                } elseif (!($accountStatus->follow_status > 0 && strtotime($accountStatus->next_follow_time) < time()) || !$this->unfollowAccount($account) || !$this->followAccount($account)) {
                    // フォロー対象を検索
                    $this->searchFollowAccount($account);
                }
            }
            self::$statuses[$account->account_id] = 0;
        }
    }

    /**
     * アカウント情報を取得し、情報を更新する。
     */
    private function updateAccount($account)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("Account");

        // Twitterへのアクセスを初期化
        $twitter = $account->getTwitter();

        // ユーザー情報を取得
        $user = $twitter->users_show(array("user_id" => $account->twitter_id));
        Vizualizer_Logger::writeDebug(print_r($user));

        if (isset($user->id) && !empty($user->id)) {
            // TwitterのIDが取得できた場合のみ更新
            $connection = Vizualizer_Database_Factory::begin("twitter");

            try {
                // アカウント情報を登録
                $account->twitter_id = $user->id;
                $account->screen_name = $user->screen_name;
                $account->name = $user->name;
                $account->profile_image_url = $user->profile_image_url;
                $account->tweet_count = $user->statuses_count;
                $account->friend_count = $user->friends_count;
                $account->follower_count = $user->followers_count;
                $account->favorite_count = $user->favourites_count;
                $account->notification = $user->notifications;
                $setting = $loader->loadModel("GlobalSetting");
                $setting->findByOperator($account->operator_id);
                if($setting->global_setting_id > 0){
                    $account->rakuten_application_id = $setting->rakuten_application_id;
                    $account->rakuten_affiliate_id = $setting->rakuten_affiliate_id;
                }
                echo "Update Account for ".$account->account_id."  : \r\n";
                $account->save();

                $today = Vizualizer::now()->date("Y-m-d");
                $follow = $loader->loadModel("Follow");
                $searched = $follow->countBy(array("account_id" => $account->account_id, "back:create_time" => $today));
                $followed = $account->friend_count;
                $refollowed = $account->follower_count;
                $unfollowed = $follow->countBy(array("account_id" => $account->account_id, "back:friend_cancel_date" => $today));
                $followHistory = $loader->loadModel("FollowHistory");
                for ($i = 1; $i <= 3; $i ++) {
                    $thisDay = date("Y-m-d", strtotime("-".$i." day"));
                    $followHistory->findBy(array("account_id" => $account->account_id, "history_date" => $thisDay));
                    if(!($followHistory->follow_history_id > 0)){
                        $followHistory->account_id = $account->account_id;
                        $followHistory->history_date = $thisDay;
                        $followHistory->follow_count = $followed;
                        $followHistory->followed_count = $refollowed;
                        $followHistory->unfollow_count = $unfollowed;
                        $followHistory->save();
                    }
                }
                $followHistory->findBy(array("account_id" => $account->account_id, "history_date" => $today));
                if(!($followHistory->follow_history_id > 0)){
                    $followHistory->account_id = $account->account_id;
                    $followHistory->history_date = $today;
                }
                $followHistory->target_count = $searched;
                $followHistory->follow_count = $followed;
                $followHistory->followed_count = $refollowed;
                $followHistory->unfollow_count = $unfollowed;
                $followHistory->save();

                // フォローリストがあってフォローリスト無しのステータスの場合は、待機中に変更
                $followListCount = $follow->countBy(array("account_id" => $account->account_id, "friend_date" => null));
                if($followListCount > 0){
                    $accountStatus = $account->status();
                    if($accountStatus->follow_status == VizualizerTwitter_Model_AccountStatus::FOLLOW_NODATA) {
                        $accountStatus->updateFollow(VizualizerTwitter_Model_AccountStatus::FOLLOW_STANDBY);
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

    /**
     * ツイートを実行
     */
    private function tweet($account)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("AccountStatus");

        $status = $account->status();
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
                return false;
            }elseif($tweetSetting->daytime_end < $tweetSetting->daytime_start && (Vizualizer::now()->date("H") >= $tweetSetting->daytime_end && Vizualizer::now()->date("H") < $tweetSetting->daytime_start)){
                // END < STARTの場合は、その間に含まれる場合のみツイートしない。
                Vizualizer_Logger::writeInfo($account->screen_name . " : Skip tweet for daytime.");
                return false;
            }
        }

        // アカウントのステータスが有効のアカウントのみを対象とする。
        if ($status->tweet_status != "1" && $status->original_status != "1" && $status->advertise_status != "1" && $status->rakuten_status != "1") {
            Vizualizer_Logger::writeInfo($account->screen_name . " : Account BOT is not active.");
            return false;
        }

        $application = $account->application();
        if ($application->suspended != 0) {
            Vizualizer_Logger::writeInfo($account->screen_name . " : Application was suspended.");
            return false;
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
        $tweetLog->screen_name = $account->screen_name;
        $tweetLog->tweet_time = Vizualizer::now()->date("Y-m-d H:i:s");

        $tweetAdvertises = $account->tweetAdvertises();
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

        if (empty($tweetLog->tweet_text)) {
            Vizualizer_Logger::writeInfo($account->screen_name . " : Tweet is empty.");
            return false;
        }

        try {
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
                    $status->updateStatus(VizualizerTwitter_Model_AccountStatus::ACCOUNT_SUSPENDED);
                }else{
                    // アカウント凍結中を解除
                    $status->updateStatus(VizualizerTwitter_Model_AccountStatus::ACCOUNT_OK);
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
                Vizualizer_Logger::writeDebug(print_r($result, true));
                Vizualizer_Logger::writeInfo($account->screen_name . " : error in Post tweet : " . $tweetLog->tweet_text);
            }
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
        return true;
    }

    /**
     * リツイートを実行
     */
    private function retweet($account, $retweets, $cancelRetweets)
    {
        $loader = new Vizualizer_Plugin("Twitter");

        $checkRetweet = $loader->loadModel("Retweet");
        $checkCount = $checkRetweet->countBy(array("ge:retweet_time" => Vizualizer::now()->strToTime("-2 minute")->date("Y-m-d H:i:s")));
        if($checkCount < mt_rand(2, 4)){
            foreach ($retweets as $retweet) {
                $account = $retweet->account();

                // リツイートを実施
                $twitter = $account->getTwitter();
                $result = $twitter->statuses_retweet_ID(array("id" => $retweet->tweet_id));
                Vizualizer_Logger::writeInfo("Retweeted for : " . $retweet->tweet_id . " with " . print_r($result, true));
                if (isset($result->errors)) {
                    if ($result->errors[0]->code == "327") {
                        // すでにRTされている場合はすべてのステータスをRT済みにする。
                        $connection = Vizualizer_Database_Factory::begin("twitter");
                        try {
                            $targets = $retweet->findAllBy(array("account_id" => $account->account_id, "tweet_id" => $reweet->tweet_id, "retweet_time" => "0000-00-00 00:00:00"));
                            foreach ($targets as $target){
                                $target->retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                                $target->save();
                            }
                            Vizualizer_Database_Factory::commit($connection);
                        } catch (Exception $e) {
                            Vizualizer_Database_Factory::rollback($connection);
                            throw new Vizualizer_Exception_Database($e);
                        }
                    }
                    break;
                    Vizualizer_Logger::writeError("Failed to Retweet on " . $retweet->tweet_id . " in " . $account->screen_name . " by " . print_r($result->errors, true));
                } elseif (!empty($result->id)) {
                    // リツイートを更新
                    $connection = Vizualizer_Database_Factory::begin("twitter");
                    try {
                        $retweet->retweet_tweet_id = $result->id_str;
                        $retweet->retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                        $retweet->save();

                        Vizualizer_Database_Factory::commit($connection);

                        Vizualizer_Logger::writeInfo("Retweeted for : " . $retweet->tweet_id . " in " . $account->screen_name . " with " . print_r($result, true));
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                } else {
                    Vizualizer_Logger::writeDebug(print_r($result, true));
                    Vizualizer_Logger::writeInfo($account->screen_name . " : error in Post tweet : " . $tweetLog->tweet_text);
                }
            }
        }

        // キャンセルの本体の処理を実行
        $checkCount = $checkRetweet->countBy(array("ge:cancel_retweet_time" => Vizualizer::now()->strToTime("-2 minute")->date("Y-m-d H:i:s")));
        if ($checkCount < mt_rand(2, 4)) {
            foreach ($cancelRetweets as $retweet) {
                // リツイートを実施
                $twitter = $account->getTwitter();
                $result = $twitter->statuses_destroy_ID(array("id" => $retweet->retweet_tweet_id));
                if (isset($result->errors)) {
                    if ($result->errors[0]->code == "144") {
                        // すでに削除されている場合はキャンセル済みフラグを立てる。
                        $connection = Vizualizer_Database_Factory::begin("twitter");
                        try {
                            $retweet->cancel_retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                            $retweet->save();
                            Vizualizer_Database_Factory::commit($connection);
                        } catch (Exception $e) {
                            Vizualizer_Database_Factory::rollback($connection);
                            throw new Vizualizer_Exception_Database($e);
                        }
                    }
                    Vizualizer_Logger::writeError("Failed to cancel retweet on " . $retweet->retweet_tweet_id . " in " . $account->screen_name . " by " . print_r($result->errors, true));
                } elseif (!empty($result->id)) {

                    // リツイートを更新
                    $connection = Vizualizer_Database_Factory::begin("twitter");
                    try {
                        $retweet->cancel_retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                        $retweet->save();

                        Vizualizer_Database_Factory::commit($connection);

                        Vizualizer_Logger::writeInfo("Deleted Retweet for : " . $retweet->retweet_tweet_id . " with " . print_r($result, true));
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                } else {
                    Vizualizer_Logger::writeInfo($account->screen_name . " : error in delete tweet : " . $tweetLog->tweet_text);
                }
            }
        }
    }

    /**
     * フォロー対象の検索を実行
     */
    private function searchFollowAccount($account)
    {
        $loader = new Vizualizer_Plugin("Twitter");

        if(!array_key_exists($account->account_id, self::$pages) || !(self::$pages[$account->account_id] > 0) || self::$pages[$account->account_id] > 50){
            self::$pages[$account->account_id] = 1;
        }
        $page = self::$pages[$account->account_id] ++;

        Vizualizer_Logger::writeInfo("Seach start : " . $account->screen_name);
        $setting = $account->followSetting();
        $follow = $loader->loadModel("Follow");
        $searched = $follow->countBy(array("account_id" => $account->account_id, "back:create_time" => Vizualizer::now()->date("Y-m-d")));
        if($setting->follow_type == "1" || $setting->follow_type == "3"){
            // 検索キーワードを取得する。
            $admin = Vizualizer_Session::get(VizualizerAdmin::SESSION_KEY);
            $keywords = explode("\r\n", str_replace(" ", "\r\n", str_replace("　", " ", $setting->follow_keywords)));
            // キーワードのリストをシャッフルする
            shuffle($keywords);

            // フォロー対象の検索処理は当日のターゲット追加数が一日のフォロー数上限の2倍以下の未満の場合のみ
            if ($searched < $setting->daily_follows * 2) {
                // ユーザー情報を検索
                foreach($keywords as $keyword){
                    if(empty($keyword)){
                        continue;
                    }
                    $users = (array) $account->getTwitter()->users_search(array("q" => $keyword, "page" => $page, "count" => 20));
                    unset($users["httpstatus"]);
                    Vizualizer_Logger::writeInfo("Search Users（".count($users)."） for ".$keyword." in page ".$page." in " . $account->screen_name);
                    foreach ($users as $index => $user) {
                        $account->addUser($user);
                        $searched ++;
                        if ($searched > $setting->daily_follows * 2) {
                            break;
                        }
                    }
                    if ($searched > $setting->daily_follows * 2) {
                        break;
                    }
                }
            }
        }

        // フォロワーを追加
        if($setting->follow_type == "2" || $setting->follow_type == "3"){
            // 検索キーワードを取得する。
            $admin = Vizualizer_Session::get(VizualizerAdmin::SESSION_KEY);
            $keywords = explode("\r\n", str_replace(" ", "\r\n", str_replace("　", " ", $setting->follower_keywords)));
            // キーワードのリストをシャッフルする
            shuffle($keywords);

            // フォロー対象の検索処理は当日のターゲット追加数が一日のフォロー数上限の2倍以下の未満の場合のみ
            if ($searched < $setting->daily_follows * 2) {
                // ユーザー情報を検索
                foreach($keywords as $keyword){
                    if(empty($keyword)){
                        continue;
                    }
                    $users = (array) $account->getTwitter()->users_search(array("q" => $keyword, "page" => $page, "count" => 20));
                    unset($users["httpstatus"]);
                    Vizualizer_Logger::writeInfo("Search Users（".count($users)."） for ".$keyword." in page ".$page." in " . $account->screen_name);
                    // ユーザーのフォロワーを取得
                    $user = $users[array_rand($users)];
                    $followers = $account->getTwitter()->followers_ids(array("user_id" => $user->id, "count" => "5000"));

                    if (!isset($followers->ids) || !is_array($followers->ids)) {
                        break;
                    }

                    if(count($followers->ids) > 100){
                        shuffle($followers->ids);
                        $followers->ids = array_splice($followers->ids, 0, 100);
                    }

                    $followerIds = implode(",", $followers->ids);
                    // ユーザーのフォロワーを取得
                    $followers = $account->getTwitter()->users_lookup(array("user_id" => $followerIds));

                    foreach($followers as $follower){
                        if(isset($follower->id) && $follower->id > 0){
                            if($account->checkAddUser($follower)){
                                $account->addUser($follower);
                                $searched ++;
                            }
                        }
                        if ($searched > $setting->daily_follows * 2) {
                            break;
                        }
                    }
                    if ($searched > $setting->daily_follows * 2) {
                        break;
                    }
                }
            }
        }

        if(!empty($setting->follow_account)){
            // フォローアカウントがURL形式だった場合にスクリーン名を取得
            if (preg_match("/^https?:\\/\\/twitter.com\\/([^\\/]+)\\/?/", trim($setting->follow_account), $terms) > 0) {
                $screen_name = $terms[1];
            }else{
                $screen_name = trim($setting->follow_account);
            }

            // フォロー対象の検索処理は当日のターゲット追加数が一日のフォロー数上限の2倍以下の未満の場合のみ
            Vizualizer_Logger::writeInfo("Search target for follow account : " . $screen_name . "(" . $setting->daily_follows . ")");
            if ($searched < $setting->daily_follows * 2) {
                // ユーザーのフォロワーを取得
                $followers = $account->getTwitter()->followers_ids(array("screen_name" => $screen_name, "count" => "5000"));

                if (isset($followers->ids) && is_array($followers->ids)) {

                    if(count($followers->ids) > 100){
                        shuffle($followers->ids);
                        $followers->ids = array_splice($followers->ids, 0, 100);
                    }

                    $followedCount = 0;
                    $followerIds = implode(",", $followers->ids);
                    // ユーザーのフォロワーを取得
                    $followers = $account->getTwitter()->users_lookup(array("user_id" => $followerIds));

                    foreach($followers as $follower){
                        if(is_object($follower) && property_exists($follower, "status") && property_exists($follower->status, "created_at")){
                            if(isset($follower->id) && $follower->id > 0){
                                if($account->checkAddUser($follower)){
                                    $account->addUser($follower);
                                    $searched ++;
                                }
                            }
                        }
                        if ($searched > $setting->daily_follows * 2) {
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * フォローを実行
     */
    private function followAccount($account)
    {
        $status = $account->status();

        // フォロー可能状態で無い場合はスキップ
        if(!$account->isFollowable()){
            Vizualizer_Logger::writeInfo("Skip for not followable in ".$account->screen_name);
            return false;
        }

        $loader = new Vizualizer_Plugin("Twitter");

        // 終了ステータスでここに来た場合は日付が変わっているため、待機中に遷移
        if ($status->follow_status == VizualizerTwitter_Model_AccountStatus::FOLLOW_FINISHED || $status->follow_status == VizualizerTwitter_Model_AccountStatus::UNFOLLOW_FINISHED) {
            Vizualizer_Logger::writeInfo("Account reactivated by end status in ".$account->screen_name);
            $status->updateFollow(VizualizerTwitter_Model_AccountStatus::FOLLOW_STANDBY);
        }

        // アカウントのステータスが待機中か実行中のアカウントのみを対象とする。
        if ($status->follow_status != VizualizerTwitter_Model_AccountStatus::FOLLOW_STANDBY && $status->follow_status != VizualizerTwitter_Model_AccountStatus::FOLLOW_RUNNING && $status->follow_status != VizualizerTwitter_Model_AccountStatus::UNFOLLOW_RUNNING) {
            Vizualizer_Logger::writeInfo("Account is not ready in ".$account->screen_name);
            return false;
        }

        // フォロー設定を取得
        $setting = $account->followSetting();

        // 前日のフォロー状況を取得
        $history = $loader->loadModel("FollowHistory");
        $yesterday = Vizualizer::now()->strToTime("-1 day")->date("Y-m-d");
        $history->findBy(array("account_id" => $account->account_id, "history_date" => $yesterday));

        // アカウントのフォロー数が1日のフォロー数を超えた場合はステータスを終了にしてスキップ
        if ($setting->daily_follows <= $account->friend_count - $history->follow_count) {
            $status->updateFollow(VizualizerTwitter_Model_AccountStatus::FOLLOW_FINISHED, Vizualizer::now()->strToTime("+1 day")->date("Y-m-d 00:00:00"), true);
            Vizualizer_Logger::writeInfo("Over daily follows for ".($account->friend_count - $history->follow_count)." to ".$setting->daily_follows." in ".$account->screen_name);
            return false;
        }

        // リストを取得する。
        $follow = $loader->loadModel("Follow");
        $follow->limit(1, 0);
        $searchParams = array("account_id" => $account->account_id, "friend_date" => null, "friend_cancel_date" => null);
        $sortOrder = "COALESCE(follow_date, CASE WHEN favorited_date IS NOT NULL THEN DATE_SUB(favorited_date, INTERVAL 7 DAY) ELSE NULL END)";
        if(Vizualizer_Configure::get("refollow_enabled") === false){
            // リフォローを行わない設定にしている場合、自分をフォローしているユーザーは対象外とする。
            $searchParams["follow_date"] = null;
        }
        if($setting->follow_target == 2){
            $searchParams["ne:follow_date*favorited_date"] = null;
        }elseif($setting->follow_target == 1){
            $searchParams["follow_date"] = null;
            $searchParams["ne:favorited_date"] = null;
        }
        $follows = $follow->findAllBy($searchParams, $sortOrder, true);

        // 結果が0件の場合はリスト無しにしてスキップ
        if ($follows->count() == 0) {
            $status->updateFollow(VizualizerTwitter_Model_AccountStatus::FOLLOW_NODATA);
            Vizualizer_Logger::writeInfo("No List in ".$account->screen_name);
            return false;
        }

        // ステータスを実行中に変更
        $status->updateFollow(VizualizerTwitter_Model_AccountStatus::FOLLOW_RUNNING);

        $result = false;
        foreach ($follows as $follow) {
            $result = $follow->follow();
        }

        if($result){
            if ($status->follow_count < $setting->follow_unit - 1) {
                $status->updateFollow(VizualizerTwitter_Model_AccountStatus::FOLLOW_RUNNING, Vizualizer::now()->strToTime("+".$setting->follow_interval." second")->date("Y-m-d H:i:s"));
            } else {
                $status->updateFollow(VizualizerTwitter_Model_AccountStatus::FOLLOW_STANDBY, Vizualizer::now()->strToTime("+".$setting->follow_unit_interval." minute")->date("Y-m-d H:i:s"), true);
            }
        }
        return true;
    }

    /**
     * アンフォロー実行
     */
    private function unfollowAccount($account)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $status = $account->status();

        // アンフォロー可能状態で無い場合はスキップ
        if(!$account->isUnfollowable()){
            Vizualizer_Logger::writeInfo("Skip for not unfollowable in ".$account->screen_name);
            return false;
        }

        $loader = new Vizualizer_Plugin("Twitter");

        // 終了ステータスでここに来た場合は日付が変わっているため、待機中に遷移
        if ($status->follow_status == VizualizerTwitter_Model_AccountStatus::FOLLOW_FINISHED || $status->follow_status == VizualizerTwitter_Model_AccountStatus::UNFOLLOW_FINISHED) {
            $status->updateFollow(VizualizerTwitter_Model_AccountStatus::FOLLOW_STANDBY);
        }

        // アカウントのステータスが待機中か実行中のアカウントのみを対象とする。
        if ($status->follow_status != VizualizerTwitter_Model_AccountStatus::FOLLOW_STANDBY && $status->follow_status != VizualizerTwitter_Model_AccountStatus::FOLLOW_RUNNING && $status->follow_status != VizualizerTwitter_Model_AccountStatus::UNFOLLOW_RUNNING) {
            Vizualizer_Logger::writeInfo("Account is not ready in ".$account->screen_name);
            return false;
        }

        $setting = $account->followSetting();
        if(!($setting->unfollow_interval > 0)){
            $setting->unfollow_interval = $setting->follow_interval;
        }
        if(!($setting->daily_unfollows > 0)){
            $setting->daily_unfollows = $setting->daily_follows;
        }

        // 本日のフォロー状況を取得
        $history = $loader->loadModel("FollowHistory");
        $today = Vizualizer::now()->date("Y-m-d");
        $history->findBy(array("account_id" => $account->account_id, "history_date" => $today));

        // アカウントのアンフォロー数が1日のアンフォロー数を超えた場合はステータスを終了にしてスキップ
        $follow = $loader->loadModel("Follow");
        $unfollowed = $follow->countBy(array("account_id" => $account->account_id, "back:friend_cancel_date" => $today));
        if ($setting->daily_unfollows <= $unfollowed) {
            $status->updateFollow(VizualizerTwitter_Model_AccountStatus::UNFOLLOW_FINISHED, Vizualizer::now()->strToTime("+1 day")->date("Y-m-d 00:00:00"), true);
            Vizualizer_Logger::writeInfo("Over daily unfollows for ".$unfollowed." to ".$setting->daily_unfollows." in ".$account->screen_name);
            return false;
        }

        // リストを取得する。
        $follow = $loader->loadModel("Follow");
        $follow->limit(1, 0);
        $follows = $follow->findAllBy(array("account_id" => $account->account_id, "le:friend_date" => Vizualizer::now()->strToTime("-".$setting->refollow_timeout." hour")->date("Y-m-d H:i:s"), "follow_date" => null, "friend_cancel_date" => null), "friend_date", false);

        // ステータスを実行中に変更
        $status->updateFollow(VizualizerTwitter_Model_AccountStatus::UNFOLLOW_RUNNING);

        $result = false;
        foreach ($follows as $follow) {
            $result = $follow->unfollow();
        }

        if($result){
            if($status->follow_count < $setting->unfollow_unit - 1){
                $status->updateFollow(VizualizerTwitter_Model_AccountStatus::UNFOLLOW_RUNNING, Vizualizer::now()->strToTime("+".$setting->unfollow_interval." second")->date("Y-m-d H:i:s"));
            }else{
                $status->updateFollow(VizualizerTwitter_Model_AccountStatus::FOLLOW_STANDBY, Vizualizer::now()->strToTime("+".$setting->unfollow_unit_interval." minute")->date("Y-m-d H:i:s"), true);
            }
        }

        return true;
    }
}
