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
 * リツイートのモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_Retweet extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("Retweets"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $retweet_id ツイートID
     */
    public function findByPrimaryKey($retweet_id)
    {
        $this->findBy(array("retweet_id" => $retweet_id));
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
     * リツイート予約IDでデータを取得する。
     *
     * @param $reservation_id リツイート予約ID
     * @return リツイートのリスト
     */
    public function findAllByReservationId($reservation_id, $sort = "", $reverse = false)
    {
        return $this->findAllBy(array("reservation_id" => $reservation_id), $sort, $reverse);
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
     * リツイートしたグループを取得する。
     */
    public function group(){
        $loader = new Vizualizer_Plugin("twitter");
        $group = $loader->loadModel("Group");
        $group->findByPrimaryKey($this->group_id);
        return $group;
    }

    /**
     * リツイートした元のツイートを取得する。
     */
    public function tweet(){
        $loader = new Vizualizer_Plugin("twitter");
        $tweet = $loader->loadModel("TweetLog");
        $tweet->findByTwitterId($this->tweet_id);
        return $tweet;
    }

    /**
     * リツイート予約を取得する。
     */
    public function reservation(){
        $loader = new Vizualizer_Plugin("twitter");
        $reservation = $loader->loadModel("RetweetReservation");
        $reservation->findByPrimaryKey($this->reservation_id);
        return $reservation;
    }
}
