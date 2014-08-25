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
 * アカウントグループのモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_AccountGroup extends Vizualizer_Plugin_Model
{
    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("AccountGroups"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $account_group_id グループID
     */
    public function findByPrimaryKey($account_group_id)
    {
        $this->findBy(array("account_group_id" => $account_group_id));
    }

    /**
     * アカウントIDとグループIDでデータを取得する。
     * @param int $account_id
     */
    public function findByAccountGroup($account_id, $group_id){
        $this->findBy(array("account_id" => $account_id, "group_id" => $group_id));
    }

    /**
     * アカウントIDとグループIDでデータを取得する。
     * @param int $account_id
     */
    public function findByAccountIndex($account_id, $group_index){
        $this->findBy(array("account_id" => $account_id, "group_index" => $group_index));
    }

    /**
     * アカウントIDでデータを取得する。
     * @param int $account_id
     */
    public function findAllByAccountId($account_id){
        return $this->findAllBy(array("account_id" => $account_id));
    }

    /**
     * グループIDでデータを取得する。
     * @param int $group_id
     */
    public function findAllByGroupId($group_id){
        return $this->findAllBy(array("group_id" => $group_id));
    }

    /**
     * 関連するアカウントを取得する。
     */
    public function account(){
        $loader = new Vizualizer_Plugin("twitter");
        $model = $loader->loadModel("Account");
        $model->findByPrimaryKey($this->account_id);
        return $model;
    }

    /**
     * 関連するグループを取得する。
     */
    public function group(){
        $cachedGroups = parent::cacheData("groups");
        if($cachedGroups === null){
            $loader = new Vizualizer_Plugin("twitter");
            $model = $loader->loadModel("Group");
            $groups = $model->findAllBy(array());
            $cachedGroups = array();
            foreach($groups as $group){
                $cachedGroups[$group->group_id] = $group;
            }
            $cachedGroups = parent::cacheData("groups", $cachedGroups);
        }
        return $cachedGroups[$this->group_id];
    }

    /**
     * アカウントグループのレコードを追加する。
     * @param int $account_id
     * @param int $group_id
     * @throws Vizualizer_Exception_Database
     */
    public function addAccountGroup($account_id, $group_id, $index = 0){
        $loader = new Vizualizer_Plugin("twitter");
        $model = $loader->loadModel("AccountGroup");
        $model->findBy(array("account_id" => $account_id, "group_id" => $group_id));
        if(!($model->account_group_id > 0)){
            // トランザクションの開始
            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                $model->account_id = $account_id;
                $model->group_id = $group_id;
                $model->group_index = $index;
                $model->save();
                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }
        }
    }

    /**
     * アカウントグループのレコードをする。
     * @param int $account_id
     * @param int $group_id
     * @throws Vizualizer_Exception_Database
     */
    public function removeAccountGroup($account_id, $group_id){
        $loader = new Vizualizer_Plugin("twitter");
        $model = $loader->loadModel("AccountGroup");
        $model->findBy(array("account_id" => $account_id, "group_id" => $group_id));
        if($model->account_group_id > 0){
            // トランザクションの開始
            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                $model->delete();
                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }
        }
    }

    /**
     * グループを置き換える
     * @param int $account_id
     * @param int $group_id
     * @param int $new_group_id
     */
    public function changeAccountGroup($account_id, $group_id, $new_group_id){
        // 設定する組み合わせが存在する場合には処理を行わない
        $loader = new Vizualizer_Plugin("twitter");
        $model = $loader->loadModel("AccountGroup");
        $model->findByAccountGroup($account_id, $new_group_id);
        if($model->accoung_group_id > 0){
            return false;
        }

        if($group_id > 0 && $new_group_id > 0){
            $model = $loader->loadModel("AccountGroup");
            $model->findBy(array("account_id" => $account_id, "group_id" => $group_id));
            if($model->account_group_id > 0){
                // トランザクションの開始
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    $model->group_id = $new_group_id;
                    $model->save();
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            }
        }elseif($group_id > 0){
            $this->removeAccountGroup($account_id, $group_id);
        }elseif($new_group_id > 0){
            $this->addAccountGroup($account_id, $new_group_id);
        }
    }

    /**
     * グループを置き換える
     * @param int $account_id
     * @param int $group_index
     * @param int $new_group_id
     */
    public function updateAccountGroup($account_id, $group_index, $new_group_id){
        // 設定する組み合わせが存在する場合には処理を行わない
        $loader = new Vizualizer_Plugin("twitter");
        $model = $loader->loadModel("AccountGroup");
        $model->findByAccountGroup($account_id, $new_group_id);
        if($model->accoung_group_id > 0){
            return false;
        }

        // Group Indexが指定された場合のみ処理を実行
        if($group_index > 0){
            $model = $loader->loadModel("AccountGroup");
            $model->findBy(array("account_id" => $account_id, "group_index" => $group_index));

            if($model->account_group_id > 0 && $new_group_id > 0){
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    $model->group_id = $new_group_id;
                    $model->save();
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            }elseif($new_group_id > 0){
                $this->addAccountGroup($account_id, $new_group_id, $group_index);
            }elseif($model->account_group_id > 0){
                $this->removeAccountGroup($account_id, $model->group_id);
            }
        }
    }
}
