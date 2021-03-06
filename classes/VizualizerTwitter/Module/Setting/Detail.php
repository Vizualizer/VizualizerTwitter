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
 * アカウントの詳細データを取得する。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class Vizualizertwitter_Module_Setting_Detail extends Vizualizer_Plugin_Module_Detail
{

    function execute($params)
    {
        $post = Vizualizer::request();
        $attr = Vizualizer::attr();
        if($attr[VizualizerAdmin::KEY]->operator_id > 0){
            // サイトデータを取得する。
            $loader = new Vizualizer_Plugin("Twitter");
            $model = $loader->loadModel("Setting");
            $model->findByOperatorId($attr[VizualizerAdmin::KEY]->operator_id);
            $post->set("setting_id", $model->setting_id);
        }
        $this->executeImpl("Twitter", "Setting", $post["setting_id"], $params->get("result", "setting"));
    }
}
