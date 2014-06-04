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
 * アカウント情報の更新バッチです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Batch_Tweets extends Vizualizer_Plugin_Batch
{
    public function getDaemonName(){
        return "tweets";
    }

    public function getName()
    {
        return "Post Tweets";
    }

    public function getFlows()
    {
        return array("postTweets");
    }

    /**
     * ツイートを投稿する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function postTweets($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("TweetSetting");

        // 本体の処理を実行
        $tweetSettings = $model->findAllBy(array("le:next_tweet_time" => date("Y-m-d H:i:s")), "next_tweet_time", false);

        foreach ($tweetSettings as $tweetSetting) {
            $loader = new Vizualizer_Plugin("Twitter");
            $account = $tweetSetting->account();

            // 日中のみフラグの場合は夜間スキップ
            if($tweetSetting->daytime_flg == "1" && date("H") > 0 && date("H") < 7){
                continue;
            }

            // 指定曜日のフラグがある場合は指定曜日をスキップ
            if($tweetSetting->sunday_flg != "1" && date("w") == "0"){
                continue;
            }
            if($tweetSetting->monday_flg != "1" && date("w") == "1"){
                continue;
            }
            if($tweetSetting->tuesday_flg != "1" && date("w") == "2"){
                continue;
            }
            if($tweetSetting->wednesday_flg != "1" && date("w") == "3"){
                continue;
            }
            if($tweetSetting->thursday_flg != "1" && date("w") == "4"){
                continue;
            }
            if($tweetSetting->friday_flg != "1" && date("w") == "5"){
                continue;
            }
            if($tweetSetting->saturday_flg != "1" && date("w") == "6"){
                continue;
            }

            // アカウントのステータスが有効のアカウントのみを対象とする。
            if ($account->tweet_status != "1") {
                echo "Account is not ready.\r\n";
                continue;
            }

            $today = date("Y-m-d");

            // ログを取得する。
            $tweetLogs = $account->tweetLogs();
            $count = 0;
            $lastTweetId = 0;
            foreach($tweetLogs as $tweetLog){
                if($tweetLog->tweet_type != "2"){
                    if($lastTweetId == 0){
                        $lastTweetId = $tweetLog->tweet_id;
                    }
                    $count ++;
                }
                if($account->advertise_interval < $count){
                    break;
                }
            }
            // トランザクションの開始
            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                $temp = $account->tweetAdvertises("RAND()", true);
                $advertises = array();
                foreach($temp as $advertise){
                    if($advertise->advertise_type != "1" || !empty($advertise->fixed_advertise_url)){
                        $advertises[] = $advertise;
                    }
                }
                $tweetLog = $loader->loadModel("TweetLog");
                $tweetLog->account_id = $account->account_id;
                $tweetLog->tweet_time = date("Y-m-d H:i:s");

                if($account->advertise_interval > 0 && $account->advertise_interval < $count && count($advertises) > 0){
                    // 広告を取得し記事を作成
                    $advertise = $advertises[0];
                    $tweetLog->tweet_id = 0;
                    $tweetLog->tweet_type = 2;
                    $tweetLog->tweet_text = $advertise->advertise_text;
                    if(!empty($advertise->fixed_advertise_url)){
                        $tweetLog->tweet_text .= " ".$advertise->fixed_advertise_url;
                    }
                }else{
                    // ツイートを取得し、記事を作成
                    $tweets = $tweetSetting->group()->tweets("RAND()", true);
                    foreach($tweets as $tweet){
                        if($tweet->tweet_id != $lastTweetId){
                            $tweetLog->tweet_id = $tweet->tweet_id;
                            $tweetLog->tweet_type = 1;
                            $tweetLog->tweet_text = $tweet->tweet_text;
                            break;
                        }
                    }
                }

                if(!empty($tweetLog->tweet_text)){
                    $result = $account->getTwitter()->statuses_update(array("status" => $tweetLog->tweet_text));
                    echo "Post tweet : ".$tweetLog->tweet_text."\r\n";
                    print_r($result);
                    if(!empty($result->id)){
                        $tweetLog->twitter_id = $result->id;
                        $tweetLog->save();

                        $interval = $tweetSetting->tweet_interval;
                        if($tweetSetting->wavy_flg == "1"){
                            $interval += mt_rand(0, 30);
                        }

                        $tweetSetting->next_tweet_time = date("Y-m-d H:i:s", strtotime("+".$interval." minute"));
                        $tweetSetting->save();
                    }
                }

                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }
        }

        return $data;
    }
}
