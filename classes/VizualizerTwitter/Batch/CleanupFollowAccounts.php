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
 * フォロー情報を整理するためのバッチです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Batch_CleanupFollowAccounts extends Vizualizer_Plugin_Batch
{

    public function getName()
    {
        return "Cleanup Follow Account";
    }

    public function getFlows()
    {
        return array("getCleanupFollows");
    }

    public function getDaemonInterval()
    {
        return 3600;
    }

    /**
     * フォロー状態を整理する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function getCleanupFollows($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $account = $loader->loadModel("Account");

        $accounts = $account->findAllBy(array());
        foreach($accounts as $account){
            $cursor = 0;
            $friendIds = array();
            while (true) {
                if ($cursor > 0) {
                    $friends = $account->getTwitter()->friends_ids(array("user_id" => $account->twitter_id, "count" => 5000, "cursor" => $cursor));
                } else {
                    $friends = $account->getTwitter()->friends_ids(array("user_id" => $account->twitter_id, "count" => 5000));
                }
                if($friends->httpstatus != "200"){
                    break;
                }
                foreach($friends->ids as $id){
                    $friendIds[$id] = $id;
                }
                if($friends->next_cursor == 0){
                    break;
                }
                $cursor = $friends->next_cursor;
            }

            $follow = $loader->loadModel("Follow");
            $follows = $follow->findAllBy(array("account_id" => $account->account_id, "ne:friend_date" => null, "friend_cancel_date" => null), "friend_date", false);
            foreach($follows as $follow){
                if(!in_array($follow->user_id, $friendIds)){
                    if(!$follow->reset()){
                        Vizualizer_Logger::error("Failed to reset follow to ".$follow->user_id." in ".$account->screen_name);
                    }
                }
            }
        }
        return $data;
    }
}
