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
 * フォロー履歴のモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_FollowHistory extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("FollowHistorys"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $follow_id フォローID
     */
    public function findByPrimaryKey($follow_history_id)
    {
        $this->findBy(array("follow_history_id" => $follow_history_id));
    }

    /**
     * アカウントIDでデータを取得する。
     *
     * @param $account_id アカウントID
     * @return フォローのリスト
     */
    public function findAllByAccountId($account_id, $days = 0, $sort = "history_date", $reverse = true)
    {
        if($days == 0){
            return $this->findAllBy(array("account_id" => $account_id), $sort, $reverse);
        }else{
            $result = $this->findAllBy(array("account_id" => $account_id, "gt:history_date" => date("Y-m-d", strtotime("-".$days." day"))), $sort, $reverse);
            $data = array();
            for($i = 0; $i < $days; $i ++){
                $data[date("Ymd", strtotime("-".$i." day"))] = (object) array("target_count" => 0, "follow_count" => 0, "followed_count" => 0, "unfollow_count" => 0);
            }
            foreach($result as $item){
                if(isset($data[date("Ymd", strtotime($item->history_date))])){
                    $data[date("Ymd", strtotime($item->history_date))] = $item;
                }
            }
            return array_values($data);
        }
    }
}
