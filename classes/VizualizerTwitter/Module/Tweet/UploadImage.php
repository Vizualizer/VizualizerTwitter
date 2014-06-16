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
 * ツイート設定のデータを取得する。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class Vizualizertwitter_Module_Tweet_Upload extends Vizualizer_Plugin_Module_Upload
{

    /**
     * エラーチェックを行い、OKであればtrue、NGであればfalseを返す。
     *
     * @param $title CSVのタイトル行データ
     */
    protected function checkTitle($title)
    {
        return true;
    }

    /**
     * エラーチェックを行い、登録するモデルを返す。
     *
     * @param $line CSVの行番号
     * @param $model 登録に使用するモデルクラス
     * @param $data CSVの行データ
     */
    protected function check($line, $model, $data)
    {
        $post = Vizualizer::request();
        if (count($data) > 7) {
            $model->findBy(array("account_id" => $post["account_id"], "twitter_id" => $data[0]));
            $model->twitter_id = $data[0];
            if ($post["account_id"] > 0) {
                $model->account_id = $post["account_id"];
            } else {
                echo $this->errors[] = $line . "行目：アカウントが指定されていません。";
                return null;
            }
            $model->user_id = $data[1];
            $model->screen_name = $data[2];
            if (!empty($data[3])) {
                $model->tweet_text = $data[3];
            } else {
                echo $this->errors[] = $line . "行目：ツイートする内容が設定されていません。";
                return null;
            }
            $model->media_url = $data[4];
            $model->media_filename = $data[5];
            $model->retweet_count = $data[6];
            $model->favorite_count = $data[7];
            return $model;
        }
        return null;
    }

    function execute($params)
    {
        $post = Vizualizer::request();
        $this->executeImpl($params, "Twitter", "Tweet", $params->get("key", "upload"));
    }
}
