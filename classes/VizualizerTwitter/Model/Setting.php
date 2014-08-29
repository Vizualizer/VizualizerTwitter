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
     * アカウントのキャッシュインスタンス
     */
    private $account;

    /**
     * アカウント共通の設定
     */
    private static $baseSetting;

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
    public function findByOperatorAccount($operator_id, $account_id = 0, $userDefault = false)
    {
        $loader = new Vizualizer_Plugin("twitter");

        // 検索して該当のアカウントの設定が無い場合はデフォルト値で作成
        $this->findBy(array("operator_id" => $operator_id, "account_id" => $account_id));
        if (!($this->setting_id > 0)) {
            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                if($userDefault){
                    $setting = $loader->loadModel("Setting");
                    $setting->findBy(array("operator_id" => $operator_id, "account_id" => "0"));
                    $arrSetting = $setting->toArray();
                    if($arrSetting["setting_id"] > 0){
                        foreach($arrSetting as $key => $value){
                            if($key != "setting_id"){
                                $this->$key = $value;
                            }
                        }
                    }
                }
                $this->operator_id = $operator_id;
                $this->account_id = $account_id;
                $this->save();
                Vizualizer_Database_Factory::commit($connection);
                $this->findBy(array("operator_id" => $operator_id, "account_id" => $account_id));
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
            }
        }
        if($account_id > 0){
            // アカウントIDが設定されている場合はデフォルトの設定を取得する。
            if(!self::$baseSetting){
                self::$baseSetting = $loader->loadModel("Setting");
                self::$baseSetting->findByOperatorAccount($operator_id);
            }
            $setting = self::$baseSetting;
            // 常にデフォルトの設定を利用する項目をコピー
            $defaultKeys = Vizualizer_Configure::get("twitter_default_setting_keys");
            if(is_array($defaultKeys)){
                foreach($defaultKeys as $key){
                    $this->$key = $setting->$key;
                }
            }
            if($this->use_follow_setting != "1"){
                // 個別の設定を利用しないとしている場合には、setting_id, operator_id, account_id, account_attribute以外を基本設定の数値で上書きする
                $keys = array_keys($setting->toArray());
                foreach($keys as $key){
                    if($key != "setting_id" && $key != "operator_id" && $key != "account_id" && $key != "account_attribute"){
                        $this->$key = $setting->$key;
                    }
                }
            }

            // アカウントIDが渡されている場合には、フォロワーの数に応じて利用する設定値を共通設定から取得する。
            $this->follow_ratio = $this->follow_ratio_1;
            $this->daily_follows = $this->daily_follows_1;
            $this->daily_unfollows = $this->daily_unfollows_1;
            $account = $this->account();
            for ($i = 2; $i < 10; $i ++) {
                $key = "follower_limit_" . $i;
                if ($this->$key > 0 && $this->$key <= $account->follower_count) {
                    $key = "follow_ratio_" . $i;
                    $this->follow_ratio = $this->$key;
                    $key = "daily_follows_" . $i;
                    $this->daily_follows = $this->$key;
                    $key = "daily_unfollows_" . $i;
                    $this->daily_unfollows = $this->$key;
                }
            }
        }
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
    public function account(VizualizerTwitter_Model_Account &$account = null)
    {
        if(!$this->account){
            // パラメータにアカウントが設定された場合は、そのアカウントをキャッシュに入れ、そのまま返す
            if($account !== null){
                return $this->account = $account;
            }
            $loader = new Vizualizer_Plugin("twitter");
            $this->account = $loader->loadModel("Account");
            $this->account->findByPrimaryKey($this->account_id);
        }
        return $this->account;
    }
}
