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

        if (isset($post['oauth_verifier']) && isset($_SESSION['TWITTER_INFO'])) {
            $twitterInfo = $_SESSION["TWITTER_INFO"];
            unset($_SESION["TWITTER_INFO"]);
            \Codebird\Codebird::setConsumerKey($twitterInfo["api_key"], $twitterInfo["api_secret"]);

            // トークンを認証する。
            $twitter->setToken($twitterInfo["request_token"], $twitterInfo["request_token_secret"]);

            // アクセストークンを取得
            $reply = $twitter->oauth_accessToken(array('oauth_verifier' => $post['oauth_verifier']));
            print_r($reply);

            /*
            // アカウント情報を登録
            $account = $loader->loadModel("Account");
            if($twitterInfo["account_id"] > 0){
                $account->findByPrimaryKey($twitterInfo["account_id"]);
            }
            $account->twitter_id = $reply->


            // トランザクションの開始
            $connection = Vizualizer_Database_Factory::begin("twitter");

            try {
                $account->save();
                $post->set("account_id", $account->account_id);

                // エラーが無かった場合、処理をコミットする。
                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }

            // GETパラメータを削除するため、自分のURLにリダイレクト
            $this->reload();
            */
        } elseif (!empty($post["add_account"])) {
            // 最適なアプリケーションを取得する。
            $application = $loader->loadModel("Application");
            $application->findByPrefer();
            $twitterInfo = array("application_id" => $application->application_id,"api_key" => $application->api_key, "api_secret" => $application->api_secret);
            if($post["account_id"] > 0){
                $twitterInfo["account_id"] = $post["account_id"];
            }
            \Codebird\Codebird::setConsumerKey($twitterInfo["api_key"], $twitterInfo["api_secret"]);

            // リクエストトークンを取得
            $attr = Vizualizer::attr();
            $reply = $twitter->oauth_requestToken(array("callback_url" => VIZUALIZER_URL . $attr["template_name"]));
            $twitterInfo["request_token"] = $reply->oauth_token;
            $twitterInfo["request_token_secret"] = $reply->oauth_token_secret;
            $_SESSION["TWITTER_INFO"] = $twitterInfo;

            // トークンを保存する。
            $twitter->setToken($twitterInfo["request_token"], $twitterInfo["request_token_secret"]);

            // 認証サイトに移動
            $redirectTo = $twitter->oauth_authorize();
            header('Location: ' . $redirectTo);
            exit;
        }
    }
}
