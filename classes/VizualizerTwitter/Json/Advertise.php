<?php

class VizualizerTwitter_Json_Advertise
{
    const DELETE_TARGET_KEY = "ADVERTISE_DELETE_TARGET";

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
            if(!empty($post["account_id"]) && !empty($post["text"])){
                $advertise = $loader->loadModel("TweetAdvertise");
                $advertise->account_id = $post["account_id"];
                $advertise->advertise_type = "0";
                $advertise->advertise_text = $post["text"];
                $advertise->save();
                $post->remove("text");
            }elseif(preg_match("/^delete_([0-9]+)$/", $post["mode"], $params) > 0){
                $deleteTarget[$params[1]] = $post["value"];
                $post->remove("mode");
                Vizualizer_Session::set(self::DELETE_TARGET_KEY, $deleteTarget);
                return $deleteTarget[$params[1]];
            }elseif($post["mode"] == "delete_all_target"){
                foreach($deleteTarget as $id => $value){
                    if($value == "1"){
                        $advertise = $loader->loadModel("TweetAdvertise");
                        $advertise->findByPrimaryKey($id);
                        if($advertise->advertise_id > 0){
                            $advertise->delete();
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
            $post->remove("mode");
        }

        $advertise = $loader->loadModel("TweetAdvertise");
        $result = array();
        if($post["account_id"] > 0){
            $count = $advertise->countBy(array("account_id" => $post["account_id"], "advertise_type" => "0"));
            if($post["page"] > 1){
                $advertise->limit(100, ($post["page"] - 1) * 100);
            }else{
                $advertise->limit(100, 0);
            }
            $data = $advertise->findAllByAccountType($post["account_id"], "0");
            foreach($data as $item){
                $item->delete_target = $deleteTarget[$item->advertise_id];
                $result[] = $item->toArray();
            }
        }
        $result["count"] = $count;
        return $result;
    }
}
