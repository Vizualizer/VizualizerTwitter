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
 * ルーティン設定からリツイートの予約を行うバッチです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Batch_RoutineRetweet extends Vizualizer_Plugin_Batch
{

    public function getName()
    {
        return "Routine Retweets";
    }

    public function getFlows()
    {
        return array("routineRetweets");
    }

    /**
     * リツイート予約を登録する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function routineRetweets($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("RetweetRoutine");

        // 当日と翌日の曜日名を取得する。
        $todayName = strtolower(Vizualizer::now()->date("l"));
        $tomorrowName = strtolower(Vizualizer::now()->strToTime("+1 day")->date("l"));

        // 本体の処理を実行
        $routines = $model->findAllBy(array("gt:" . $todayName . "+" . $tomorrowName => "0"));
        foreach($routines as $routine){
            if ($routine->$todayName == "1" && Vizualizer::now()->date("H:i:s") < $routine->schedule_retweet_time) {
                // 当日の予約を設定可能
                $reserveTime = Vizualizer::now()->strToTime($routine->schedule_retweet_time)->date("Y-m-d H:i:s");
            } elseif ($routine->$tomorrowName == "1" && $routine->schedule_retweet_time <= Vizualizer::now()->date("H:i:s")) {
                $reserveTime = Vizualizer::now()->strToTime("+1 day")->strToTime($routine->schedule_retweet_time)->date("Y-m-d H:i:s");
            }
            if (isset($reserveTime)) {
                print_r($reserveTime);
            }
        }

        return $data;
    }
}
