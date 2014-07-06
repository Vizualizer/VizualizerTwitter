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

    public function getDaemonName()
    {
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
        $model = $loader->loadModel("AccountStatus");

        // 本体の処理を実行
        $statuses = $model->findAllBy(array("le:next_tweet_time" => Vizualizer::now()->date("Y-m-d H:i:s")), "next_tweet_time", false);

        foreach ($statuses as $status) {
            $loader = new Vizualizer_Plugin("Twitter");
            $account = $status->account();
            $tweetSetting = $account->tweetSetting();

            // 日中のみフラグの場合は夜間スキップ
            if ($tweetSetting->daytime_flg == "1" && Vizualizer::now()->date("H") > 0 && Vizualizer::now()->date("H") < 7) {
                echo $account->screen_name . " : Skip tweet for daytime\r\n";
                continue;
            }

            // アカウントのステータスが有効のアカウントのみを対象とする。
            if (
                $account->status()->tweet_status != "1" && $account->status()->original_status != "1" && $account->status()->advertise_status != "1" && $account->status()->rakuten_status != "1") {
                echo $account->screen_name . " : Account BOT is not active.\r\n";
                continue;
            }

            $today = Vizualizer::now()->date("Y-m-d");

            // ログを取得する。
            $tweetLogs = $account->tweetLogs();
            $count = 0;
            $lastTweetId = 0;
            foreach ($tweetLogs as $tweetLog) {
                if ($tweetLog->tweet_type != "2") {
                    if ($lastTweetId == 0) {
                        $lastTweetId = $tweetLog->tweet_id;
                    }
                    $count ++;
                } else {
                    break;
                }
                if ($tweetSetting->advertise_interval < $count) {
                    break;
                }
            }
            // トランザクションの開始
            $tweetLog = $loader->loadModel("TweetLog");
            $tweetLog->account_id = $account->account_id;
            $tweetLog->tweet_time = Vizualizer::now()->date("Y-m-d H:i:s");

            $advertise = $account->tweetAdvertises()->findByPrefer();
            if ($tweetSetting->advertise_interval >= 0 && $tweetSetting->advertise_interval < $count && $advertise->advertise_id > 0) {
                echo $account->screen_name . " : use advertise because " . $tweetSetting->advertise_interval . " < " . $count . ".\r\n";
                // 広告を取得し記事を作成
                $tweetLog->tweet_id = 0;
                $tweetLog->tweet_type = 2;
                $tweetLog->tweet_text = $advertise->advertise_text;
                if (!empty($advertise->fixed_advertise_url)) {
                    $tweetLog->tweet_text .= " " . $advertise->fixed_advertise_url;
                }
                echo $account->screen_name . " : prepare to Tweet advertise text.\r\n";
            } else {
                // ツイートを取得し、記事を作成
                $tweet = $account->tweets()->findByPrefer();
                $tweetLog->tweet_id = $tweet->tweet_id;
                $tweetLog->tweet_type = 1;
                $tweetLog->tweet_text = $tweet->tweet_text;
                $tweetLog->media_url = $tweet->media_url;
                $tweetLog->media_filename = $tweet->media_filename;
                echo $account->screen_name . " : prepare to Tweet normal text.\r\n";
            }

            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                if (!empty($tweetLog->tweet_text)) {
                    if (!empty($tweetLog->media_filename)) {
                        $params = array("status" => str_replace(" " . $tweetLog->media_url, "", $tweetLog->tweet_text));
                        $params["media[]"] = VIZUALIZER_SITE_ROOT.Vizualizer_Configure::get("twitter_image_savepath")."/".$tweetLog->media_filename;
                        $result = $account->getTwitter()->statuses_updateWithMedia($params);
                    } else {
                        $result = $account->getTwitter()->statuses_update(array("status" => $tweetLog->tweet_text));
                    }
                    if (!empty($result->id)) {
                        echo $account->screen_name . " : Post tweet(" . $result->id . ") : " . $tweetLog->tweet_text . "\r\n";
                        $tweetLog->twitter_id = $result->id;
                        $tweetlog->tweet_text = $result->text;
                        $tweetLog->save();

                        $interval = $tweetSetting->tweet_interval;
                        if ($tweetSetting->wavy_flg == "1") {
                            $interval = mt_rand(0, $interval) + floor($interval / 2);
                        }

                        echo $account->screen_name . " : Use interval : " . $interval . "\r\n";
                        $status->next_tweet_time = Vizualizer::now()->strToTime("+" . $interval . " minute")->date("Y-m-d H:i:s");
                        echo $account->screen_name . " : Next tweet at : " . $status->next_tweet_time . "\r\n";
                        $status->save();
                    } else {
                        echo $account->screen_name . " : error in Post tweet : " . $tweetLog->tweet_text . "\r\n";
                        print_r($result);
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
