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
class Vizualizertwitter_Module_Tweet_Download extends Vizualizer_Plugin_Module_Download
{

    protected function filterData($data){
        return str_replace("\r\n", "\\n", $data);
    }

    function execute($params)
    {
        $post = Vizualizer::request();
        if($post["account_id"] > 0){
            $post->set("search", array("account_id" => $post["account_id"]));
        }else{
            $loader = new Vizualizer_Plugin("twitter");
            $model = $loader->loadModel("Account");
            $models = $model->findAllBy(array());
            $accountIds = array();
            foreach($models as $model){
                $accountIds[] = $model->account_id;
            }
            $post->set("search", array("in:account_id" => $accountIds));
        }
        $this->executeImpl($params, "Twitter", "Tweet", $params->get("result", "tweet"));
    }
}
