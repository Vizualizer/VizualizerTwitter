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
        return "followed_accounts";
    }

    public function getDaemonInterval()
    {
        return 900;
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
            // Twitterへのアクセスを初期化
            $application = $account->application();
            $twitterInfo = array("application_id" => $application->application_id, "api_key" => $application->api_key, "api_secret" => $application->api_secret);
            \Codebird\Codebird::setConsumerKey($twitterInfo["api_key"], $twitterInfo["api_secret"]);

            // フォロワーのIDを取得する。
            $twitter = \Codebird\Codebird::getInstance();
            $twitter->setToken($account->access_token, $account->access_token_secret);
            $cursor = 0;
            $friendIds = array();
            while (true) {
                if ($cursor > 0) {
                    $friends = $twitter->friends_ids(array("user_id" => $account->twitter_id, "count" => 5000, "cursor" => $cursor));
                } else {
                    $friends = $twitter->friends_ids(array("user_id" => $account->twitter_id, "count" => 5000));
                }

                foreach ($friends->ids as $userId) {
                    $friendIds[$userId] = $userId;
                }
                $cursor = $friends->next_cursor;
                if ($cursor == 0) {
                    break;
                }
            }
            // トランザクションの開始
            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                // フォロワーになっていないフレンドを取得
                $follow = $loader->loadModel("Follow");
                $follows = $follow->findAllBy(array("account_id" => $account->account_id, "in: user_id" => array_values($followerIds)));
                foreach ($follows as $follow) {
                    if (array_key_exists($follow->user_id, $friendIds)) {
                        if (empty($follow->friend_date)) {
                            $follow->friend_date = date("Y-m-d H:i:s");
                            $follow->save();
                        }
                        unset($friendIds[$follow->user_id]);
                    }
                }

                foreach ($friendIds as $userId) {
                    $follow = $loader->loadModel("Follow");
                    $follow->account_id = $account->account_id;
                    $follow->user_id = $userId;
                    $follow->friend_date = date("Y-m-d H:i:s");
                    $follow->save();
                }
                // エラーが無かった場合、処理をコミットする。
                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }
        }

        return $data;
    }
}