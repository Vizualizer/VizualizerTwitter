<?php

class VizualizerTwitter_Json_Accounts
{
    protected function getAccountInfos(){
        $loader = new Vizualizer_Plugin("twitter");
        $account = $loader->loadModel("Account");
        $data = $account->findAllBy();
        $accounts = array();
        foreach ($data as $account) {
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
            $accounts[] = $account->toArray();
        }
        return $accounts;
    }

    public function execute()
    {
        $accounts = $this->getAccountInfos();
        return $accounts;
    }
}
