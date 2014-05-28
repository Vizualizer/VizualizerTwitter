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
 * Twitterの認証を行う。
 *
 * @package VizualizerTrade
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Module_Authenticate extends Vizualizer_Plugin_Module
{

    function execute($params)
    {
        $post = Vizualizer::request();
        $loader = new Vizualizer_Plugin("Twitter");
        $twitter = \Codebird\Codebird::getInstance();

        $twitterInfo = Vizualizer_Session::get("TWITTER_INFO");
        if (isset($post['oauth_verifier']) && !empty($twitterInfo)) {
            Vizualizer_Session::remove("TWITTER_INFO");
            \Codebird\Codebird::setConsumerKey($twitterInfo["api_key"], $twitterInfo["api_secret"]);

            // トークンを認証する。
            $twitter->setToken($twitterInfo["request_token"], $twitterInfo["request_token_secret"]);

            // アクセストークンを取得
            $reply = $twitter->oauth_accessToken(array('oauth_verifier' => $post['oauth_verifier']));

            // アクセストークンを設定
            $twitter->setToken($reply->oauth_token, $reply->oauth_token_secret);

            // ユーザー情報を取得
            $user = $twitter->users_show(array("user_id" => $reply->user_id));

            // トランザクションの開始
            $connection = Vizualizer_Database_Factory::begin("twitter");

            try {
                // 最適なサーバーを取得する。
                $server = $loader->loadModel("Server");
                $server->findByPrefer();

                // アカウント情報を登録
                $account = $loader->loadModel("Account");
                $account->twitter_id = $user->id;
                $account->screen_name = $user->screen_name;
                $account->name = $user->name;
                $account->profile_image_url = $user->profile_image_url;
                $account->tweet_count = $user->statuses_count;
                $account->friend_count = $user->friends_count;
                $account->follower_count = $user->followers_count;
                $account->favorite_count = $user->favourites_count;
                $account->follow_mode = VizualizerTwitter_Model_Account::FOLLOW_MODE_SAFE;
                $account->follow_unit = 10;
                $account->follow_unit_interval = 10;
                $account->application_id = $twitterInfo["application_id"];
                $account->server_id = $server->server_id;
                $account->access_token = $reply->oauth_token;
                $account->access_token_secret = $reply->oauth_token_secret;
                $account->save();
                $post->set("account_id", $account->account_id);

                // デフォルトの設定を登録する。
                $setting = $loader->loadModel("FollowSetting");
                $setting->account_id = $account->account_id;
                $setting->setting_index = 0;
                $setting->min_followers = 0;
                $setting->min_follow_interval = 20;
                $setting->refollow_timeout = 40;
                $setting->daily_follows = 40;
                $setting->save();
                $setting = $loader->loadModel("FollowSetting");
                $setting->account_id = $account->account_id;
                $setting->setting_index = 1;
                $setting->min_followers = 100;
                $setting->min_follow_interval = 20;
                $setting->refollow_timeout = 40;
                $setting->daily_follows = 80;
                $setting->save();
                $setting = $loader->loadModel("FollowSetting");
                $setting->account_id = $account->account_id;
                $setting->setting_index = 2;
                $setting->min_followers = 300;
                $setting->min_follow_interval = 20;
                $setting->refollow_timeout = 40;
                $setting->daily_follows = 120;
                $setting->save();
                $setting = $loader->loadModel("FollowSetting");
                $setting->account_id = $account->account_id;
                $setting->setting_index = 3;
                $setting->min_followers = 0;
                $setting->min_follow_interval = 0;
                $setting->refollow_timeout = 0;
                $setting->daily_follows = 0;
                $setting->save();
                $setting = $loader->loadModel("FollowSetting");
                $setting->account_id = $account->account_id;
                $setting->setting_index = 4;
                $setting->min_followers = 0;
                $setting->min_follow_interval = 0;
                $setting->refollow_timeout = 0;
                $setting->daily_follows = 0;
                $setting->save();

                // エラーが無かった場合、処理をコミットする。
                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }

            // GETパラメータを削除するため、自分のURLにリダイレクト
            $this->reload();
        } elseif (!empty($post["add_account"])) {
            // 最適なアプリケーションを取得する。
            $application = $loader->loadModel("Application");
            $application->findByPrefer();
            $twitterInfo = array("application_id" => $application->application_id,"api_key" => $application->api_key, "api_secret" => $application->api_secret);
            \Codebird\Codebird::setConsumerKey($twitterInfo["api_key"], $twitterInfo["api_secret"]);

            // リクエストトークンを取得
            $attr = Vizualizer::attr();
            $reply = $twitter->oauth_requestToken(array("oauth_callback" => VIZUALIZER_URL . $attr["templateName"]));
            $twitterInfo["request_token"] = $reply->oauth_token;
            $twitterInfo["request_token_secret"] = $reply->oauth_token_secret;
            Vizualizer_Session::set("TWITTER_INFO", $twitterInfo);

            // トークンを保存する。
            $twitter->setToken($twitterInfo["request_token"], $twitterInfo["request_token_secret"]);

            // 認証サイトに移動
            $redirectTo = $twitter->oauth_authorize();
            header('Location: ' . $redirectTo);
            $post->remove("add_account");
            exit;
        }
    }
}
