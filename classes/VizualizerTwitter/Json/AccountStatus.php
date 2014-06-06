<?php

class VizualizerTwitter_Json_AccountStatus
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
                        if ($status->follow_status == "0") {
                            $status->follow_status = "1";
                        } else {
                            $status->follow_status = "0";
                        }
                        break;
                    case "tweet_status":
                        if ($status->tweet_status == "0") {
                            $status->tweet_status = "1";
                        } else {
                            $status->tweet_status = "0";
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

        $status = $account->status();

        return $status->toArray();
    }
}
