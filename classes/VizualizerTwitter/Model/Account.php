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
    public function followLimit()
    {
        $setting = $this->followSetting();
        return floor($setting->follow_ratio * $this->follower_count / 100);
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
     * フォローデータを登録する。
     */
    public function addFollowUser($user, $asFriend = false, $asFollower = false) {
        // ユーザーのIDが取得できない場合はスキップ
        if(!($user->id > 0)){
            echo "Skipped invalid ID : ".$user->id." in ".$index."\r\n";
            return false;
        }

        // 日本語チェックに引っかかる場合はスキップ
        $setting = $this->followSetting();
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

        // フォロー対象に追加済みの場合はスキップ
        $loader = new Vizualizer_Plugin("Twitter");
        $follow = $loader->loadModel("Follow");
        $follow->findBy(array("account_id" => $this->account_id, "user_id" => $user->screen_name));

        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");

        try {
            // フォロー対象に追加
            $follow->account_id = $this->account_id;
            $follow->user_id = $user->id;

            if ($follow->follow_id > 0) {
                if ($asFriend && empty($follow->friend_date)) {
                    // フレンドとして登録する場合は、既存のデータを更新する。
                    $follow->friend_date = date("Y-m-d H:i:s");
                } elseif ($asFollower && empty($follow->follow_date)) {
                    if (!empty($follow->friend_date) && !empty($follow->friend_cancel_date)) {
                        // フォロワーとして登録する場合で、フォローとキャンセルが両方とも設定されている場合は一旦削除する。
                        $follow->delete();
                        $follow = $loader->loadModel("Follow");
                    }
                    $follow->follow_date = date("Y-m-d H:i:s");
                } else {
                    // フレンドとしてもフォロワーとしても無い形で登録する場合は、既に登録済みとしてスキップする。
                    Vizualizer_Logger::writeInfo("Skipped targeted : ".$user->screen_name." was followed");
                    return false;
                }
            }
            $follow->save();
            Vizualizer_Logger::writeInfo("Add follow target : ".$user->screen_name."(".$user->lang.")");

            // エラーが無かった場合、処理をコミットする。
            Vizualizer_Database_Factory::commit($connection);

            // リスト無しステータスの場合は待機中ステータスに移行
            if ($this->status()->follow_status == "4") {
                $this->status()->updateFollow(1);
            }
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
        $unfollowCount = $follow->countBy(array("account_id" => $this->account_id, "ge:friend_cancel_date" => date("Y-m-d 00:00:00", strtotime("-3 hour"))));
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
        // 24時間以内にアンフォローが存在するか上限に達している場合で、リフォロー期限を超えているフォローが存在している場合はアンフォロー可能
        $loader = new Vizualizer_Plugin("twitter");
        $follow = $loader->loadModel("Follow");
        $unfollowCount = $follow->countBy(array("account_id" => $this->account_id, "ge:friend_cancel_date" => date("Y-m-d 00:00:00", strtotime("-3 hour"))));
        $refollowCount = $follow->countBy(array("account_id" => $this->account_id, "follow_date" => null, "le:friend_date" => date("Y-m-d H:i:s", strtotime("-" . $this->followSetting()->refollow_timeout . " hour"))));
        if (($this->followLimit() < $this->friend_count || $unfollowCount > 0) && $refollowCount > 0) {
            return true;
        }
        return false;
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
