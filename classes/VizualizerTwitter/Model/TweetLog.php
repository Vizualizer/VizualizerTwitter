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
 * ツイートログのモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_TweetLog extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("TweetLogs"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $tweet_log_id ツイートログID
     */
    public function findByPrimaryKey($tweet_log_id)
    {
        $this->findBy(array("tweet_log_id" => $tweet_log_id));
    }

    /**
     * アカウントIDでデータを取得する。
     *
     * @param $account_id アカウントID
     * @return 設定のリスト
     */
    public function findAllByAccountId($account_id)
    {
        return $this->findAllBy(array("account_id" => $account_id));
    }

    /**
     * ツイートIDでデータを取得する。
     *
     * @param $tweet_id ツイートID
     * @return 設定のリスト
     */
    public function findAllByTweetId($tweet_id)
    {
        return $this->findAllBy(array("tweet_id" => $tweet_id));
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
     * 設定に紐づいたツイートを取得する
     *
     * @return ツイート
     */
    public function tweet()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $group = $loader->loadModel("Tweet");
        $group->findByPrimaryKey($this->tweet_id);
        return $group;
    }
}
