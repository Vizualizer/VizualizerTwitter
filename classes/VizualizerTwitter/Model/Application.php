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
 * アプリケーションのモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_Application extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("Applications"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $application_id アプリケーションID
     */
    public function findByPrimaryKey($application_id)
    {
        $this->findBy(array("application_id" => $application_id));
    }

    /**
     * 取得時点で最適なデータを取得する
     */
    public function findByPrefer()
    {
        $select = new Vizualizer_Query_Select($this->access);
        $select->addColumn($this->access->_W);
        $accounts = $loader->loadTable("Accounts");
        $select->joinLeft($accounts, array($this->access->account_id." = ".$accounts->account_id));
        $select->group($this->access->server_id)->order("COUNT(".$accounts->account_id.")")->order("RAND()");
        $select->setLimit(1, 0);
        $sqlResult = $select->fetch(1, 0);
        $thisClass = get_class($this);
        $result = new Vizualizer_Plugin_ModelIterator($thisClass, $sqlResult);
        if (($data = $result->current()) !== NULL) {
            $this->setValues($data->toArray());
            return true;
        }
        return false;
    }

    /**
     * アプリケーションに紐づいたアカウントを取得する
     *
     * @return アカウントリスト
     */
    public function accounts()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $account = $loader->loadModel("Account");
        return $account->findAllByApplicationId($this->application_id);
    }
}
