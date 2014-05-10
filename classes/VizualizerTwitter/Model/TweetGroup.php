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
 * ツイートグループのモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_TweetGroup extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("TweetGroups"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $tweet_group_id ツイートグループID
     */
    public function findByPrimaryKey($tweet_group_id)
    {
        $this->findBy(array("tweet_group_id" => $tweet_group_id));
    }

    /**
     * 管理オペレータIDでデータを取得する。
     *
     * @param $operator_id 管理オペレータID
     */
    public function findAllByOperatorId($operator_id)
    {
        return $this->findAllBy(array("operator_id" => $operator_id));
    }

    /**
     * グループに紐づいたツイートを取得する
     *
     * @return ツイートリスト
     */
    public function tweets()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $tweet = $loader->loadModel("Tweet");
        return $tweet->findAllByGroupId($this->tweet_group_id);
    }

    /**
     * グループに紐づいた設定リストを取得する
     *
     * @return 設定リスト
     */
    public function settings()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $tweet = $loader->loadModel("TweetSetting");
        return $tweet->findAllByGroupId($this->tweet_group_id);
    }
}
