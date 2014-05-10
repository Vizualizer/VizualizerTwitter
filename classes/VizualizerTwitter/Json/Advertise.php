<?php

class VizualizerTwitter_Json_Advertise
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
            if(!empty($post["account_id"]) && !empty($post["text"])){
                $advertise = $loader->loadModel("TweetAdvertise");
                $advertise->account_id = $post["account_id"];
                $advertise->advertise_text = $post["text"];
                $advertise->save();
            }elseif(preg_match("/^delete_([0-9]+)$/", $post["mode"], $params) > 0){
                $advertise = $loader->loadModel("TweetAdvertise");
                $advertise->findByPrimaryKey($params[1]);
                if($advertise->tweet_advertise_id > 0){
                    $advertise->delete();
                }
            }
            // エラーが無かった場合、処理をコミットする。
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }

        $advertise = $loader->loadModel("TweetAdvertise");
        $result = array();
        if($post["account_id"] > 0){
            $data = $advertise->findAllByAccountId($post["account_id"]);
            foreach($data as $item){
                $result[] = $item->toArray();
            }
        }
        return $result;
    }
}
