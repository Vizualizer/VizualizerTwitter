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
class VizualizerTwitter_Batch_SearchFollowAccounts extends Vizualizer_Plugin_Batch
{

    public function getName()
    {
        return "Search Follow Twitter Account";
    }

    public function getFlows()
    {
        return array("searchAccounts");
    }

    /**
     * 検索対象のアカウントを取得する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function searchAccounts($params, $data)
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

            // 検索キーワードを取得する。
            $admin = Vizualizer_Session::get(VizualizerAdmin::SESSION_KEY);
            $setting = $loader->loadModel("Setting");
            $setting->findBy(array("operator_id" => $admin["operator_id"]));
            if (!($setting->setting_id > 0)) {
                $setting->follow_keywords = "相互フォロー フォロバ100% リフォロー100%";
                $setting->limit_followers = 2000;
            }
            if ($account->follower_count < $setting->limit_followers) {
                $keywords = explode(" ", $setting->follow_keywords);
            } else {
                $keywords = explode("\r\n", $account->follow_keywords);
            }
            if($account->follow_mode == "1"){
                $keywords[] = "相互フォロー";
                $keywords[] = "フォロバ100%";
                $keywords[] = "リフォロー100%";
            }

            // ユーザー情報を検索
            for ($i = 0; $i < 3; $i ++) {
                $twitter = \Codebird\Codebird::getInstance();
                $twitter->setToken($account->access_token, $account->access_token_secret);

                if($i < 2){
                    $page = $i + 1;
                }else{
                    $page = mt_rand(3, 50);
                }
                $users = (array) $twitter->users_search(array("q" => implode(" ", $keywords), "page" => $page, "per_page" => 20));
                unset($users["httpstatus"]);
                echo "Search Users（".count($users)."） in page ".$page."\r\n";
                foreach ($users as $index => $user) {
                    // ユーザーのIDが取得できない場合はスキップ
                    if(!($user->id > 0)){
                        echo "Skipped invalid ID : ".$user->id." in ".$index."\r\n";
                        print_r($user);
                        continue;
                    }

                    // 日本語チェックに引っかかる場合はスキップ
                    if ($account->japanese_flg == "1" && $user->lang != "ja") {
                        echo "Skipped invalid not Japanese : ".$user->id."\r\n";
                        continue;
                    }

                    // ボットチェックに引っかかる場合はスキップ
                    if ($account->non_bot_flg == "1" && preg_match("/BOT|ボット|ﾎﾞｯﾄ/ui", $user->description) > 0) {
                        echo "Skipped invalid Bot : ".$user->id."\r\n";
                        continue;
                    }

                    // 拒絶キーワードを含む場合はスキップ
                    if (!empty($account->ignore_keywords) && preg_match("/" . implode("|", explode("\r\n", $account->ignore_keywords)) . "/u", $user->description) > 0) {
                        echo "Skipped invalid Profile : ".$user->id."\r\n";
                        continue;
                    }

                    // フォロー対象に追加済みの場合はスキップ
                    $follow = $loader->loadModel("Follow");
                    $follow->findBy(array("account_id" => $account->account_id, "user_id" => $user->id));
                    if ($follow->follow_id > 0) {
                        echo "Skipped targeted : ".$user->id."\r\n";
                        continue;
                    }

                    // トランザクションの開始
                    $connection = Vizualizer_Database_Factory::begin("twitter");

                    try {
                        // フォロー対象に追加
                        $follow->account_id = $account->account_id;
                        $follow->user_id = $user->id;
                        if(!empty($user->following)){
                            $follow->friend_date = date("Y-m-d H:i:s");
                        }
                        $follow->save();
                        echo "Add follow target : ".$user->id."\r\n";

                        // フォロー履歴に追加
                        $history = $loader->loadModel("FollowHistory");
                        $today = date("Y-m-d");
                        $history->findBy(array("account_id" => $account->account_id, "history_date" => $today));
                        $history->account_id = $account->account_id;
                        $history->history_date = $today;
                        $history->target_count ++;
                        $history->save();

                        // リスト無しステータスの場合は待機中ステータスに移行
                        if($account->follow_status == "4"){
                            $account->follow_status == "1";
                            $account->save();
                        }

                        // エラーが無かった場合、処理をコミットする。
                        Vizualizer_Database_Factory::commit($connection);
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                    // フォロー対象に追加
                }
            }
        }
        return $data;
    }
}
