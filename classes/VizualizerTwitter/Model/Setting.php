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
 * 共通設定のモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_Setting extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("Settings"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $setting_id 共通設定ID
     */
    public function findByPrimaryKey($setting_id)
    {
        $this->findBy(array("setting_id" => $setting_id));
    }

    /**
     * 管理オペレータIDとアカウントIDでデータを取得する。
     *
     * @param $operator_id 管理オペレータID
     * @param $account_id アカウントID
     */
    public function findByOperatorAccount($operator_id, $account_id = 0)
    {
        $this->findBy(array("operator_id" => $operator_id, "account_id" => $account_id));
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
}
