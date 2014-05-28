<?php

class VizualizerTwitter_Json_AccountText
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
                switch ($p[1]) {
                    case "attribute_text":
                    case "attribute_select":
                        $attribute = "attribute";
                        $account->$attribute = $post["value"];
                        break;
                    default:
                        $attribute = $p[1];
                        $account->$attribute = $post["value"];
                        break;
                }
                // トランザクションの開始
                $connection = Vizualizer_Database_Factory::begin("twitter");

                try {
                    $account->save();

                    // エラーが無かった場合、処理をコミットする。
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            }
            $post->remove("target");
        }

        $accounts = $account->findAllBy(array());
        $attributes = array();
        foreach($accounts as $acc){
            if(!empty($acc->attribute)){
                $attributes[$acc->attribute] = $acc->attribute;
            }
        }

        $account->findByPrimaryKey($post["account_id"]);
        $account->attributes = $attributes;

        return $account->toArray();
    }
}
