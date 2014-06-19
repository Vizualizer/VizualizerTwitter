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
    public function getDaemonName(){
        return "follow_accounts";
    }

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
        $model = $loader->loadModel("AccountStatus");

        // 本体の処理を実行
        $statuses = $model->findAllBy(array("le:next_follow_time" => date("Y-m-d H:i:s")), "next_follow_time", false);

        foreach ($statuses as $status) {
            $account = $status->account();

            // フォロー可能状態で無い場合はスキップ
            if(!$account->isFollowable()){
                echo "Skip for not followable.\r\n";
                continue;
            }

            $loader = new Vizualizer_Plugin("Twitter");

            // 終了ステータスでここに来た場合は日付が変わっているため、待機中に遷移
            if ($account->status()->follow_status == "3") {
                $account->updateFollowStatus(1);
            }

            // アカウントのステータスが待機中か実行中のアカウントのみを対象とする。
            if ($account->status()->follow_status != "1" && $account->status()->follow_status != "2") {
                echo "Account is not ready.\r\n";
                continue;
            }

            // フォロー設定を取得
            $setting = $account->followSetting();

            // 本日のフォロー状況を取得
            $history = $loader->loadModel("FollowHistory");
            $today = date("Y-m-d");
            $history->findBy(array("account_id" => $account->account_id, "history_date" => $today));

            // アカウントのフォロー数が1日のフォロー数を超えた場合はステータスを終了にしてスキップ
            if ($setting->daily_follows <= $history->follow_count) {
                $account->updateFollowStatus(3, date("Y-m-d 00:00:00", strtotime("+1 day")), true);
                echo "Over daily follows for ".$history->follow_count." to ".$setting->daily_follows." in ".$account->account_id."\r\n";
            }

            // リストを取得する。
            $follow = $loader->loadModel("Follow");
            $follow->limit(1, 0);
            $follows = $follow->findAllBy(array("account_id" => $account->account_id, "friend_date" => null), "follow_date", true);

            // 結果が0件の場合はリスト無しにしてスキップ
            if ($follows->count() == 0) {
                $account->updateFollowStatus(4);
                echo "No List in ".$account->account_id."\r\n";
            }

            // ステータスを実行中に変更
            $account->updateFollowStatus(2);

            foreach ($follows as $follow) {
                $connection = Vizualizer_Database_Factory::begin("twitter");
                try {
                    // フォロー処理を実行する。
                    $account->getTwitter()->friendships_create(array("user_id" => $follow->user_id, "follow" => true));
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
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                }
            }

            if($account->status()->follow_count < $setting->follow_unit - 1){
                $account->updateFollowStatus(2, date("Y-m-d H:i:s", strtotime("+".$setting->follow_interval." second")));
            }else{
                $account->updateFollowStatus(1, date("Y-m-d H:i:s", strtotime("+".$account->follow_unit_interval." minute")), true);
            }
        }

        return $data;
    }
}
