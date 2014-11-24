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
 * ツイートを即時実行する。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Module_Tweet_Execute extends Vizualizer_Plugin_Module_Detail
{

    function execute($params)
    {
        $post = Vizualizer::request();
        $attr = Vizualizer::attr();
        if (!empty($post["tweet"])) {
            $this->executeImpl("Twitter", "Tweet", $post["tweet_id"], $params->get("result", "tweet"));
            $tweet = $attr["tweet"];

            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                if (!empty($post["tweet_text"])) {
                    $loader = new Vizualizer_Plugin("Twitter");
                    $tweetLog = $loader->loadModel("TweetLog");
                    $tweetLog->account_id = $tweet->account_id;
                    $tweetLog->tweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                    $tweetLog->tweet_text = $tweet->tweet_text;
                    $tweetLog->media_url = $tweet->media_url;
                    $tweetLog->media_filename = $tweet->media_filename;

                    if (!empty($tweetLog->media_filename)) {
                        $params = array("status" => trim(str_replace(" " . $tweetLog->media_url, "", $tweet->tweet_text)));
                        $params["media[]"] = VIZUALIZER_SITE_ROOT.Vizualizer_Configure::get("twitter_image_savepath")."/".$tweetLog->media_filename;
                        $result = $tweet->account()->getTwitter()->statuses_updateWithMedia($params);
                    } else {
                        $result = $tweet->account()->getTwitter()->statuses_update(array("status" => $tweetLog->tweet_text));
                    }
                    if (!empty($result->id)) {
                        Vizualizer_Logger::writeInfo($account->screen_name . " : Post tweet(" . $result->id . ") : " . $tweetLog->tweet_text);
                        $tweetLog->twitter_id = $result->id;
                        $tweetlog->tweet_text = $result->text;
                        if(property_exists($result->entities, "media") && is_array($result->entities->media) && count($result->entities->media) > 0){
                            $media = $result->entities->media[0];
                            $tweetLog->media_url = $media->url;
                            $tweetLog->media_link = $media->media_url;
                        }else{
                            $tweetLog->media_url = "";
                            $tweetLog->media_link = "";
                        }
                        $tweetLog->save();

                        if($status->retweet_status == "1" && $tweetSetting->retweet_group_id > 0){
                            // 強化アカウントで、対象グループが設定されている場合は、リツイートの登録も併せて行う。
                            $group = $loader->loadModel("AccountGroup");
                            $groups = $group->findAllBy(array("group_id" => $tweetSetting->retweet_group_id));
                            foreach($groups as $group){
                                if($account->account_id != $group->account_id){
                                    $model = $loader->loadModel("Retweet");
                                    $model->account_id = $group->account_id;
                                    $model->tweet_id = $result->id;
                                    if($tweetSetting->retweet_delay > 0){
                                        $model->scheduled_retweet_time = Vizualizer::now()->strToTime("+" . $tweetSetting->retweet_delay . "minute")->date("Y-m-d H:i:s");
                                    }else{
                                        $model->scheduled_retweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                                    }
                                    if($tweetSetting->retweet_duration > 0){
                                        $model->scheduled_cancel_retweet_time = Vizualizer::now()->strToTime("+" . $tweetSetting->retweet_duration . "hour")->date("Y-m-d H:i:s");
                                    }
                                    $model->save();
                                }
                            }
                        }

                        $interval = $tweetSetting->tweet_interval;
                        if ($tweetSetting->wavy_flg == "1") {
                            $interval = mt_rand(0, 20) + $interval - 10;
                        }

                        Vizualizer_Logger::writeInfo($account->screen_name . " : Use interval : " . $interval);
                        $status->next_tweet_time = Vizualizer::now()->strToTime("+" . $interval . " minute")->date("Y-m-d H:i:s");
                        Vizualizer_Logger::writeInfo($account->screen_name . " : Next tweet at : " . $status->next_tweet_time);
                        $status->save();
                    } else {
                        Vizualizer_Logger::writeInfo($account->screen_name . " : error in Post tweet : " . $tweetLog->tweet_text);
                    }
                }

                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }
        }
    }
}
