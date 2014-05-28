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
 * フォロー詳細設定のモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_FollowSetting extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("FollowSettings"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $follow_setting_id フォロー詳細設定ID
     */
    public function findByPrimaryKey($follow_setting_id)
    {
        $this->findBy(array("follow_setting_id" => $follow_setting_id));
    }

    /**
     * アカウントIDでデータを取得する。
     *
     * @param $account_id アカウントID
     * @return 詳細設定のリスト
     */
    public function findAllByAccountId($account_id, $sort = "setting_index", $reverse = false)
    {
        return $this->findAllBy(array("account_id" => $account_id), $sort, $reverse);
    }

    /**
     * アカウントに適用される設定を取得する。
     *
     * @param $account_id アカウントID
     * @return 詳細設定のリスト
     */
    public function findByAccountFollowers($account_id, $follower_count)
    {
        $followSettings = $this->findAllBy(array("account_id" => $account_id, "le:min_followers" => $follower_count), "min_followers", true);
        $this->findByPrimaryKey($followSettings->current()->follow_setting_id);
    }

    /**
     * 詳細設定に紐づいたアカウントを取得する
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
}
