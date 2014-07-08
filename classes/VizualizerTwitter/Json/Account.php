<?php

class VizualizerTwitter_Json_Account
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
                    case "follow_mode":
                        if ($account->follower_count < 2000) {
                            return array("error" => "フォロワーが2000人未満の場合はモードを変更できません");
                        }
                        if ($account->follow_mode == "1") {
                            $account->follow_mode = "2";
                        } else {
                            $account->follow_mode = "1";
                        }
                        break;
                    case "follow_status":
                        if ($account->follow_status == "0") {
                            $account->follow_status = "1";
                        } elseif ($account->follow_status == "1") {
                            $account->follow_status = "0";
                        }
                        break;
                    case "tweet_status":
                        if ($account->tweet_status == "0") {
                            $account->tweet_status = "1";
                        } elseif ($account->tweet_status == "1") {
                            $account->tweet_status = "0";
                        }
                        break;
                    case "follow_keyword":
                        if (!empty($post["value"])) {
                            $keywords = $account->followKeywords();
                            $values = explode(" ", str_replace("　", " ", trim($post["value"])));
                            foreach ($values as $value) {
                                $keywords[] = $value;
                            }
                            $account->follow_keywords = implode("\r\n", $keywords);
                        }
                        break;
                    case "ignore_keyword":
                        if (!empty($post["value"])) {
                            $keywords = $account->ignoreKeywords();
                            $values = explode(" ", str_replace("　", " ", trim($post["value"])));
                            foreach ($values as $value) {
                                $keywords[] = $value;
                            }
                            $account->ignore_keywords = implode("\r\n", $keywords);
                        }
                        break;
                    case "follow_keyword_delete":
                        if (!empty($post["value"])) {
                            $keywords = $account->followKeywords();
                            $newKeywords = array();
                            foreach ($keywords as $keyword) {
                                if ($keyword != $post["value"]) {
                                    $newKeywords[] = $keyword;
                                }
                            }
                            $account->follow_keywords = implode("\r\n", $newKeywords);
                        }
                        break;
                    case "ignore_keyword_delete":
                        if (!empty($post["value"])) {
                            $keywords = $account->ignoreKeywords();
                            $newKeywords = array();
                            foreach ($keywords as $keyword) {
                                if ($keyword != $post["value"]) {
                                    $newKeywords[] = $keyword;
                                }
                            }
                            $account->ignore_keywords = implode("\r\n", $newKeywords);
                        }
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

        $account->findByPrimaryKey($post["account_id"]);
        $account->friend_limit = $account->followLimit() - $account->friend_count;
        $account->setting = $account->setting()->toArray();
        $account->followSetting = $account->followSetting()->toArray();
        $account->status = $account->status()->toArray();
        $account->isUnfollowable = $account->isUnfollowable();
        $accountGroups = $account->accountGroups();
        $account->groups = array();
        foreach($accountGroups as $accountGroup){
            $account->groups[] = $accountGroup->toArray();
        }
        $accountGroups = $account->accountGroups();
        $account->groups = array();
        foreach($accountGroups as $accountGroup){
            $account->groups[] = $accountGroup->toArray();
        }

        return $account->toArray();
    }
}
