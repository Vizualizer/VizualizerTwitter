<?php

class VizualizerTwitter_Json_OriginalTweet
{
    const DELETE_TARGET_KEY = "ORIGINAL_TWEET_DELETE_TARGET";

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
                $post["text"] = str_replace("\n", "\r\n", str_replace("\r", "\n", str_replace("\r\n", "\n", $post["text"])));
                if($post["commit"] == "2"){
                    $values = explode("\r\n", $post["text"]);
                }else{
                    $values = array($post["text"]);
                }
                foreach($values as $value){
                    $tweetDb = $loader->loadModel("Tweet");
                    $tweetDb->twitter_id = "0";
                    $tweetDb->tweet_group_id = $post["group_id"];
                    $tweetDb->user_id = "0";
                    $tweetDb->screen_name = "0";
                    $tweetDb->tweet_text = str_replace("\\n", "\r\n", $value);
                    $tweetDb->retweet_count = "0";
                    $tweetDb->favorite_count = "0";
                    $tweetDb->save();
                }
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
        if($post["group_id"] > 0){
            $data = $tweetDb->findAllBy(array("group_id" => $post["group_id"], "user_id" => "0"), retweet_count, true);
            foreach($data as $item){
                $item->delete_target = $deleteTarget[$item->tweet_id];
                $result[] = $item->toArray();
            }
        }
        return $result;
    }
}
