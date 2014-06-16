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
                        $tweetDb->findBy(array("account_id" => $post["account_id"], "twitter_id" => $tweet->id));
                        if(!($tweetDb->tweet_id > 0)){
                            $tweetDb = $loader->loadModel("Tweet");
                            $tweetDb->twitter_id = $tweet->id_str;
                        }
                        $tweetDb->account_id = $post["account_id"];
                        $tweetDb->user_id = $tweet->user->id;
                        $tweetDb->screen_name = $tweet->user->screen_name;
                        $tweetDb->tweet_text = $tweet->text;
                        if(count($tweet->entities->media) > 0){
                            $media = $tweet->entities->media[0];
                            $parsedUrl = parse_url($media->media_url);
                            $info = pathinfo($parsedUrl["path"]);

                            $image = VIZUALIZER_SITE_ROOT.Vizualizer_Configure::get("twitter_image_savepath")."/".$info["basename"];
                            if(($fp = fopen($image, "w+")) !== FALSE){
                                fwrite($fp, file_get_contents($media->media_url));
                                fclose($fp);
                                $tweetDb->media_url = $media->url;
                                $tweetDb->media_filename = $info["basename"];
                            }
                        }
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
            $count = $tweetDb->countBy(array("account_id" => $post["account_id"], "ne:user_id" => "0"));
            if($post["page"] > 1){
                $tweetDb->limit(100, ($post["page"] - 1) * 100);
            }else{
                $tweetDb->limit(100, 0);
            }
            if($post["sort"] == "retweet"){
                $data = $tweetDb->findAllBy(array("account_id" => $post["account_id"], "ne:user_id" => "0"), "retweet_count", true);
            }else{
                $data = $tweetDb->findAllBy(array("account_id" => $post["account_id"], "ne:user_id" => "0"), "create_time", false);
            }
            foreach($data as $item){
                $item->delete_target = $deleteTarget[$item->tweet_id];
                $result[] = $item->toArray();
            }
        }
        $result["count"] = $count;
        return $result;
    }
}
