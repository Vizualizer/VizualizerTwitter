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
    private $page;

    public function getDaemonName(){
        return "search_follow_accounts";
    }

    public function getDaemonInterval()
    {
        return 120;
    }

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

        if(!($this->page > 0) || $this->page > 50){
            $this->page = 1;
        }
        $page = $this->page ++;

        foreach ($accounts as $account) {
            // 検索キーワードを取得する。
            $admin = Vizualizer_Session::get(VizualizerAdmin::SESSION_KEY);
            $setting = $account->followSetting();
            $keywords = explode("\r\n", str_replace(" ", "\r\n", str_replace("　", " ", $setting->follow_keywords)));

            // フォロー対象の検索処理は当日のターゲット追加数が一日のフォロー数上限の2倍以下の未満の場合のみ
            $follow = $loader->loadModel("Follow");
            $searched = $follow->countBy(array("account_id" => $account->account_id, "back:create_time" => Vizualizer::now()->date("Y-m-d")));
            if ($searched < $setting->daily_follows * 2) {
                // ユーザー情報を検索
                foreach($keywords as $keyword){
                    if(empty($keyword)){
                        continue;
                    }
                    $users = (array) $account->getTwitter()->users_search(array("q" => $keyword, "page" => $page, "count" => 20));
                    unset($users["httpstatus"]);
                    Vizualizer_Logger::writeInfo("Search Users（".count($users)."） for ".$keyword." in page ".$page." in " . $account->screen_name);
                    foreach ($users as $index => $user) {
                        if($setting->follow_type == "1" || $setting->follow_type == "3"){
                            $account->addUser($user);
                        }

                        // フォロワーを追加
                        if($setting->follow_type == "2" || $setting->follow_type == "3"){
                            // ユーザーのフォロワーを取得
                            $followers = $account->getTwitter()->followers_ids(array("user_id" => $user->id, "count" => "5000"));

                            if (!isset($followers->ids) || !is_array($followers->ids)) {
                                break;
                            }

                            if(count($followers->ids) > 100){
                                shuffle($followers->ids);
                                $followers->ids = array_splice($followers->ids, 0, 100);
                            }

                            $followerIds = implode(",", $followers->ids);
                            // ユーザーのフォロワーを取得
                            $followers = $account->getTwitter()->users_lookup(array("user_id" => $followerIds));

                            foreach($followers as $follower){
                                if(isset($follower->id) && $follower->id > 0){
                                    if($account->checkAddUser($follower)){
                                        $account->addUser($follower);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }
}
