<?php

class VizualizerTwitter_Json_Tweet
{
    const DELETE_TARGET_KEY = "TWEET_DELETE_TARGET";

    public function execute()
    {
        $post = Vizualizer::request();

        // 商品プラグインの初期化
        $loader = new Vizualizer_Plugin("twitter");
        $account = $loader->loadModel("Account");

        $deleteTarget = Vizualizer_Session::get(self::DELETE_TARGET_KEY);
        if(!is_array($deleteTarget)){
            $deleteTarget = array();
        }

        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");
        try {
            if(!empty($post["commit"])){
                $tweetData = Vizualizer_Session::get(VizualizerTwitter_Json_SearchTweet::TWEET_SESSION_KEY);
                foreach($tweetData as $id => $tweet){
                    if($tweet->delete_target == "1"){
                        $tweetDb = $loader->loadModel("Tweet");
                        $tweetDb->findBy(array("twitter_id" => $tweet->id));
                        if(!($tweetDb->tweet_id > 0)){
                            $tweetDb = $loader->loadModel("Tweet");
                            $tweetDb->twitter_id = $tweet->id;
                        }
                        $tweetDb->account_id = $post["account_id"];
                        $tweetDb->user_id = $tweet->user->id;
                        $tweetDb->screen_name = $tweet->user->screen_name;
                        $tweetDb->tweet_text = $tweet->text;
                        $tweetDb->retweet_count = $tweet->retweet_count;
                        $tweetDb->favorite_count = $tweet->favorite_count;
                        $tweetDb->save();
                        unset($tweetData[$id]);
                    }
                }
                Vizualizer_Session::set(VizualizerTwitter_Json_SearchTweet::TWEET_SESSION_KEY, $tweetData);
                $post->remove("commit");
            }elseif(preg_match("/^delete_([0-9]+)$/", $post["mode"], $params) > 0){
                $deleteTarget[$params[1]] = $post["value"];
                $post->remove("mode");
                Vizualizer_Session::set(self::DELETE_TARGET_KEY, $deleteTarget);
                return $deleteTarget[$params[1]];
            }elseif($post["mode"] == "delete_all_target"){
                foreach($deleteTarget as $id => $value){
                    if($value == "1"){
                        $tweetDb = $loader->loadModel("Tweet");
                        $tweetDb->findByPrimaryKey($id);
                        if($tweetDb->tweet_id > 0){
                            $tweetDb->delete();
                        }
                    }
                }
                $post->remove("mode");
                Vizualizer_Session::remove(self::DELETE_TARGET_KEY);
            }
            // エラーが無かった場合、処理をコミットする。
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }

        $tweetDb = $loader->loadModel("Tweet");
        $result = array();
        if($post["account_id"] > 0){
            $data = $tweetDb->findAllBy(array("account_id" => $post["account_id"], "ne:user_id" => "0"), "retweet_count", true);
            foreach($data as $item){
                $item->delete_target = $deleteTarget[$item->tweet_id];
                $result[] = $item->toArray();
            }
        }
        return $result;
    }
}
