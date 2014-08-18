<?php

class VizualizerTwitter_Json_AccountOperator extends VizualizerTwitter_Json_Account
{
    public function execute()
    {
        $post = Vizualizer::request();

        // 商品プラグインの初期化
        $loader = new Vizualizer_Plugin("twitter");
        $accountOperator = $loader->loadModel("AccountOperator");

        // ターゲットパラメータが指定されている場合は、データを書き換える
        if (!empty($post["target"])) {
            if (preg_match("/^([a-zA-Z0-9_]+)_([0-9]+)_([0-9]+)$/", $post["target"], $p) > 0) {
                $post->set("account_id", $p[2]);
                $post->set("old_value", $p[3]);
            }elseif(preg_match("/^([a-zA-Z0-9_]+)_([0-9]+)$/", $post["target"], $p) > 0){
                $post->set("account_id", $p[2]);
                $post->set("old_value", "0");
            }
            $post->remove("target");
        }

        // 設定するアカウントIDとグループIDが存在している場合には処理を実行
        if($post["account_id"] > 0 && $post["index"] > 0){
            $accountOperator->updateAccountOperator($post["account_id"], $post["index"], $post["value"]);
        }elseif($post["account_id"] > 0 && $post["old_value"] != $post["value"]){
            $accountOperator->changeAccountOperator($post["account_id"], $post["old_value"], $post["value"]);
        }

        $account = $this->getAccountInfo($post["account_id"]);
        return $account->toArray();
    }
}
