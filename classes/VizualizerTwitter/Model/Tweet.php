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
 * ツイートのモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_Tweet extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("Tweets"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $tweet_id ツイートID
     */
    public function findByPrimaryKey($tweet_id)
    {
        $this->findBy(array("tweet_id" => $tweet_id));
    }

    /**
     * グループIDでデータを取得する。
     *
     * @param $group_id グループID
     * @return 設定のリスト
     */
    public function findAllByGroupId($group_id)
    {
        return $this->findAllBy(array("tweet_group_id" => $group_id));
    }


    /**
     * 設定に紐づいたツイートログを取得する
     *
     * @return ツイートログのリスト
     */
    public function tweetLogs()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $tweetLog = $loader->loadModel("TweetLog");
        return $tweetLog->findAllByTweetId($this->tweet_id);
    }

    /**
     * 設定に紐づいたグループを取得する
     *
     * @return グループ
     */
    public function group()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $group = $loader->loadModel("TweetGroup");
        $group->findByPrimaryKey($this->tweet_group_id);
        return $group;
    }
}
