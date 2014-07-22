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
 * アカウントのリストを取得する。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Module_Account_List extends Vizualizer_Plugin_Module_List
{

    function execute($params)
    {
        $attr = Vizualizer::attr();
        $post = Vizualizer::request();
        if($params->get("operator", "single") == "list"){
            if($attr[VizualizerAdmin::KEY]->role()->role_code != ""){
                $loader = new Vizualizer_Plugin("twitter");
                $accountOperator = $loader->loadModel("AccountOperator");
                $accountOperators = $accountOperator->findAllByOperatorId($attr[VizualizerAdmin::KEY]->operator_id);
                $search = $post["search"];
                $search["in:account_id"] = array();
                foreach($accountOperators as $account){
                    $search["in:account_id"][] = $account->account_id;
                }
                $post->set("search", $search);
            }
        }
        $this->executeImpl($params, "Twitter", "Account", $params->get("result", "accounts"));
    }
}
