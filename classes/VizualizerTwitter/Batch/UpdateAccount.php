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
class VizualizerTwitter_Batch_UpdateAccount extends Vizualizer_Plugin_Batch
{
    public function getName(){
        return "Twitter Account Updater";
    }

    public function getFlows(){
        return array("updateAccounts");
    }

    /**
     * アカウント情報更新する。
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function updateAccounts($params, $data){
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("Account");
        $accounts = $model->findAllBy(array());

        foreach($accounts as $account){

            // Twitterへのアクセスを初期化
            $twitter = $account->getTwitter();

            // ユーザー情報を取得
            $user = $twitter->users_show(array("user_id" => $account->twitter_id));

            if (isset($user->id) && !empty($user->id)) {
                // TwitterのIDが取得できた場合のみ更新
                $connection = Vizualizer_Database_Factory::begin("twitter");

                try {
                    // アカウント情報を登録
                    $account->twitter_id = $user->id;
                    $account->screen_name = $user->screen_name;
                    $account->name = $user->name;
                    $account->profile_image_url = $user->profile_image_url;
                    $account->tweet_count = $user->statuses_count;
                    $account->friend_count = $user->friends_count;
                    $account->follower_count = $user->followers_count;
                    $account->favorite_count = $user->favourites_count;
                    $account->notification = $user->notifications;
                    $setting = $loader->loadModel("GlobalSetting");
                    $setting->findByOperator($account->operator_id);
                    if($setting->global_setting_id > 0){
                        $account->rakuten_application_id = $setting->rakuten_application_id;
                        $account->rakuten_affiliate_id = $setting->rakuten_affiliate_id;
                    }
                    echo "Update Account for ".$account->account_id."  : \r\n";
                    $account->save();

                    $today = Vizualizer::now()->date("Y-m-d");
                    $follow = $loader->loadModel("Follow");
                    $searched = $follow->countBy(array("account_id" => $account->account_id, "back:create_time" => $today));
                    $followed = $follow->countBy(array("account_id" => $account->account_id, "back:friend_date" => $today));
                    $refollowed = $follow->countBy(array("account_id" => $account->account_id, "back:follow_date" => $today));
                    $unfollowed = $follow->countBy(array("account_id" => $account->account_id, "back:friend_cancel_date" => $today));
                    $followHistory = $loader->loadModel("FollowHistory");
                    $followHistory->findBy(array("account_id" => $account->account_id, "history_date" => $today));
                    if(!($followHistory->follow_history_id > 0)){
                        $followHistory->account_id = $account->account_id;
                        $followHistory->history_date = $today;
                    }
                    $followHistory->target_count = $searched;
                    $followHistory->follow_count = $followed;
                    $followHistory->followed_count = $refollowed;
                    $followHistory->unfollow_count = $unfollowed;
                    $followHistory->save();

                    // フォローリストがあってフォローリスト無しのステータスの場合は、待機中に変更
                    $followListCount = $follow->countBy(array("account_id" => $account->account_id, "friend_date" => null));
                    if($followListCount > 0){
                        $accountStatus = $account->status();
                        if($accountStatus->follow_status == "4"){
                            $accountStatus->updateFollow("1");
                        }
                    }

                    // エラーが無かった場合、処理をコミットする。
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
            }
        }
        return $data;
    }
}
