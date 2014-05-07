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
 * アカウントのモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_Account extends Vizualizer_Plugin_Model
{
    const FOLLOW_MODE_NORMAL = "2";
    const FOLLOW_MODE_SAFE = "1";


    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("Accounts"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $account_id アカウントID
     */
    public function findByPrimaryKey($account_id)
    {
        $this->findBy(array("account_id" => $account_id));
    }

    /**
     * グループIDでデータを取得する。
     *
     * @param $group_id グループID
     * @return アカウントのリスト
     */
    public function findAllByGroupId($group_id)
    {
        return $this->findAllBy(array("group_id" => $group_id));
    }

    /**
     * アプリケーションIDでデータを取得する。
     *
     * @param $application_id アプリケーションID
     * @return アカウントのリスト
     */
    public function findAllByApplicationId($application_id)
    {
        return $this->findAllBy(array("application_id" => $application_id));
    }

    /**
     * サーバーIDでデータを取得する。
     *
     * @param $server_id サーバーID
     * @return アカウントのリスト
     */
    public function findAllByServerId($server_id)
    {
        return $this->findAllBy(array("server_id" => $server_id));
    }

    /**
     * フォローキーワードのリストを取得する。
     *
     * @return フォローキーワード
     */
    public function followKeywords()
    {
        $data = explode("\r\n", $this->follow_keywords);
        $result = array();
        foreach($data as $item){
            if(!empty($item)){
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * 拒否キーワードのリストを取得する。
     *
     * @return 拒否キーワード
     */
    public function ignoreKeywords()
    {
        $data = explode("\r\n", $this->ignore_keywords);
        $result = array();
        foreach($data as $item){
            if(!empty($item)){
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * アカウントに紐づいたグループを取得する
     *
     * @return グループ
     */
    public function group()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $group = $loader->loadModel("Group");
        $group->findByPrimaryKey($this->group_id);
        return $group;
    }

    /**
     * アカウントに紐づいたアプリケーションを取得する
     *
     * @return アプリケーション
     */
    public function application()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $application = $loader->loadModel("Application");
        $application->findByPrimaryKey($this->application_id);
        return $application;
    }

    /**
     * アカウントに紐づいたサーバーを取得する
     *
     * @return サーバー
     */
    public function server()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $server = $loader->loadModel("Server");
        $server->findByPrimaryKey($this->server_id);
        return $server;
    }

    /**
     * アカウントに紐づいたフォロー詳細設定を取得する
     *
     * @return 詳細設定のリスト
     */
    public function followSetting()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $followSetting = $loader->loadModel("FollowSetting");
        $followSetting->findByAccountFollowers($this->account_id, $this->follower_count);
        return $followSetting;
    }

    /**
     * アカウントに紐づいたフォロー詳細設定を取得する
     *
     * @return 詳細設定のリスト
     */
    public function followSettings($sort = "setting_index", $reverse = false)
    {
        $loader = new Vizualizer_Plugin("twitter");
        $followSetting = $loader->loadModel("FollowSetting");
        $followSettings = $followSetting->findAllByAccountId($this->account_id, $sort, $reverse);
        return $followSettings;
    }

    /**
     * アカウントに紐づいたフォローを取得する
     *
     * @return フォローのリスト
     */
    public function follows()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $follow = $loader->loadModel("Follow");
        return $follow->findAllByAccountId($this->account_id);
    }

    /**
     * アカウントに紐づいたフォローを取得する
     *
     * @return フォローのリスト
     */
    public function followHistorys($days = 0)
    {
        $loader = new Vizualizer_Plugin("twitter");
        $follow = $loader->loadModel("FollowHistory");
        return $follow->findAllByAccountId($this->account_id, $days);
    }
}
