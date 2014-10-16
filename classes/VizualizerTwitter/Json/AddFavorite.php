<?php

class VizualizerTwitter_Json_AddFavorite
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
                    $tweet = $loader->loadModel("TweetFavorite");
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
                    // エラーが無かった場合、処理をコミットする。
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
