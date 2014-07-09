<?php

class VizualizerTwitter_Json_AccountSetting extends VizualizerTwitter_Json_Account
{

    public function execute()
    {
        $post = Vizualizer::request();

        // 商品プラグインの初期化
        $loader = new Vizualizer_Plugin("twitter");
        $account = $loader->loadModel("Account");

        // ターゲットパラメータが指定されている場合は、データを書き換える
        if (!empty($post["target"])) {
            if (preg_match("/^([a-zA-Z0-9_]+)_([0-9]+)_([0-9]+)$/", $post["target"], $p) > 0) {
                $post->set("account_id", $p[3]);
                $post->set("setting_index", $p[2]);
                $account->findByPrimaryKey($post["account_id"]);
                $settings = $account->followSettings();

                foreach ($settings as $setting) {
                    if ($setting->setting_index == $post["setting_index"]) {
                        $attribute = $p[1];
                        $setting->$attribute = $post["value"];

                        // トランザクションの開始
                        $connection = Vizualizer_Database_Factory::begin("twitter");

                        try {
                            $setting->save();

                            // エラーが無かった場合、処理をコミットする。
                            Vizualizer_Database_Factory::commit($connection);
                        } catch (Exception $e) {
                            Vizualizer_Database_Factory::rollback($connection);
                            throw new Vizualizer_Exception_Database($e);
                        }
                    }
                }
            }
            $post->remove("target");
        }

        $account = $this->getAccountInfo($post["account_id"]);
        return $account->toArray();
    }
}
