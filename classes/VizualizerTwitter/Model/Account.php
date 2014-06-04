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
 * アカウントのモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_Account extends Vizualizer_Plugin_Model
{
    const FOLLOW_MODE_NORMAL = "2";
    const FOLLOW_MODE_SAFE = "1";

    const FOLLOW_STATUS_SUSPEND = 0;
    const FOLLOW_STATUS_STANDBY = 1;
    const FOLLOW_STATUS_RUNNING = 2;
    const FOLLOW_STATUS_CLOSED = 3;
    const FOLLOW_STATUS_NOLIST = 4;
    const FOLLOW_STATUS_RETRY = 5;
    const FOLLOW_STATUS_SKIPPED = 6;

    const TWEET_STATUS_SUSPENDED = 0;
    const FOLLOW_STATUS_ACTIVATED = 1;

    /**
     * ツイッターアクセス用のインスタンス
     */
    private $twitter;

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("Accounts"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $account_id アカウントID
     */
    public function findByPrimaryKey($account_id)
    {
        $this->findBy(array("account_id" => $account_id));
    }

    /**
     * グループIDでデータを取得する。
     *
     * @param $group_id グループID
     * @return アカウントのリスト
     */
    public function findAllByGroupId($group_id)
    {
        return $this->findAllBy(array("group_id" => $group_id));
    }

    /**
     * アプリケーションIDでデータを取得する。
     *
     * @param $application_id アプリケーションID
     * @return アカウントのリスト
     */
    public function findAllByApplicationId($application_id)
    {
        return $this->findAllBy(array("application_id" => $application_id));
    }

    /**
     * サーバーIDでデータを取得する。
     *
     * @param $server_id サーバーID
     * @return アカウントのリスト
     */
    public function findAllByServerId($server_id)
    {
        return $this->findAllBy(array("server_id" => $server_id));
    }

    /**
     * アカウントのフォローの限界値を取得する
     */
    public function followLimit(){
        if($this->follower_count < 1819){
            return 2000;
        }else{
            return floor($this->follower_count * 1.1);
        }
    }

    /**
     * アカウントに紐づいたアプリケーションを取得する
     *
     * @return アプリケーション
     */
    public function application()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $application = $loader->loadModel("Application");
        $application->findByPrimaryKey($this->application_id);
        return $application;
    }

    /**
     * アカウントに紐づいたサーバーを取得する
     *
     * @return サーバー
     */
    public function server()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $server = $loader->loadModel("Server");
        $server->findByPrimaryKey($this->server_id);
        return $server;
    }

    /**
     * アカウントに紐づいたステータス情報を取得する
     *
     * @return ステータス
     */
    public function status()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $accountStatus = $loader->loadModel("AccountStatus");
        $accountStatus->findByAccountId($this->account_id);
        if(!($accountStatus->account_status_id > 0)){
            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                $accountStatus->account_id = $this->account_id;
                $accountStatus->save();
                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
            }
        }
        return $accountStatus;
    }

    /**
     * アカウントに紐づいたフォロー設定を取得する
     *
     * @return 詳細設定のリスト
     */
    public function followSetting()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $setting = $loader->loadModel("Setting");
        $setting->findByOperatorAccount($this->operator_id, $this->account_id);
        if($setting->use_follow_setting != "1"){
            $setting->findByOperatorAccount($this->operator_id, "0");
        }
        $setting->follow_interval = $setting->follow_interval_1;
        $setting->refollow_timeout = $setting->refollow_timeout_1;
        $setting->daily_follows = $setting->daily_follows_1;
        for($i = 2; $i < 6; $i ++){
            $key = "follower_limit_".$i;
            if($setting->$key > 0 && $setting->$key < $this->follower_count){
                $key = "follow_interval_".$i;
                $setting->follow_interval = $setting->$key;
                $key = "refollow_timeout_".$i;
                $setting->refollow_timeout = $setting->$key;
                $key = "daily_follows_".$i;
                $setting->daily_follows = $setting->$key;
            }
        }
        return $setting;
    }

    /**
     * アカウントに紐づいたツイート設定を取得する
     *
     * @return 詳細設定のリスト
     */
    public function tweetSetting()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $setting = $loader->loadModel("Setting");
        $setting->findByOperatorAccount($this->operator_id, $this->account_id);
        if($setting->use_tweet_setting != "1"){
            $setting->findByOperatorAccount($this->operator_id);
        }
        return $setting;
    }

    /**
     * アカウントに紐づいたフォローを取得する
     *
     * @return フォローのリスト
     */
    public function follows()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $follow = $loader->loadModel("Follow");
        return $follow->findAllByAccountId($this->account_id);
    }

    /**
     * アカウントに紐づいたフォローを取得する
     *
     * @return フォローのリスト
     */
    public function followHistorys($days = 0)
    {
        $loader = new Vizualizer_Plugin("twitter");
        $follow = $loader->loadModel("FollowHistory");
        return $follow->findAllByAccountId($this->account_id, $days);
    }

    /**
     * アカウントに紐づいたツイートを取得する
     *
     * @return ツイートのリスト
     */
    public function tweets($sort = "", $reverse = false)
    {
        $loader = new Vizualizer_Plugin("twitter");
        $tweet = $loader->loadModel("Tweet");
        $tweets = $tweet->findAllByAccountId($this->account_id, $sort, $reverse);
        return $tweets;
    }

    /**
     * アカウントに紐づいたツイート広告を取得する
     *
     * @return ツイート広告のリスト
     */
    public function tweetAdvertises($sort = "", $reverse = false)
    {
        $loader = new Vizualizer_Plugin("twitter");
        $tweetAdvertise = $loader->loadModel("TweetAdvertise");
        $tweetAdvertises = $tweetAdvertise->findAllByAccountId($this->account_id, $sort, $reverse);
        return $tweetAdvertises;
    }

    /**
     * アカウントに紐づいたツイートログを取得する
     *
     * @return ツイートログのリスト
     */
    public function tweetLogs($sort = "tweet_time", $reverse = true)
    {
        $loader = new Vizualizer_Plugin("twitter");
        $tweetLog = $loader->loadModel("TweetLog");
        $tweetLogs = $tweetLog->findAllByAccountId($this->account_id, $sort, $reverse);
        return $tweetLogs;
    }

    /**
     * ツイッターAPI用のオブジェクトを取得
     */
    public function getTwitter(){
        if(!$this->twitter){
            $application = $this->application();
            \Codebird\Codebird::setConsumerKey($application->api_key, $application->api_secret);
            $this->twitter = \Codebird\Codebird::getInstance();
            $this->twitter->setToken($this->access_token, $this->access_token_secret);
        }
        return $this->twitter;
    }

    /**
     * フォローステータスを更新する
     *
     * @param int $status フォローステータス
     * @param int $next 次回のフォロー実行時間
     * @param boolean $reset フォローカウントのリセットフラグ（$nextが設定された場合、trueならカウントを0に、falseならカウントを1加算）
     */
    public function updateFollowStatus($status, $next = "", $reset = false)
    {
        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");
        try {
            $status = $this->status();
            $status->follow_status = $status;
            if(!empty($next)){
                $status->next_follow_time = $next;
                if($reset){
                    $status->follow_count = 0;
                }else{
                    $status->follow_count ++;
                }
            }
            $status->save();
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
    }

    /**
     * ツイートステータスを更新する
     *
     * @param int $status ツイートステータス
     * @param int $next 次回のツイート実行時間
     */
    public function updateTweetStatus($status, $next = "")
    {
        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");
        try {
            $status = $this->status();
            $status->tweet_status = $status;
            if(!empty($next)){
                $status->next_tweet_time = $next;
            }
            $this->save();
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
    }
}
