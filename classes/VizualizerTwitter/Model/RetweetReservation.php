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
 * リツイート予約のモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_RetweetReservation extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("RetweetReservations"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $retweet_id ツイートID
     */
    public function findByPrimaryKey($reservation_id)
    {
        $this->findBy(array("reservation_id" => $reservation_id));
    }

    /**
     * オペレータIDでデータを取得する。
     *
     * @param $account_id アカウントID
     * @return RT予約のリスト
     */
    public function findAllByOperatorId($operator_id, $sort = "", $reverse = false)
    {
        return $this->findAllBy(array("operator_id" => $operator_id), $sort, $reverse);
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
    public function retweets(){
        $loader = new Vizualizer_Plugin("twitter");
        $tweet = $loader->loadModel("Retweet");
        $tweet->findByReservationId($this->reservation_id);
        return $tweet;
    }
}
