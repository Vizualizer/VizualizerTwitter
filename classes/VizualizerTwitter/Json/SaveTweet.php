<?php

class VizualizerTwitter_Json_SaveTweet
{

    public function execute()
    {
        $post = Vizualizer::request();

        // 商品プラグインの初期化
        $loader = new Vizualizer_Plugin("twitter");
        $result = array();

        if ($post["account_id"] > 0) {

            if (!empty($post["twitter_id"])) {
                // トランザクションの開始
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    $tweet = $loader->loadModel("Tweet");
                    $tweet->findBy(array("account_id" => $post["account_id"], "twitter_id" => $post["twitter_id"]));
                    if (!($tweet->tweet_id > 0)) {
                        $tweet = $loader->loadModel("Tweet");
                        $tweet->twitter_id = $post["twitter_id"];
                    }
                    $tweet->account_id = $post["account_id"];
                    $tweet->user_id = $post["user_id"];
                    $tweet->screen_name = $post["screen_name"];
                    $tweet->tweet_text = $post["tweet_text"];
                    if ($post["original_image_url"] != "") {
                        $parsedUrl = parse_url($post["original_image_url"]);
                        $info = pathinfo($parsedUrl["path"]);

                        $image = VIZUALIZER_SITE_ROOT . Vizualizer_Configure::get("twitter_image_savepath") . "/" . $info["basename"];
                        if (($fp = fopen($image, "w+")) !== FALSE) {
                            fwrite($fp, file_get_contents($post["original_image_url"]));
                            fclose($fp);
                            $tweet->media_url = $post["original_image_url"];
                            $tweet->media_filename = $info["basename"];
                        }
                    }
                    $tweet->retweet_count = $post["retweet_count"];
                    $tweet->favorite_count = $post["favorite_count"];
                    $tweet->save();

                    if ($post["execute"] > 0) {
                        $account = $tweet->account();
                        $tweetLog = $loader->loadModel("TweetLog");
                        $tweetLog->account_id = $tweet->account_id;
                        $tweetLog->screen_name = $tweet->account()->screen_name;
                        $tweetLog->tweet_time = Vizualizer::now()->date("Y-m-d H:i:s");
                        $tweetLog->tweet_text = $tweet->tweet_text;
                        $tweetLog->media_url = $tweet->media_url;
                        $tweetLog->media_filename = $tweet->media_filename;

                        if (!empty($tweetLog->media_filename)) {
                            $params = array("status" => trim(str_replace(" " . $tweetLog->media_url, "", $tweet->tweet_text)));
                            $params["media[]"] = VIZUALIZER_SITE_ROOT.Vizualizer_Configure::get("twitter_image_savepath")."/".$tweetLog->media_filename;
                            $tweeted = $tweet->account()->getTwitter()->statuses_updateWithMedia($params);
                        } else {
                            $tweeted = $tweet->account()->getTwitter()->statuses_update(array("status" => $tweetLog->tweet_text));
                        }
                        if (!empty($tweeted->id)) {
                            Vizualizer_Logger::writeInfo($account->screen_name . " : Post tweet(" . $tweeted->id . ") : " . $tweetLog->tweet_text);
                            $tweetLog->twitter_id = $tweeted->id;
                            $tweetlog->tweet_text = $tweeted->text;
                            if(property_exists($tweeted->entities, "media") && is_array($tweeted->entities->media) && count($tweeted->entities->media) > 0){
                                $media = $tweeted->entities->media[0];
                                $tweetLog->media_url = $media->url;
                                $tweetLog->media_link = $media->media_url;
                            }else{
                                $tweetLog->media_url = "";
                                $tweetLog->media_link = "";
                            }
                            $tweetLog->save();

                            $status = $account->status();
                            $tweetSettng = $account->tweetSetting();
                            if($status->retweet_status == "1" && $tweetSetting->retweet_group_id > 0){
                                // 強化アカウントで、対象グループが設定されている場合は、リツイートの登録も併せて行う。
                                $group = $loader->loadModel("AccountGroup");
                                $groups = $group->findAllBy(array("group_id" => $tweetSetting->retweet_group_id));
                                foreach($groups as $group){
                                    if($account->account_id != $group->account_id){
                                        $model = $loader->loadModel("Retweet");
                                        $model->account_id = $group->account_id;
                                        $model->tweet_id = $tweeted->id;
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

            $tweet = $loader->loadModel("Tweet");
            $tweets = $tweet->findAllBy(array("account_id" => $post["account_id"]));
            foreach($tweets as $tweet){
                $result[] = $tweet->twitter_id;
            }
        }
        return $result;
    }
}
