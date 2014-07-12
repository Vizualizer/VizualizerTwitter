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
        $model = $loader->loadModel("AccountStatus");

        // 本体の処理を実行
        $statuses = $model->findAllBy(array("le:next_follow_time" => Vizualizer::now()->date("Y-m-d H:i:s")), "next_follow_time", false);
        $statusIds = array();
        foreach($statuses as $status){
            $statusIds[] = $status->account_status_id;
        }

        foreach ($statusIds as $statusId) {
            $status = $loader->loadModel("AccountStatus");
            $status->findByPrimaryKey($statusId);
            $account = $status->account();

            // アカウントデータが存在しない場合はスキップ
            if(!($account->account_id > 0)){
                Vizualizer_Logger::writeInfo("Skip for not user in ".$account->screen_name);
                continue;
            }

            // アンフォロー可能状態で無い場合はスキップ
            if(!$account->isUnfollowable()){
                Vizualizer_Logger::writeInfo("Skip for not unfollowable in ".$account->screen_name);
                continue;
            }

            $loader = new Vizualizer_Plugin("Twitter");

            // 終了ステータスでここに来た場合は日付が変わっているため、待機中に遷移
            if ($status->follow_status == "3") {
                $status->updateFollow(1);
            }

            // アカウントのステータスが待機中か実行中のアカウントのみを対象とする。
            if ($status->follow_status != "1" && $status->follow_status != "2") {
                Vizualizer_Logger::writeInfo("Account is not ready in ".$account->screen_name);
                continue;
            }

            $setting = $account->followSetting();

            // 本日のフォロー状況を取得
            $history = $loader->loadModel("FollowHistory");
            $today = Vizualizer::now()->date("Y-m-d");
            $history->findBy(array("account_id" => $account->account_id, "history_date" => $today));

            // アカウントのアンフォロー数が1日のアンフォロー数を超えた場合はステータスを終了にしてスキップ
            $follow = $loader->loadModel("Follow");
            $unfollowed = $follow->countBy(array("account_id" => $account->account_id, "back:friend_cancel_date" => $today));
            if ($setting->daily_unfollows <= $unfollowed) {
                $status->updateFollow(3, Vizualizer::now()->strToTime("+1 day")->date("Y-m-d 00:00:00"), true);
                Vizualizer_Logger::writeInfo("Over daily unfollows for ".$unfollowed." to ".$setting->daily_unfollows." in ".$account->screen_name);
                continue;
            }

            // リストを取得する。
            $follow = $loader->loadModel("Follow");
            $follow->limit(1, 0);
            $follows = $follow->findAllBy(array("account_id" => $account->account_id, "le:friend_date" => Vizualizer::now()->strToTime("-".$setting->refollow_timeout." hour")->date("Y-m-d H:i:s"), "follow_date" => null, "friend_cancel_date" => null), "friend_date", false);

            // ステータスを実行中に変更
            $status->updateFollow(2);

            $result = false;
            foreach ($follows as $follow) {
                $result = $follow->unfollow();
            }

            if($result){
                if($status->follow_count < $setting->follow_unit - 1){
                    $status->updateFollow(2, Vizualizer::now()->strToTime("+".$setting->follow_interval." second")->date("Y-m-d H:i:s"));
                }else{
                    $status->updateFollow(1, Vizualizer::now()->strToTime("+".$setting->follow_unit_interval." minute")->date("Y-m-d H:i:s"), true);
                }
            }
        }

        return $data;
    }
}
