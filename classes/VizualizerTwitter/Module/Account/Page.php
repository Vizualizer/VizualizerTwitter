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
class VizualizerTwitter_Module_Account_Page extends Vizualizer_Plugin_Module_Page
{

    function execute($params)
    {
        $attr = Vizualizer::attr();
        $post = Vizualizer::request();
        $loader = new Vizualizer_Plugin("twitter");
        if($params->get("operator", "single") == "list"){
            if ($params->check("adminRoles")) {
                $adminRoles = explode(",", $params->get("adminRoles"));
            }else{
                $adminRoles = array();
            }
            if(!in_array($attr[VizualizerAdmin::KEY]->role()->role_code, $adminRoles)){
                $accountOperator = $loader->loadModel("AccountOperator");
                $accountOperators = $accountOperator->findAllByOperatorId($attr[VizualizerAdmin::KEY]->operator_id);
                $search = $post["search"];
                if(!is_array($search)){
                    $search = array();
                }
                $accountIds = array("0");
                foreach($accountOperators as $account){
                    $accountIds[] = $account->account_id;
                }
                if(array_key_exists("in:account_id", $search) && is_array($search["in:account_id"]) && !empty($search["in:account_id"])){
                    $search["in:account_id"] = array_intersect($search["in:account_id"], $accountIds);
                }else{
                    $search["in:account_id"] = $accountIds;
                }
                $post->set("search", $search);
            }
        }
        // account_attributeで検索する処理を追加
        if(!empty($post["account_attribute"])){
            $setting = $loader->loadModel("Setting");
            $settings = $setting->findAllBy(array("account_attribute" => $post["account_attribute"]));
            $search = $post["search"];
            if(!is_array($search)){
                $search = array();
            }
            $accountIds = array("0");
            foreach($settings as $setting){
                $accountIds[] = $setting->account_id;
            }
            if(array_key_exists("in:account_id", $search) && is_array($search["in:account_id"]) && !empty($search["in:account_id"])){
                $search["in:account_id"] = array_intersect($search["in:account_id"], $accountIds);
            }else{
                $search["in:account_id"] = $accountIds;
            }
            $post->set("search", $search);
        }
        $this->executeImpl($params, "Twitter", "Account", $params->get("result", "accounts"));
        // 結果に属性一覧を追加する。
        $account = $loader->loadModel("Account");
        $attr["account_attributes"] = $account->attributes();
    }
}
