<?php

class VizualizerTwitter_Json_AccountStatus extends VizualizerTwitter_Json_Account
{

    public function execute()
    {
        $post = Vizualizer::request();

        // 商品プラグインの初期化
        $loader = new Vizualizer_Plugin("twitter");
        $account = $loader->loadModel("Account");

        // ターゲットパラメータが指定されている場合は、データを書き換える
        if (!empty($post["target"])) {
            if (preg_match("/^([a-zA-Z0-9_]+)_([0-9]+)$/", $post["target"], $p) > 0) {
                $post->set("account_id", $p[2]);
                $account->findByPrimaryKey($post["account_id"]);
                $status = $account->status();
                switch ($p[1]) {
                    case "follow_status":
                    case "tweet_status":
                    case "original_status":
                    case "advertise_status":
                    case "rakuten_status":
                    case "retweet_status":
                        $attrName = $p[1];
                        if(!isset($post["value"]) || !is_numeric($post["value"])){
                            if ($status->$attrName == "0") {
                                $status->$attrName = "1";
                            } else {
                                $status->$attrName = "0";
                            }
                        }else{
                            $status->$attrName = $post["value"];
                        }
                        break;
                    default:
                        $attribute = $p[1];
                        $status->$attribute = $post["value"];
                        break;
                }
                // トランザクションの開始
                $connection = Vizualizer_Database_Factory::begin("twitter");

                try {
                    $status->save();

                    // エラーが無かった場合、処理をコミットする。
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            }
            $post->remove("target");
        }

        $account = $this->getAccountInfo($post["account_id"]);
        return $account->toArray();
    }
}
