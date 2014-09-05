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
class VizualizerTwitter_Batch_RecoveryRetweet extends Vizualizer_Plugin_Batch
{

    public function getName()
    {
        return "Recover Retweets";
    }

    public function getFlows()
    {
        return array("recoverRetweets");
    }

    /**
     * ツイートを投稿する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function recoverRetweets($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("Retweet");

        // 本体の処理を実行
        $retweets = $model->findAllBy(array("retweet_tweet_id" => "", "ne:retweet_time" => "0000-00-00 00:00:00"), "scheduled_retweet_time", false);

        foreach ($retweets as $retweet) {
            $account = $retweet->account();

            // リツイートを実施
            $twitter = $account->getTwitter();
            $result = (array) $twitter->statuses_retweets_ID(array("id" => $retweet->tweet_id));

            foreach ($result as $index => $item) {
                if (is_numeric($index) && $item->user->id == $account->twitter_id) {
                    // リツイートを更新
                    $connection = Vizualizer_Database_Factory::begin("twitter");
                    try {
                        $retweet->retweet_tweet_id = $item->id_str;
                        $retweet->save();

                        Vizualizer_Database_Factory::commit($connection);
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                }
            }
        }

        return $data;
    }
}
