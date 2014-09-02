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
                continue;
            }

            // フォロー可能状態で無い場合はスキップ
            if(!$account->isFollowable()){
                Vizualizer_Logger::writeInfo("Skip for not followable in ".$account->screen_name);
                continue;
            }

            $loader = new Vizualizer_Plugin("Twitter");

            // 終了ステータスでここに来た場合は日付が変わっているため、待機中に遷移
            if ($status->follow_status == "3") {
                Vizualizer_Logger::writeInfo("Account reactivated by end status in ".$account->screen_name);
                $status->updateFollow(1);
            }

            // アカウントのステータスが待機中か実行中のアカウントのみを対象とする。
            if ($status->follow_status != "1" && $status->follow_status != "2") {
                Vizualizer_Logger::writeInfo("Account is not ready in ".$account->screen_name);
                continue;
            }

            // フォロー設定を取得
            $setting = $account->followSetting();

            // 前日のフォロー状況を取得
            $history = $loader->loadModel("FollowHistory");
            $yesterday = Vizualizer::now()->strToTime("-1 day")->date("Y-m-d");
            $history->findBy(array("account_id" => $account->account_id, "history_date" => $yesterday));

            // アカウントのフォロー数が1日のフォロー数を超えた場合はステータスを終了にしてスキップ
            if ($setting->daily_follows <= $account->friend_count - $history->follow_count) {
                $status->updateFollow(3, Vizualizer::now()->strToTime("+1 day")->date("Y-m-d 00:00:00"), true);
                Vizualizer_Logger::writeInfo("Over daily follows for ".$followed." to ".$setting->daily_follows." in ".$account->screen_name);
                continue;
            }

            // リストを取得する。
            $follow = $loader->loadModel("Follow");
            $follow->limit(1, 0);
            if(Vizualizer_Configure::get("refollow_enabled") === false){
                // リフォローを行わない設定にしている場合、自分をフォローしているユーザーは対象外とする。
                $follows = $follow->findAllBy(array("account_id" => $account->account_id, "friend_date" => null, "follow_date" => null, "friend_cancel_date" => null), "follow_date", true);
            }else{
                $follows = $follow->findAllBy(array("account_id" => $account->account_id, "friend_date" => null, "friend_cancel_date" => null), "follow_date", true);
            }

            // 結果が0件の場合はリスト無しにしてスキップ
            if ($follows->count() == 0) {
                $status->updateFollow(4);
                Vizualizer_Logger::writeInfo("No List in ".$account->screen_name);
                continue;
            }

            // ステータスを実行中に変更
            $status->updateFollow(2);

            $result = false;
            foreach ($follows as $follow) {
                $result = $follow->follow();
            }

            if($result){
                if ($status->follow_count < $setting->follow_unit - 1) {
                    $status->updateFollow(2, Vizualizer::now()->strToTime("+".$setting->follow_interval." second")->date("Y-m-d H:i:s"));
                } else {
                    $status->updateFollow(1, Vizualizer::now()->strToTime("+".$setting->follow_unit_interval." minute")->date("Y-m-d H:i:s"), true);
                }
            }
        }

        return $data;
    }
}
