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
class VizualizerTwitter_Batch_FollowedAccounts extends Vizualizer_Plugin_Batch
{

    public function getDaemonName()
    {
        return "followed_accounts";
    }

    public function getDaemonInterval()
    {
        return 3600;
    }

    public function getName()
    {
        return "Follower Account Update";
    }

    public function getFlows()
    {
        return array("searchFollowers");
    }

    /**
     * 検索対象のアカウントを取得する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function searchFollowers($params, $data)
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
            $followerIds = array();
            while (true) {
                if ($cursor > 0) {
                    $followers = $account->getTwitter()->followers_ids(array("user_id" => $account->twitter_id, "count" => 5000, "cursor" => $cursor));
                } else {
                    $followers = $account->getTwitter()->followers_ids(array("user_id" => $account->twitter_id, "count" => 5000));
                }

                if (!isset($followers->ids) || !is_array($followers->ids)) {
                    break;
                }

                foreach ($followers->ids as $userId) {
                    $follow = $loader->loadModel("AccountFollower");
                    $follow->findBy(array("account_id" => $account->account_id, "user_id" => $userId));
                    if($follow->account_follower_id > 0){
                        $item = (object) array("id" => $userId);
                        $account->addFollower($item);
                    }else{
                        $followerIds[$userId] = $userId;
                    }
                    if(count($followerIds) == 100){
                        $list = $account->getTwitter()->users_lookup(array("user_id" => implode(",", $followerIds)));
                        if(!property_exists($list, "errors") || !is_array($list->errors) || empty($list->errors)){
                            $followerIds = array();
                            foreach($list as $item){
                                if($account->checkAddUser($item)){
                                    $account->addFollower($item);
                                }
                            }
                        }else{
                            $followerIds = array();
                            Vizualizer_Logger::writeError("ERROR : ".$list->errors[0]->message." in ".$account->screen_name);
                            break;
                        }
                    }
                }
                if(count($followerIds) > 0){
                    $list = $account->getTwitter()->users_lookup(array("user_id" => implode(",", $followerIds)));
                    if(!property_exists($list, "errors") || !is_array($list->errors) || empty($list->errors)){
                        $followerIds = array();
                        foreach($list as $item){
                            if($account->checkAddUser($item)){
                                $account->addFollower($item);
                            }
                        }
                    }else{
                        Vizualizer_Logger::writeError("ERROR : ".$list->errors[0]->message." in ".$account->screen_name);
                    }
                }
                $cursor = $followers->next_cursor;
                if ($cursor == 0) {
                    break;
                }
            }
        }

        return $data;
    }
}
