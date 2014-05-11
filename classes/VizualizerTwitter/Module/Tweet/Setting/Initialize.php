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
 * ツイート設定を初期化する。（アカウントあたり1設定／1グループで管理するためのもの）
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Module_Tweet_Setting_Initialize extends Vizualizer_Plugin_Module
{

    function execute($params)
    {
        $post = Vizualizer::request();
        $loader = new Vizualizer_Plugin("twitter");
        $account = $loader->loadModel("Account");
        $account->findByPrimaryKey($post["account_id"]);

        if ($account->account_id > 0) {
            // アカウントが存在している場合にはデータを初期化
            $settings = $account->tweetSettings();
            if ($settings->count() == 0) {
                // トランザクションの開始
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    // 設定に使うグループを作成
                    $group = $loader->loadModel("TweetGroup");
                    if (class_exists("VizualizerAdmin")) {
                        $operator = Vizualizer_Session::get(VizualizerAdmin::SESSION_KEY);
                        $group->operator_id = $operator["operator_id"];
                    }
                    $group->tweet_group_name = "デフォルト";
                    $group->save();

                    $setting = $loader->loadModel("TweetSetting");
                    $setting->account_id = $account->account_id;
                    $setting->tweet_group_id = $group->tweet_group_id;
                    $setting->tweet_interval = 30;
                    $setting->daytime_flg = 0;
                    $setting->monday_flg = 1;
                    $setting->tuesday_flg = 1;
                    $setting->wednesday_flg = 1;
                    $setting->thursday_flg = 1;
                    $setting->friday_flg = 1;
                    $setting->saturday_flg = 1;
                    $setting->sunday_flg = 1;
                    $setting->wavy_flg = 1;
                    $setting->save();

                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            }else{
                $setting = $settings->current();
            }
            $post->set("tweet_setting_id", $setting->tweet_setting_id);
        }
    }
}
