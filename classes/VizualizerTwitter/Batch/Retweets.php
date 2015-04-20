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
class VizualizerTwitter_Batch_Retweets extends Vizualizer_Plugin_Batch
{

    public function getDaemonName()
    {
        return "retweets";
    }

    public function getName()
    {
        return "Post Retweets";
    }

    public function getFlows()
    {
        return array("postRetweets");
    }

    /**
     * ツイートを投稿する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function postRetweets($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("Retweet");

        // 本体の処理を実行
        $retweets = $model->findAllBy(array("le:scheduled_retweet_time" => Vizualizer::now()->date("Y-m-d H:i:s"), "retweet_time" => "0000-00-00 00:00:00"), "scheduled_retweet_time", false);

        foreach ($retweets as $retweet) {
            if(Vizualizer::now()->getTime() < strtotime($retweet->scheduled_cancel_retweet_time)){
                // キャンセル済みになっているはずのRTはRT済み／キャンセル済み扱いにする。
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    $retweet->retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                    $retweet->cancel_retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                    $retweet->save();
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
                continue;
            }

            $account = $retweet->account();

            // リツイートを実施
            $twitter = $account->getTwitter();
            $result = $twitter->statuses_retweet_ID(array("id" => $retweet->tweet_id));
            if (isset($result->errors)) {
                if ($result->errors[0]->code == "327") {
                    // すでにRTされている場合はすべてのステータスをRT済みにする。
                    $connection = Vizualizer_Database_Factory::begin("twitter");
                    try {
                        $targets = $retweet->findAllBy(array("account_id" => $account->account_id, "tweet_id" => $reweet->tweet_id, "retweet_time" => "0000-00-00 00:00:00"));
                        foreach ($targets as $target){
                            $target->retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                            $target->save();
                        }
                        Vizualizer_Database_Factory::commit($connection);
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                }
                break;
                Vizualizer_Logger::writeError("Failed to Retweet on " . $retweet->tweet_id . " in " . $account->screen_name . " by " . print_r($result->errors, true));
            } elseif (!empty($result->id)) {
                // リツイートを更新
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    $retweet->retweet_tweet_id = $result->id_str;
                    $retweet->retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                    $retweet->save();

                    Vizualizer_Database_Factory::commit($connection);

                    Vizualizer_Logger::writeInfo("Retweeted for : " . $retweet->tweet_id . " in " . $account->screen_name . " with " . print_r($result, true));
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            } else {
                Vizualizer_Logger::writeInfo($account->screen_name . " : error in Post tweet : " . $tweetLog->tweet_text);
            }
        }

        // キャンセルの本体の処理を実行
        $retweets = $model->findAllBy(array("ne:retweet_tweet_id" => "", "le:scheduled_cancel_retweet_time" => Vizualizer::now()->date("Y-m-d H:i:s"), "cancel_retweet_time" => "0000-00-00 00:00:00"), "scheduled_retweet_time", false);
        foreach ($retweets as $retweet) {
            $account = $retweet->account();

            // リツイートを実施
            $twitter = $account->getTwitter();
            $result = $twitter->statuses_destroy_ID(array("id" => $retweet->retweet_tweet_id));
            if (isset($result->errors)) {
                if ($result->errors[0]->code == "144") {
                    // すでに削除されている場合はキャンセル済みフラグを立てる。
                    $connection = Vizualizer_Database_Factory::begin("twitter");
                    try {
                        $retweet->cancel_retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                        $retweet->save();
                        Vizualizer_Database_Factory::commit($connection);
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                }
                Vizualizer_Logger::writeError("Failed to cancel retweet on " . $retweet->retweet_tweet_id . " in " . $account->screen_name . " by " . print_r($result->errors, true));
            } elseif (!empty($result->id)) {
                // リツイートを更新
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    $retweet->cancel_retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                    $retweet->save();

                    Vizualizer_Database_Factory::commit($connection);

                    Vizualizer_Logger::writeInfo("Deleted Retweet for : " . $retweet->retweet_tweet_id . " with " . print_r($result, true));
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            } else {
                Vizualizer_Logger::writeInfo($account->screen_name . " : error in delete tweet : " . $tweetLog->tweet_text);
            }


        }

        return $data;
    }
}
