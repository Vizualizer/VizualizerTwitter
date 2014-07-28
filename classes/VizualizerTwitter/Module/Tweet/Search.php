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
 * ツイートのリストを取得する。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Module_Tweet_Search extends Vizualizer_Plugin_Module_List
{
    private function sortByRetweet($data){
        usort($data, function($small, $large){
            return $small->retweet_count < $large->retweet_count;
        });
        return $data;
    }

    private function sortByFavorite($data){
        usort($data, function($small, $large){
            return $small->favorite_count < $large->favorite_count;
        });
        return $data;
    }

    function execute($params)
    {
        $post = Vizualizer::request();
        if($post["account_id"] > 0){
            $loader = new Vizualizer_Plugin("twitter");
            $account = $loader->loadModel("Account");
            $account->findByPrimaryKey($post["account_id"]);
            $twitter = $account->getTwitter();
            if(!empty($post["url"]) && empty($post["screen_name"])){
                if(preg_match("/^https:\\/\\/twitter.com\\/([a-zA-Z0-9_-]+)\\/?$/", $post["url"], $p) > 0){
                    $post->set("screen_name", $p[1]);
                }
            }
            if(!empty($post["keyword"])){
                // ツイートを検索
                $tweetsTemp = $twitter->search_tweets(array("q" => $post["keyword"]." -RT ", "lang" => "ja", "locale" => "ja", "count" => 100, "result_type" => "mixed"));
                $tweets = $tweetsTemp->statuses;
                if($post["sort"] == "retweet"){
                    $tweets = $this->sortByRetweet($tweets);
                }elseif($post["sort"] == "favorite"){
                    $tweets = $this->sortByFavorite($tweets);
                }
                $attr = Vizualizer::attr();
                $attr["tweets"] = $tweets;
            }elseif(!empty($post["screen_name"])){
                // ツイートを検索
                $tweets = (array) $twitter->statuses_userTimeline(array("screen_name" => $post["screen_name"], "count" => "200"));
                unset($tweets["httpstatus"]);
                if($post["sort"] == "retweet"){
                    $tweets = $this->sortByRetweet($tweets);
                }elseif($post["sort"] == "favorite"){
                    $tweets = $this->sortByFavorite($tweets);
                }
                $attr = Vizualizer::attr();
                $attr["tweets"] = $tweets;
            }else{
                $attr["tweets"] = array();
            }
        }
    }
}
