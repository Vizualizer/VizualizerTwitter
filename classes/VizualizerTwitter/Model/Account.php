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
     * 属性をキャッシュするための変数
     */
    private static $attributes;

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
    public function followLimit()
    {
        $setting = $this->followSetting();
        $limit =  floor($setting->follow_ratio * $this->follower_count / 100);
        if($limit < $setting->daily_follows){
            return $setting->daily_follows;
        }
        return $limit;
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
     * 属性のリストを取得するための変数
     */
    public function attributes(){
        if(!isset(self::$attributes)){
            $loader = new Vizualizer_Plugin("twitter");
            $setting = $loader->loadModel("Setting");
            $settings = $setting->findAllBy(array());
            $attributes = array();
            foreach($settings as $setting){
                if(!empty($setting->account_attribute)){
                    $attributes[$setting->account_attribute] = $setting->account_attribute;
                }
            }
            self::$attributes = $attributes;
        }
        return self::$attributes;
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
        if (!($accountStatus->account_status_id > 0)) {
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
     * 追加可能かどうかのチェックを行う。
     */
    public function checkAddUser($user, $followSetting = null){
        // ユーザーの形式で無い場合はスキップ
        if(is_numeric($user) || !isset($user->id)){
            return false;
        }

        // ユーザーのIDが取得できない場合はスキップ
        if(!($user->id > 0)){
            echo "Skipped invalid ID : ".$user->id." in ".$index."\r\n";
            return false;
        }

        // 日本語チェックに引っかかる場合はスキップ
        if($followSetting == null){
            $setting = $this->followSetting();
        }else{
            $setting = $followSetting;
        }
        if ($setting->japanese_flg == "1" && $user->lang != "ja") {
            Vizualizer_Logger::writeInfo("Skipped invalid not Japanese : ".$user->screen_name);
            return false;
        }

        // デフォルト画像チェックに引っかかる場合はスキップ
        if ($setting->unique_icon_flg == "1" && $user->default_profile_image) {
            Vizualizer_Logger::writeInfo("Skipped invalid Default icon : ".$user->screen_name);
            return false;
        }

        // ボットチェックに引っかかる場合はスキップ
        if ($setting->non_bot_flg == "1" && preg_match("/BOT|ボット|ﾎﾞｯﾄ/ui", $user->description) > 0) {
            Vizualizer_Logger::writeInfo("Skipped invalid Bot : ".$user->screen_name);
            return false;
        }

        // 拒絶キーワードを含む場合はスキップ
        if (!empty($setting->ignore_keywords) && preg_match("/" . implode("|", explode("\r\n", $setting->ignore_keywords)) . "/u", $user->description) > 0) {
            Vizualizer_Logger::writeInfo("Skipped invalid Profile : ".$user->screen_name);
            return false;
        }
        return true;
    }

    /**
     * フレンドでもフォロワーでもないユーザーを追加する。
     */
    public function addUser($user){
        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");

        try {
            // フォロー対象に追加
            $loader = new Vizualizer_Plugin("Twitter");

            // フォローのデータを追加
            $follow = $loader->loadModel("Follow");
            // フレンドやフォローでないとして追加する場合はレコードが無いことが条件
            if($follow->countBy(array("account_id" => $this->account_id, "user_id" => $user->id)) == 0){
                // 更新対象を取得する場合はアンフォローのレコードを除外
                $follow->account_id = $this->account_id;
                $follow->user_id = $user->id;
                $follow->save();
            }

            // エラーが無かった場合、処理をコミットする。
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
    }

    /**
     * フレンドとしてユーザーを追加する。
     */
    public function addFriend($user){
        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");

        try {
            // フォロー対象に追加
            $loader = new Vizualizer_Plugin("Twitter");

            // 対象がアカウントのフレンドであることを確認したとして最終確認日時を更新
            $follow = $loader->loadModel("AccountFriend");
            $follow->findBy(array("account_id" => $this->account_id, "user_id" => $user->id));
            $follow->account_id = $this->account_id;
            $follow->user_id = $user->id;
            $follow->checked_time = Vizualizer::now()->date("Y-m-d H:i:s");
            $follow->save();

            // フォローのデータを追加
            $follow = $loader->loadModel("Follow");
            // フレンドとして追加する場合はフォロー済みor相互フォローのレコードが無いことが条件
            if($follow->countBy(array("account_id" => $this->account_id, "user_id" => $user->id, "ne:friend_date" => null, "friend_cancel_date" => null)) == 0){
                // 更新対象を取得する場合はアンフォローのレコードを除外
                $follow->findBy(array("account_id" => $this->account_id, "user_id" => $user->id, "friend_cancel_date" => null));
                if(!($follow->follow_id > 0)){
                    $follow->account_id = $this->account_id;
                    $follow->user_id = $user->id;
                }
                $follow->friend_date = Vizualizer::now()->date("Y-m-d H:i:s");
                $follow->save();
            }

            // エラーが無かった場合、処理をコミットする。
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
    }

    /**
     * フォロワーを追加する。
     */
    public function addFollower($user){
        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");

        try {
            $loader = new Vizualizer_Plugin("Twitter");

            // 対象がアカウントのフォロワーであることを確認したとして、最終確認日時を更新
            $follow = $loader->loadModel("AccountFollower");
            $follow->findBy(array("account_id" => $this->account_id, "user_id" => $user->id));
            $follow->account_id = $this->account_id;
            $follow->user_id = $user->id;
            $follow->checked_time = Vizualizer::now()->date("Y-m-d H:i:s");
            $follow->save();

            // フォローのデータを追加
            $follow = $loader->loadModel("Follow");
            // フォロワーとして追加する場合は被フォローor相互フォローのレコードが無いことが条件
            if($follow->countBy(array("account_id" => $this->account_id, "user_id" => $user->id, "ne:follow_date" => null)) == 0){
                // 更新対象を取得する場合はアンフォローのレコードを除外
                $follow->findBy(array("account_id" => $this->account_id, "user_id" => $user->id, "friend_cancel_date" => null));
                if(!($follow->follow_id > 0)){
                    $follow->account_id = $this->account_id;
                    $follow->user_id = $user->id;
                }
                $follow->follow_date = Vizualizer::now()->date("Y-m-d H:i:s");
                $follow->save();
            }

            // エラーが無かった場合、処理をコミットする。
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
    }

    /**
     * アカウントがフォロー処理可能か調べる
     *
     * @return true：フォロー可能／false：フォロー不可能
     */
    public function isFollowable()
    {
        // 24時間以内にアンフォローが無く、上限に達していない場合はフォロー可能
        $loader = new Vizualizer_Plugin("twitter");
        $follow = $loader->loadModel("Follow");
        $unfollowCount = $follow->countBy(array("account_id" => $this->account_id, "ge:friend_cancel_date" => Vizualizer::now()->strTotime("-2 hour")->date("Y-m-d 00:00:00")));
        if ($unfollowCount == 0 && $this->friend_count < $this->followLimit()) {
            return true;
        }
        Vizualizer_Logger::writeInfo("Over the limit follows  as " . $this->followLimit() . " < " . $this->friend_count . " in " . $this->screen_name);
        return false;
    }

    /**
     * アカウントがアンフォロー処理可能か調べる
     *
     * @return true：アンフォロー可能／false：アンフォロー不可能
     */
    public function isUnfollowable()
    {
        // フォロワーが1818人以下の場合、フレンドが2000人に達している場合は設定に関係なくアンフォローを行うようにする。
        if($this->follower_count <= 1819 && $this->friend_count >= 2000){
            Vizualizer_Logger::writeInfo("Unfollow for over 2000 friends in ".$this->screen_name);
            return true;
        }
        // 24時間以内にアンフォローが存在するか上限に達している場合で、リフォロー期限を超えているフォローが存在している場合はアンフォロー可能
        $loader = new Vizualizer_Plugin("twitter");
        $follow = $loader->loadModel("Follow");
        $unfollowCount = $follow->countBy(array("account_id" => $this->account_id, "ge:friend_cancel_date" => Vizualizer::now()->strTotime("-3 hour")->date("Y-m-d 00:00:00")));
        $refollowCount = $follow->countBy(array("account_id" => $this->account_id, "follow_date" => null, "le:friend_date" => Vizualizer::now()->strTotime("-" . $this->followSetting()->refollow_timeout . " hour")->date("Y-m-d H:i:s")));
        Vizualizer_Logger::writeInfo("Account check for ".$this->followLimit()."  < ".$this->friend_count." and ".$unfollowCount." unfollows and ".$refollowCount." refollows in ".$this->screen_name);
        if (($this->followLimit() < $this->friend_count || $unfollowCount > 0) && $refollowCount > 0) {
            return true;
        }
        return false;
    }

    /**
     * アカウントに紐づいた設定を取得する
     *
     * @return 詳細設定のリスト
     */
    public function setting()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $setting = $loader->loadModel("Setting");
        $setting->findBy(array("operator_id" => $this->operator_id, "account_id" => $this->account_id));
        return $setting;
    }

    /**
     * アカウントグループを取得
     */
    public function accountGroups(){
        $loader = new Vizualizer_Plugin("twitter");
        $model = $loader->loadModel("AccountGroup");
        return $model->findAllByAccountId($this->account_id);
    }

    /**
     * アカウントオペレータを取得
     */
    public function accountOperators(){
        $loader = new Vizualizer_Plugin("twitter");
        $model = $loader->loadModel("AccountOperator");
        return $model->findAllByAccountId($this->account_id);
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
        $historysTemp = $follow->findAllByAccountId($this->account_id, $days + 1);
        $historysTemp2 = array();
        foreach($historysTemp as $temp){
            $historysTemp2[] = $temp;
        }
        $historys = array();
        for($index = 0; $index < $days; $index ++){
            $historysTemp2[$index]->follow_count = $historysTemp2[$index]->follow_count - $historysTemp2[$index + 1]->follow_count + $historysTemp2[$index]->unfollow_count;
            $historysTemp2[$index]->followed_count = $historysTemp2[$index]->followed_count - $historysTemp2[$index + 1]->followed_count;
            $historys[] = $historysTemp2[$index];
        }
        return $historys;
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
     * アカウントに紐づいたリツイートログを取得する
     *
     * @return リツイートログのリスト
     */
    public function retweets($sort = "retweet_time", $reverse = true)
    {
        $loader = new Vizualizer_Plugin("twitter");
        $retweet = $loader->loadModel("Retweet");
        $retweets = $retweet->findAllByAccountId($this->account_id, $sort, $reverse);
        return $retweets;
    }

    /**
     * アカウントに紐づいたツイートログを件数制限して取得する
     *
     * @return ツイートログのリスト
     */
    public function limitedTweetLogs($limit, $offset = 0, $sort = "tweet_time", $reverse = true)
    {
        $loader = new Vizualizer_Plugin("twitter");
        $tweetLog = $loader->loadModel("TweetLog");
        $tweetLog->limit($limit, $offset);
        $tweetLogs = $tweetLog->findAllByAccountId($this->account_id, $sort, $reverse);
        return $tweetLogs;
    }

    /**
     * 一度もツイートしていないツイートデータの件数を取得する。
     * @return int ツイートの件数
     */
    public function getPreTweetCount(){
        $loader = new Vizualizer_Plugin("twitter");
        $tweetLog = $loader->loadModel("Tweet");
        $count = $tweetLog->countBy(array("account_id" => $this->account_id, "first_tweeted_flg" => "0"));
        return $count;

    }

    /**
     * ツイッターAPI用のオブジェクトを取得
     */
    public function getTwitter()
    {
        if (!$this->twitter) {
            $application = $this->application();
            \Codebird\Codebird::setConsumerKey($application->api_key, $application->api_secret);
            $this->twitter = \Codebird\Codebird::getInstance();
            $this->twitter->setToken($this->access_token, $this->access_token_secret);
        }
        return $this->twitter;
    }
}
