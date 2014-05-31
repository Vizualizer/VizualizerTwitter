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
 * 広告設定のデータを取得する。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class Vizualizertwitter_Module_Advertise_RakutenList extends Vizualizer_Plugin_Module
{

    function execute($params)
    {
        $post = Vizualizer::request();
        $loader = new Vizualizer_Plugin("Twitter");

        if (!empty($post["id"]) && !empty($post["url"])) {
            // トランザクションの開始
            $connection = Vizualizer_Database_Factory::begin("twitter");

            try {
                $advertise = $loader->loadModel("TweetAdvertise");
                $advertise->findByPrimaryKey($post["id"]);
                if($advertise->advertise_id > 0){
                    $advertise->fixed_advertise_url = $post["url"];
                    $advertise->save();
                }

                // エラーが無かった場合、処理をコミットする。
                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }
        }

        // 楽天のアプリIDとアフィリIDからアカウントを特定
        $setting = $loader->loadModel("Setting");
        $setting->findBy(array("rakuten_app_id" => $post["app"], "rakuten_aff_id" => $post["aff"]));

        // オペレータIDからアカウントリストを取得
        $account = $loader->loadModel("Account");
        $accounts = $account->findAllBy(array("operator_id" => $setting->operator_id));
        $accountIds = array();
        foreach ($accounts as $account) {
            $accountIds[] = $account->account_id;
        }

        // アカウントIDのリストから楽天の広告を取得
        $advertise = $loader->loadModel("TweetAdvertise");
        $advertises = $advertise->findAllBy(array("in:account_id" => $accountIds, "advertise_type" => "1"));

        $result = array();
        foreach ($advertises as $advertise) {
            if (empty($advertise->fixed_advertise_url) || strtotime($advertise->update_time) < time("-3 day")) {
                $result[] = $advertise->advertise_id."@".$advertise->advertise_url;
            }
        }

        $attr = Vizualizer::attr();
        $attr[$params->get("result", "urls")] = $result;
    }
}
