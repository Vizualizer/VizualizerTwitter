<?php

class VizualizerTwitter_Json_Account
{
    protected function getAccountInfo($accountId){
        $loader = new Vizualizer_Plugin("twitter");
        $account = $loader->loadModel("Account");
        $account->findByPrimaryKey($accountId);
        $account->friend_limit = $account->followLimit() - $account->friend_count;
        $account->setting = $account->setting()->toArray();
        $account->followSetting = $account->followSetting()->toArray();
        $status = $account->status()->toArray();
        if($account->application()->suspended != 0 && $status["account_status"] == VizualizerTwitter_Model_AccountStatus::ACCOUNT_OK){
            $status["account_status"] = VizualizerTwitter_Model_AccountStatus::APPLICATION_SUSPENDED;
        }
        $account->status = $status;
        $account->isUnfollowable = $account->isUnfollowable();
        $account->preTweetCount = $account->getPreTweetCount();
        $account->attributes = $account->attributes();
        $accountGroups = $account->accountGroups();
        $groups = array();
        foreach($accountGroups as $accountGroup){
            if($accountGroup->group_index > 0){
                for($i = 1; $i <= $accountGroup->group_index; $i ++){
                    if(!isset($groups[$i])){
                        $groups[$i] = 0;
                    }
                }
                $groups[$accountGroup->group_index] = $accountGroup->group_id;
            }else{
                $groups[] = $accountGroup->group_id;
            }
        }
        $account->groups = $groups;
        $accountOperators = $account->accountOperators();
        $operators = array();
        foreach($accountOperators as $accountOperator){
            if($accountOperator->operator_index > 0){
                for($i = 1; $i <= $accountOperator->operator_index; $i ++){
                    if(!isset($operators[$i])){
                        $operators[$i] = 0;
                    }
                }
                $operators[$accountOperator->operator_index] = $accountOperator->operator_id;
            }else{
                $operators[] = $accountOperator->operator_id;
            }
        }
        $account->operators = $operators;
        return $account;
    }

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
                        if ($account->follow_status == VizualizerTwitter_Model_AccountStatus::FOLLOW_SUSPENDED) {
                            $account->follow_status = VizualizerTwitter_Model_AccountStatus::FOLLOW_STANDBY;
                        } elseif ($account->follow_status == VizualizerTwitter_Model_AccountStatus::FOLLOW_STANDBY) {
                            $account->follow_status = VizualizerTwitter_Model_AccountStatus::FOLLOW_SUSPENDED;
                        }
                        break;
                    case "tweet_status":
                        if ($account->tweet_status == VizualizerTwitter_Model_AccountStatus::TWEET_SUSPENDED) {
                            $account->tweet_status = VizualizerTwitter_Model_AccountStatus::TWEET_RUNNING;
                        } elseif ($account->tweet_status == VizualizerTwitter_Model_AccountStatus::TWEET_RUNNING) {
                            $account->tweet_status = VizualizerTwitter_Model_AccountStatus::TWEET_SUSPENDED;
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

        $account = $this->getAccountInfo($post["account_id"]);
        return $account->toArray();
    }
}
