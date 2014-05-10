<?php

class VizualizerTwitter_Json_TweetSetting
{

    public function execute()
    {
        $post = Vizualizer::request();

        // 商品プラグインの初期化
        $loader = new Vizualizer_Plugin("twitter");
        $tweetSetting = $loader->loadModel("TweetSetting");

        // ターゲットパラメータが指定されている場合は、データを書き換える
        if (!empty($post["target"])) {
            if (preg_match("/^([a-zA-Z0-9_]+)_([0-9]+)_([0-9]+)$/", $post["target"], $p) > 0) {
                $post->set("tweet_setting_id", $p[2]);
                $attribute = $p[1];
                $value = $p[3];
            }elseif (preg_match("/^([a-zA-Z0-9_]+)_([0-9]+)$/", $post["target"], $p) > 0) {
                $post->set("tweet_setting_id", $p[2]);
                $attribute = $p[1];
                $value = $post["value"];
            }

            $tweetSetting->findByPrimaryKey($post["tweet_setting_id"]);
            if($tweetSetting->tweet_setting_id > 0){
                // トランザクションの開始
                $connection = Vizualizer_Database_Factory::begin("twitter");

                try {
                    $tweetSetting->$attribute = $value;
                    $tweetSetting->save();

                    // エラーが無かった場合、処理をコミットする。
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            }
        }

        $tweetSetting->findByPrimaryKey($post["tweet_setting_id"]);
        return $tweetSetting->toArray();
    }
}
