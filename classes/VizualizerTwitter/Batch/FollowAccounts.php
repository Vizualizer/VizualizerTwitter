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
class VizualizerTwitter_Batch_FollowAccounts extends Vizualizer_Plugin_Batch
{

    public function getName()
    {
        return "Follow Twitter Account";
    }

    public function getFlows()
    {
        return array("followAccounts");
    }

    /**
     * 検索対象のアカウントを取得する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function followAccounts($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("Account");

        if (count($params) >= 4 && $params[3] > 0) {
            $accounts = $model->findAllBy(array("account_id" => $params[3]));
        } else {
            $accounts = $model->findAllBy(array());
        }

        foreach ($accounts as $account) {
            // アカウントのステータスが待機中のアカウントのみを対象とする。
            if ($account->follow_status != "1") {
                echo "Account is not ready.\r\n";
                continue;
            }

            $pid = pcntl_fork();
            if ($pid == -1) {
                die('サブプロセスを実行できません');
            } elseif ($pid) {
                // 親プロセスの場合
                pcntl_wait($status);
            } else {
                // 子プロセスの場合
                // 規定時間内にフォローされている場合はスキップ
                $loader = new Vizualizer_Plugin("Twitter");
                $setting = $account->followSetting();
                $follow = $loader->loadModel("Follow");
                $follow->findBy(array("account_id" => $account->account_id, "gt:friend_date" => date("Y-m-d H:i:s", strtotime("-" . $account->follow_unit_interval . " minute"))));
                if ($follow->follow_id > 0) {
                    echo "Interval time from ".date("Y-m-d H:i:s", strtotime("-" . $account->follow_unit_interval . " minute"))." in ".$account->account_id."\r\n";
                    continue;
                }

                // アカウントのフォロー数が1日のフォロー数を超えた場合はステータスを終了にしてスキップ
                $history = $loader->loadModel("FollowHistory");
                $today = date("Y-m-d");
                $history->findBy(array("account_id" => $account->account_id, "history_date" => $today));
                if ($setting->daily_follows < $history->follow_count) {
                    // トランザクションの開始
                    $connection = Vizualizer_Database_Factory::begin("twitter");
                    try {
                        $account->follow_status = 3;
                        $account->save();
                        Vizualizer_Database_Factory::commit($connection);
                        echo "Over daily follows in ".$account->account_id."\r\n";
                        continue;
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                }

                // リストを取得する。
                $follow = $loader->loadModel("Follow");
                $follow->limit($account->follow_unit, 0);
                $follows = $follow->findAllByAccountId($account->account_id);

                // 結果が0件の場合はリスト無しにしてスキップ
                if ($follows->count() == 0) {
                    // トランザクションの開始
                    $connection = Vizualizer_Database_Factory::begin("twitter");

                    try {
                        $account->follow_status = 4;
                        $account->save();
                        Vizualizer_Database_Factory::commit($connection);
                        echo "No List in ".$account->account_id."\r\n";
                        continue;
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                }

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
                        // フォロー処理を実行する。
                        $twitter->friendships_create(array("user_id" => $follow->user_id, "follow" => true));
                        $follow->friend_date = date("Y-m-d H:i:s");
                        $follow->save();

                        // フォロー履歴に追加
                        $history = $loader->loadModel("FollowHistory");
                        $history->findBy(array("account_id" => $account->account_id, "history_date" => $today));
                        $history->account_id = $account->account_id;
                        $history->history_date = $today;
                        $history->follow_count ++;
                        $history->save();

                        Vizualizer_Database_Factory::commit($connection);

                        echo "Followed to ".$follow->user_id." in ".$account->account_id."\r\n";

                        // 所定時間待機
                        // sleep($setting->min_follow_interval);
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                    }
                }

                // ステータスを待機中に変更
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    $account->follow_status = 1;
                    $account->save();
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                }
            }
        }

        return $data;
    }
}
