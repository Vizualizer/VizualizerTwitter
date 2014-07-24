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
 * リツイートを条件にあわせて作成する。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Module_Retweet_Create extends Vizualizer_Plugin_Module
{

    function execute($params)
    {
        $post = Vizualizer::request();
        $loader = new Vizualizer_Plugin("Twitter");

        // 該当のグループのアカウントを取得する。
        $model = $loader->loadModel("AccountGroup");
        $models = $model->findAllBy(array("group_id" => $post["target_group_id"]));
        $accountIds = array();
        foreach($models as $model){
            $accountIds[$model->account_id] = $model->account_id;
        }
        shuffle($accountIds);
        if($post["max_accounts"] > 0 && $post["max_accounts"] < count($accountIds)){
            $accountIds = array_slice($accountIds, 0, $post["max_accounts"]);
        }

        // URLから対象のツイートIDを取得する。
        if(preg_match("/^https?:\\/\\/twitter\\.com\\/([a-zA-Z0-9_]+)\\/status\\/([0-9]+)\\/?$/", $post["retweet_target"], $params) > 0){
            $screenName = $params[1];
            $tweetId = $params[2];
        }
        $tweet = $loader->loadModel("TweetLog");
        $tweet->findBy(array("twitter_id" => $tweetId));

        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");
        try {
            foreach($accountIds as $accountId){
                if($accountId != $tweet->account_id){
                    $model = $loader->loadModel("Retweet");
                    $model->account_id = $accountId;
                    $model->tweet_id = $tweetId;
                    if($post["retweet_delay"] > 0){
                        $model->scheduled_retweet_time = date("Y-m-d H:i:s", strtotime("+" . $post["retweet_delay"] . "minute"));
                    }else{
                        $model->scheduled_retweet_time = date("Y-m-d H:i:s");
                    }
                    if($post["retweet_duration"] > 0){
                        $model->scheduled_cancel_retweet_time = date("Y-m-d H:i:s", strtotime("+" . $post["retweet_duration"] . "minute"));
                    }
                    $model->save();
                }
            }

            // エラーが無かった場合、処理をコミットする。
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
    }
}
