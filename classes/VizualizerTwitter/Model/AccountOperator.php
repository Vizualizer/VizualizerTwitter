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
 * アカウントオペレータのモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_AccountOperator extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("AccountOperators"), $values);
    }

    /**
     * アカウントIDとオペレータIDでデータを取得する。
     * @param int $account_id
     */
    public function findByAccountOperator($account_id, $operator_id){
        $this->findBy(array("account_id" => $account_id, "operator_id" => $operator_id));
    }

    /**
     * アカウントIDとグループIDでデータを取得する。
     * @param int $account_id
     */
    public function findByAccountIndex($account_id, $operator_index){
        $this->findBy(array("account_id" => $account_id, "operator_index" => $operator_index));
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $account_operator_id アカウントオペレータID
     */
    public function findByPrimaryKey($account_operator_id)
    {
        $this->findBy(array("account_operator_id" => $account_operator_id));
    }

    /**
     * アカウントIDでデータを取得する。
     * @param int $account_id
     */
    public function findAllByAccountId($account_id){
        return $this->findAllBy(array("account_id" => $account_id));
    }

    /**
     * オペレータIDでデータを取得する。
     * @param int $operator_id
     */
    public function findAllByOperatorId($operator_id){
        return $this->findAllBy(array("operator_id" => $operator_id));
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
     * 関連するオペレータを取得する。
     */
    public function operator(){
        $loader = new Vizualizer_Plugin("admin");
        $model = $loader->loadModel("Operator");
        $model->findByPrimaryKey($this->operator_id);
        return $model;
    }

    /**
     * アカウントオペレータのレコードを追加する。
     * @param int $account_id
     * @param int $operator_id
     * @throws Vizualizer_Exception_Database
     */
    public function addAccountOperator($account_id, $operator_id, $index = 0){
        $loader = new Vizualizer_Plugin("twitter");
        $model = $loader->loadModel("AccountOperator");
        $model->findBy(array("account_id" => $account_id, "operator_id" => $operator_id));
        if(!($model->account_operator_id > 0)){
            // トランザクションの開始
            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                $model->account_id = $account_id;
                $model->operator_id = $operator_id;
                $model->operator_index = $index;
                $model->save();
                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }
        }
    }

    /**
     * アカウントオペレータのレコードをする。
     * @param int $account_id
     * @param int $operator_id
     * @throws Vizualizer_Exception_Database
     */
    public function removeAccountOperator($account_id, $operator_id){
        $loader = new Vizualizer_Plugin("twitter");
        $model = $loader->loadModel("AccountOperator");
        $model->findBy(array("account_id" => $account_id, "operator_id" => $operator_id));
        if($model->account_operator_id > 0){
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
     * オペレータを置き換える
     * @param int $account_id
     * @param int $operator_id
     * @param int $new_operator_id
     */
    public function changeAccountOperator($account_id, $operator_id, $new_operator_id){
        // 設定する組み合わせが存在する場合には処理を行わない
        $loader = new Vizualizer_Plugin("twitter");
        $model = $loader->loadModel("AccountOperator");
        $model->findByAccountOperator($account_id, $new_operator_id);
        if($model->accoung_operator_id > 0){
            return false;
        }

        if($operator_id > 0 && $new_operator_id > 0){
            $model = $loader->loadModel("AccountOperator");
            $model->findBy(array("account_id" => $account_id, "operator_id" => $operator_id));
            if($model->account_operator_id > 0){
                // トランザクションの開始
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    $model->operator_id = $new_operator_id;
                    $model->save();
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            }
        }elseif($operator_id > 0){
            $this->removeAccountOperator($account_id, $operator_id);
        }elseif($new_operator_id > 0){
            $this->addAccountOperator($account_id, $new_operator_id);
        }
    }

    /**
     * オペレータを置き換える
     * @param int $account_id
     * @param int $operator_index
     * @param int $new_operator_id
     */
    public function updateAccountOperator($account_id, $operator_index, $new_operator_id){
        // 設定する組み合わせが存在する場合には処理を行わない
        $loader = new Vizualizer_Plugin("twitter");
        $model = $loader->loadModel("AccountOperator");
        $model->findByAccountOperator($account_id, $new_operator_id);
        if($model->account_operator_id > 0){
            return false;
        }

        // Operator Indexが指定された場合のみ処理を実行
        if($operator_index > 0){
            $model = $loader->loadModel("AccountOperator");
            $model->findBy(array("account_id" => $account_id, "operator_index" => $operator_index));

            if($model->account_operator_id > 0 && $new_operator_id > 0){
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    $model->operator_id = $new_operator_id;
                    $model->save();
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            }elseif($new_operator_id > 0){
                $this->addAccountOperator($account_id, $new_operator_id, $operator_index);
            }elseif($model->account_operator_id > 0){
                $this->removeAccountOperator($account_id, $model->operator_id);
            }
        }
    }
}
