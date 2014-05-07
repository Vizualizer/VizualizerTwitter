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
 * オペレータのデータを保存する。
 *
 * @package VizualizerAdmin
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Module_Account_Save extends Vizualizer_Plugin_Module_Save
{

    function execute($params)
    {
        $post = Vizualizer::request();
        if(!empty($post->follow_keyword)){
            $data = explode("\r\n", $post->follow_keywords);
            if(!is_array($data)){
                $data = array();
            }
            $data[] = $post->follow_keyword;
            $post->follow_keywords = implode("\r\n", $data);
        }
        if(!empty($post->ignore_keyword)){
            $data = explode("\r\n", $post->ignore_keywords);
            if(!is_array($data)){
                $data = array();
            }
            $data[] = $post->ignore_keyword;
            $post->ignore_keywords = implode("\r\n", $data);
        }
        $this->executeImpl("Twitter", "Account", "account_id");
    }
}
