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

            // ツイートを検索
            $twitter = $account->getTwitter();
            if (Vizualizer_Configure::get("accept_languages") === null) {
                Vizualizer_Configure::set("accept_languages", array("ja"));
            }
            $acceptLanguages = Vizualizer_Configure::get("accept_languages");
            $maxIds = array();
            foreach($acceptLanguages as $index => $acceptLanguage){
                $maxIds[$index] = 0;
            }
            $tweets = array();
            $tweetsTemp = $twitter->search_tweets(array("q" => $post["keyword"]." -RT ", "lang" => $acceptLanguages, "locale" => "ja", "count" => 100, "result_type" => "mixed"));
            $tweets = $tweetsTemp->statuses;

            $tweetData = array();
            foreach($tweets as $tweet){
                if(!isset($post["ignore_signature"]) || $post["ignore_signature"] != "1"){
                    $tweet->text .= " ".$tweet->user->screen_name;
                }
                if(!empty($tweet->id)){
                    $tweet->delete_target = "1";
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
        $tweetData["count"] = count($tweetData);
        return $tweetData;
    }
}
