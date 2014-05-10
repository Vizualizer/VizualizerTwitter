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
 * アカウント情報の更新バッチです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Batch_UnfollowAccounts extends Vizualizer_Plugin_Batch
{

    public function getDaemonName(){
        return "unfollow_accounts";
    }

    public function getName()
    {
        return "Unfollow Twitter Account";
    }

    public function getFlows()
    {
        return array("unfollowAccounts");
    }

    /**
     * 検索対象のアカウントを取得する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function unfollowAccounts($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("Account");

        // 本体の処理を実行
        $accounts = $model->findAllBy(array("le:next_follow_time" => date("Y-m-d H:i:s", strtotime("-1 day"))), "next_follow_time", false);

        foreach ($accounts as $account) {
            $loader = new Vizualizer_Plugin("Twitter");

            // 終了ステータスでここに来た場合は日付が変わっているため、待機中に遷移
            if ($account->follow_status == "3") {
                // トランザクションの開始
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    $account->follow_status = 1;
                    $account->save();
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            }

            // アカウントのステータスが待機中か実行中のアカウントのみを対象とする。
            if ($account->follow_status != "1" && $account->follow_status != "2") {
                echo "Account is not ready.\r\n";
                continue;
            }

            $setting = $account->followSetting();

            $today = date("Y-m-d");

            // アンフォロー対象のユーザーを取得
            $follow = $loader->loadModel("Follow");
            $follows = $follow->findAllBy(array("account_id" => $account->account_id, "le:friend_date" => date("Y-m-d H:i:s", strtotime("-".$setting->refollow_timeout." hour")), "follow_date" => null));

            // Twitterへのアクセスを初期化
            $application = $account->application();
            $twitterInfo = array("application_id" => $application->application_id, "api_key" => $application->api_key, "api_secret" => $application->api_secret);
            \Codebird\Codebird::setConsumerKey($twitterInfo["api_key"], $twitterInfo["api_secret"]);
            $twitter = \Codebird\Codebird::getInstance();
            $twitter->setToken($account->access_token, $account->access_token_secret);

            // ステータスを実行中に変更
            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                $account->follow_status = 2;
                $account->save();
                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
            }

            foreach ($follows as $follow) {
                try {
                    // アンフォロー処理を実行する。
                    $twitter->friendships_destroy(array("user_id" => $follow->user_id));
                    $follow->friend_cancel_date = date("Y-m-d H:i:s");
                    $follow->save();

                    // フォロー履歴に追加
                    $history = $loader->loadModel("FollowHistory");
                    $history->findBy(array("account_id" => $account->account_id, "history_date" => $today));
                    $history->account_id = $account->account_id;
                    $history->history_date = $today;
                    $history->unfollow_count ++;
                    $history->save();

                    Vizualizer_Database_Factory::commit($connection);

                    echo "Unfollowed to ".$follow->user_id." in ".$account->account_id."\r\n";

                    // 所定時間待機
                    // sleep($setting->min_follow_interval);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                }
            }

            // ステータスを待機中に変更
            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                $account->follow_count = 0;
                $account->follow_status = 1;
                $account->next_follow_time = date("Y-m-d H:i:s", strtotime("+1 day"));

                $account->save();
                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
            }
        }

        return $data;
    }
}
