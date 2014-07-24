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
 * ツイート情報の更新バッチです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Batch_UpdateTweets extends Vizualizer_Plugin_Batch
{
    public function getDaemonName()
    {
        return "update_tweets";
    }

    public function getDaemonInterval()
    {
        return 900;
    }

    public function getName(){
        return "Twitter Tweet Updater";
    }

    public function getFlows(){
        return array("updateTweets");
    }

    /**
     * アカウント情報更新する。
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function updateTweets($params, $data){
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("Account");
        $accounts = $model->findAllBy(array());

        foreach($accounts as $account){
            // Twitterへのアクセスを初期化
            $twitter = $account->getTwitter();

            // ユーザー情報を取得
            $tweets = $twitter->statuses_userTimeline(array("user_id" => $account->twitter_id, "trim_user" => true, "include_rts" => false));

            foreach ($tweets as $tweet) {
                if(!empty($tweet->text)){
                    // TwitterのIDが取得できた場合のみ更新
                    $connection = Vizualizer_Database_Factory::begin("twitter");

                    try {
                        $model = $loader->loadModel("TweetLog");
                        $model->findBy(array("account_id" => $account->account_id, "twitter_id" => $tweet->id_str));
                        if(!($model->tweet_log_id > 0)){
                            $model->account_id = $account->account_id;
                            $model->twitter_id = $tweet->id_str;
                            $model->tweet_time = date("Y-m-d H:i:s", strtotime($tweet->created_at));
                            $model->tweet_type = 3;
                            if(is_array($tweet->entities->media)){
                                foreach($tweet->entities->media as $media){
                                    if($media->type == "photo"){
                                        $model->media_link = $media->media_url;
                                        $model->media_url = $media->url;
                                        $tweet->text = str_replace($media->url, "", $tweet->text);
                                        break;
                                    }
                                }
                            }
                            $model->tweet_text = $tweet->text;
                        }
                        $model->retweet_count = $tweet->retweet_count;
                        $model->favorite_count = $tweet->favorite_count;
                        echo "Update Tweet for ".$model->twitter_id."  : \r\n";
                        $model->save();

                        // エラーが無かった場合、処理をコミットする。
                        Vizualizer_Database_Factory::commit($connection);
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                }
            }
        }
        return $data;
    }
}
