<?php

class VizualizerTwitter_Json_Tweet
{
    public function execute()
    {
        $post = Vizualizer::request();

        // 商品プラグインの初期化
        $loader = new Vizualizer_Plugin("twitter");
        $account = $loader->loadModel("Account");

        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");
        try {
            if(!empty($post["commit"])){
                $tweetData = Vizualizer_Session::get(VizualizerTwitter_Json_SearchTweet::TWEET_SESSION_KEY);
                foreach($tweetData as $tweet){
                    $tweetDb = $loader->loadModel("Tweet");
                    $tweetDb->findBy(array("twitter_id" => $tweet->id));
                    if(!($tweetDb->tweet_id > 0)){
                        $tweetDb = $loader->loadModel("Tweet");
                        $tweetDb->twitter_id = $tweet->id;
                    }
                    $tweetDb->tweet_group_id = $post["group_id"];
                    $tweetDb->user_id = $tweet->user->id;
                    $tweetDb->screen_name = $tweet->user->screen_name;
                    $tweetDb->tweet_text = $tweet->text;
                    $tweetDb->retweet_count = $tweet->retweet_count;
                    $tweetDb->favorite_count = $tweet->favorite_count;
                    $tweetDb->save();
                }
                Vizualizer_Session::set(VizualizerTwitter_Json_SearchTweet::TWEET_SESSION_KEY, array());
                $post->remove("commit");
            }elseif(preg_match("/^delete_([0-9]+)$/", $post["mode"], $params) > 0){
                $tweetDb = $loader->loadModel("Tweet");
                $tweetDb->findByPrimaryKey($params[1]);
                if($tweetDb->tweet_id > 0){
                    $tweetDb->delete();
                }
                $post->remove("mode");
            }
            // エラーが無かった場合、処理をコミットする。
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }

        $tweetDb = $loader->loadModel("Tweet");
        $result = array();
        if($post["group_id"] > 0){
            $data = $tweetDb->findAllByGroupId($post["group_id"]);
            foreach($data as $item){
                $result[] = $item->toArray();
            }
        }
        return $result;
    }
}
