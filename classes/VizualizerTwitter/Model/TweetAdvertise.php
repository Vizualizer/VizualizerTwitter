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
 * ツイート広告のモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_TweetAdvertise extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("TweetAdvertises"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $advertise_id ツイート広告ID
     */
    public function findByPrimaryKey($advertise_id)
    {
        $this->findBy(array("advertise_id" => $advertise_id));
    }

    /**
     * アカウントIDでデータを取得する。
     *
     * @param $account_id アカウントID
     * @return 設定のリスト
     */
    public function findAllByAccountId($account_id, $sort = "", $reverse = false)
    {
        return $this->findAllBy(array("account_id" => $account_id), $sort, $reverse);
    }

    /**
     * アカウント種別でデータを取得する。
     *
     * @param $account_id アカウントID
     * @return 設定のリスト
     */
    public function findAllByAccountType($account_id, $advertise_type, $sort = "", $reverse = false)
    {
        return $this->findAllBy(array("account_id" => $account_id, "advertise_type" => $advertise_type), $sort, $reverse);
    }

    /**
     * 設定に紐づいたアカウントを取得する
     *
     * @return アカウント
     */
    public function account()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $account = $loader->loadModel("Account");
        $account->findByPrimaryKey($this->account_id);
        return $account;
    }

    /**
     * 直近でツイートした履歴を取得する。
     * @param number $count
     * @return ツイート履歴
     */
    protected function getTweetedHistory($count = 10){
        // 過去X件のツイートを取得する。
        $loader = new Vizualizer_Plugin("twitter");
        $tweetLog = $loader->loadModel("TweetLog");
        $tweetLog->limit($count);
        $tweetLogs = $tweetLog->findAllBy(array("account_id" => $this->account_id), "create_time", true);
        $ignoreTweets = array();
        foreach ($tweetLogs as $tweetLog) {
            $ignoreTweets[] = $tweetLog->tweet_text;
        }
        return $ignoreTweets;
    }

    /**
     * ツイート済みのフラグをリセットする。
     * @throws Vizualizer_Exception_Database
     */
    protected function resetTweeted(){
        $tweets = $this->findAllBy(array("account_id" => $this->account_id, "tweeted_flg" => "1"));
        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");
        try {
            foreach ($tweets as $tweet) {
                $tweet->tweeted_flg = 0;
                $tweet->save();
            }
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
    }

    /**
     * 優先度の高いツイートを取得する。
     * @param array $ignoreTweets ツイート履歴
     * @throws Vizualizer_Exception_Database
     * @return 優先度の高いツイート
     */
    protected function getPreferTweet($ignoreTweets){
        // ツイートを取得
        $account = $this->account();
        $status = $account->status();

        $tweetOrder = (($account->tweetSetting()->advertise_order == "1") ? "create_time" : "RAND()");
        if ($status->advertise_status > 0 && $status->rakuten_status > 0) {
            $tweets = $tweet->findAllBy(array("account_id" => $this->account_id, "tweeted_flg" => "0", "nin:advertise_text" => $ignoreTweets), $tweetOrder);
        } elseif ($status->advertise_status > 0) {
            $tweets = $tweet->findAllBy(array("account_id" => $this->account_id, "advertise_type" => "0", "tweeted_flg" => "0", "nin:advertise_text" => $ignoreTweets), $tweetOrder);
        } elseif ($status->rakuten_status > 0) {
            $tweets = $tweet->findAllBy(array("account_id" => $this->account_id, "advertise_type" => "1", "tweeted_flg" => "0", "nin:advertise_text" => $ignoreTweets), $tweetOrder);
        } else {
            $tweets = $tweet->findAllBy(array("account_id" => $this->account_id, "advertise_type" => "-1", "tweeted_flg" => "0", "nin:advertise_text" => $ignoreTweets), $tweetOrder);
        }
        // ツイートが1件以上ある場合は該当のツイートを取得する。
        if ($tweets->count() > 0) {
            $tweet = $tweets->current();
            // トランザクションの開始
            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                $tweet->tweeted_flg = 1;
                $tweet->save();
                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }
            return $tweet;
        }
        return false;
    }

    /**
     * アカウントに紐づいたツイートのうち、優先度の高いものを取得する。
     *
     * @return ツイートのリスト
     */
    public function findByPrefer()
    {
        // ツイート履歴を取得
        $tweeted = $this->getTweetedHistory();

        // 優先ツイートを取得
        $tweet = $this->getPreferTweet($tweeted);

        if($tweet === false){
            // 優先ツイートが取得できなかった場合は、一度ツイート済みフラグをリセットして再取得する。
            $this->resetTweeted();
            $tweet = $this->getPreferTweet($tweeted);
            if($tweet === false){
                $loader = new Vizualizer_Plugin("twitter");
                return $loader->loadModel("TweetAdvertise");
            }
        }
        return $tweet;
    }
}
