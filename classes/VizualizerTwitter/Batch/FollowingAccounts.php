<?php

/**
 * Copyright (C) 2012 Vizualizer All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Naohisa Minagawa <info@vizualizer.jp>
 * @copyright Copyright (c) 2010, Vizualizer
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 * @since PHP 5.3
 * @version   1.0.0
 */

/**
 * フォロワー情報の更新バッチです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Batch_FollowingAccounts extends Vizualizer_Plugin_Batch
{
    public function getDaemonName()
    {
        return "following_accounts";
    }

    public function getDaemonInterval()
    {
        return 3600;
    }

    public function getName()
    {
        return "Following Account Update";
    }

    public function getFlows()
    {
        return array("searchFriends");
    }

    /**
     * 検索対象のアカウントを取得する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function searchFriends($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("Account");

        if (count($params) >= 4 && $params[3] > 0) {
            $accounts = $model->findAllBy(array("account_id" => $params[3]));
        } else {
            $accounts = $model->findAllBy(array());
        }

        foreach ($accounts as $account) {
            $cursor = 0;
            $allFriends = array();
            $friendIds = array();
            $followSetting = $account->followSetting();
            while (true) {
                if ($cursor > 0) {
                    $friends = $account->getTwitter()->friends_ids(array("user_id" => $account->twitter_id, "count" => 5000, "cursor" => $cursor));
                } else {
                    $friends = $account->getTwitter()->friends_ids(array("user_id" => $account->twitter_id, "count" => 5000));
                }

                if (!isset($friends->ids) || !is_array($friends->ids)) {
                    break;
                }

                $follow = $loader->loadModel("AccountFriend");
                $follows = $follow->findAllBy(array("account_id" => $account->account_id, "in:user_id" => $friends->ids));
                $followIds = array();
                foreach($follows as $follow){
                    $followIds[] = $follow->use_id;
                }
                foreach ($friends->ids as $userId) {
                    if(in_array($userId, $followIds)){
                        $item = (object) array("id" => $userId);
                        $account->addFriend($item);
                    }else{
                        $friendIds[$userId] = $userId;
                    }
                    if(count($friendIds) == 100){
                        $list = $account->getTwitter()->users_lookup(array("user_id" => implode(",", $friendIds)));
                        if(!property_exists($list, "errors") || !is_array($list->errors) || empty($list->errors)){
                            $friendIds = array();
                            foreach($list as $item){
                                if($account->checkAddUser($item, $followSetting)){
                                    $account->addFriend($item);
                                }
                            }
                        }else{
                            $friendIds = array();
                            Vizualizer_Logger::writeError("ERROR : ".$list->errors[0]->message." in ".$account->screen_name);
                            break;
                        }
                    }
                }
                if(count($friendIds) > 0){
                    $list = $account->getTwitter()->users_lookup(array("user_id" => implode(",", $friendIds)));
                    if(!property_exists($list, "errors") || !is_array($list->errors) || empty($list->errors)){
                        $friendIds = array();
                        foreach($list as $item){
                            if($account->checkAddUser($item, $followSetting)){
                                $account->addFriend($item);
                            }
                        }
                    }else{
                        Vizualizer_Logger::writeError("ERROR : ".$list->errors[0]->message." in ".$account->screen_name);
                    }
                }

                $cursor = $friends->next_cursor;
                if ($cursor == 0) {
                    break;
                }
            }
        }

        return $data;
    }
}
