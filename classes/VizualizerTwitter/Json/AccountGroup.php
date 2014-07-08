<?php

class VizualizerTwitter_Json_AccountGroup
{
    public function execute()
    {
        $post = Vizualizer::request();

        // 商品プラグインの初期化
        $loader = new Vizualizer_Plugin("twitter");
        $accountGroup = $loader->loadModel("AccountGroup");

        // ターゲットパラメータが指定されている場合は、データを書き換える
        if (!empty($post["target"])) {
            if (preg_match("/^([a-zA-Z0-9_]+)_([0-9]+)_([0-9]+)$/", $post["target"], $p) > 0) {
                $post->set("account_id", $p[2]);
                $post->set("old_group_id", $p[3]);
            }elseif(preg_match("/^([a-zA-Z0-9_]+)_([0-9]+)$/", $post["target"], $p) > 0){
                $post->set("account_id", $p[2]);
                $post->set("old_group_id", "0");
            }
            // 設定するアカウントIDとグループIDが存在している場合には処理を実行
            if($post["account_id"] > 0 && $post["old_group_id"] != $post["group_id"]){
                $accountGroup->changeAccountGroups($post["account_id"], $post["old_group_id"], $post["group_id"]);
            }
            $post->remove("target");
        }

        $account = $loader->loadModel("Account");
        $account->findByPrimaryKey($post["account_id"]);
        $account->friend_limit = $account->followLimit() - $account->friend_count;
        $account->setting = $account->setting()->toArray();
        $account->followSetting = $account->followSetting()->toArray();
        $account->status = $account->status()->toArray();
        $account->isUnfollowable = $account->isUnfollowable();

        return $account->toArray();
    }
}
