<?php

class VizualizerTwitter_Json_SearchTweet
{
    const TWEET_SESSION_KEY = "SEARCHED_TWEETS";


    public function execute()
    {
        $post = Vizualizer::request();

        // 商品プラグインの初期化
        $loader = new Vizualizer_Plugin("twitter");
        $account = $loader->loadModel("Account");

        $tweetData = Vizualizer_Session::get(self::TWEET_SESSION_KEY);
        if(!is_array($tweetData)){
            $tweetData = array();
        }

        if(!empty($post["keyword"])){
            // アカウントを取得
            $account->findByPrimaryKey($post["account_id"]);

            // Twitterへのアクセスを初期化
            $application = $account->application();
            $twitterInfo = array("application_id" => $application->application_id, "api_key" => $application->api_key, "api_secret" => $application->api_secret);
            \Codebird\Codebird::setConsumerKey($twitterInfo["api_key"], $twitterInfo["api_secret"]);
            $twitter = \Codebird\Codebird::getInstance();
            $twitter->setToken($account->access_token, $account->access_token_secret);

            // ツイートを検索
            $tweetsTemp = $twitter->search_tweets(array("q" => $post["keyword"]." -RT ", "lang" => "ja", "locale" => "ja", "count" => 100, "result_type" => "mixed"));
            $tweets = $tweetsTemp->statuses;

            foreach($tweets as $tweet){
                if(!isset($post["ignore_signature"]) || $post["ignore_signature"] != "1"){
                    $tweet->text .= " ".$tweet->user->screen_name;
                }
                if(!empty($tweet->id) && $tweet->retweet_count > 0){
                    $tweetData[$tweet->id] = $tweet;
                }
            }
            $post->remove("keyword");
        }elseif(preg_match("/^delete_([0-9]+)$/", $post["mode"], $params) > 0){
            $tweetData[$params[1]]->delete_target = $post["value"];
            $post->remove($params[0]);
            $post->remove("mode");
            Vizualizer_Session::set(self::TWEET_SESSION_KEY, $tweetData);
            return $tweetData[$params[1]]->delete_target;
        }elseif($post["mode"] == "delete_all_target"){
            foreach($tweetData as $id => $tweet){
                if($tweet->delete_target == "1"){
                    unset($tweetData[$id]);
                }
            }
            $post->remove($params[0]);
            $post->remove("mode");
        }
        uasort($tweetData, function($a, $b){
            return ($a->retweet_count < $b->retweet_count);
        });
        Vizualizer_Session::set(self::TWEET_SESSION_KEY, $tweetData);
        return $tweetData;
    }
}
