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
    public function findByOperatorAccount($operator_id, $account_id = 0)
    {
        $loader = new Vizualizer_Plugin("twitter");

        // アカウントIDが指定されている場合は、個別設定を取得する。
        if($account_id > 0){
            $this->findBy(array("operator_id" => $operator_id, "account_id" => $account_id));
            if (!($this->setting_id > 0)) {
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    $this->operator_id = $operator_id;
                    $this->account_id = $account_id;
                    $this->save();
                    Vizualizer_Database_Factory::commit($connection);
                    $this->findBy(array("operator_id" => $operator_id, "account_id" => $account_id));
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                }
            }
        }

        // アカウントIDが設定されている場合はデフォルトの設定を取得する。
        if(!self::$baseSetting){
            self::$baseSetting = array();
        }
        if(!array_key_exists($operator_id, self::$baseSetting)){
            self::$baseSetting[$operator_id] = $loader->loadModel("Setting");
            self::$baseSetting[$operator_id]->findByOperatorAccount($operator_id);
        }
        $setting = self::$baseSetting[$operator_id];

        // 未設定の個別項目は共通設定の項目で上書きする。
        foreach ($setting->toArray() as $key => $value) {
            if (empty($this->key)) {
                $this->key = $value;
            }
        }

        // 常にデフォルトの設定を利用する項目をコピー
        $defaultKeys = Vizualizer_Configure::get("twitter_default_setting_keys");
        if(is_array($defaultKeys)){
            foreach($defaultKeys as $key){
                $this->$key = $setting->$key;
            }
        }
        if($this->use_follow_setting != "1"){
            // 個別の設定を利用しないとしている場合には、特定のキーを基本設定の数値で上書きする
            $keys = array_keys($setting->toArray());
            foreach($keys as $key){
                switch($key){
                    case "follow_type":
                    case "follow_keywords":
                    case "follower_keywords":
                    case "follow_interval":
                    case "unfollow_interval":
                    case "refollow_timeout":
                    case "follow_unit":
                    case "follow_unit_interval":
                    case "unfollow_unit_interval":
                    case "japanese_flg":
                    case "unlock_user_flg":
                    case "unique_icon_flg":
                    case "non_bot_flg":
                        $this->$key = $setting->$key;
                        break;
                    default:
                    break;
                }
                if(preg_match("/^(.+)_[0-9]$/", $key, $vals) > 0){
                    switch($vals[1]){
                        case "follower_limit":
                        case "follow_ratio":
                        case "daily_follows":
                        case "daily_unfollows":
                            $this->$key = $setting->$key;
                            break;
                        default:
                        break;
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
