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
 * アカウント検索用にoperator_idを初期化する。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Module_Account_InitializeOperator extends Vizualizer_Plugin_Module
{

    function execute($params)
    {
        // 取得対象のグループIDを調整
        $post = Vizualizer::request();
        $search = $post["search"];
        $operatorIds = array();
        if(!is_array($search["operator_id"])){
            if(!empty($search["operator_id"])){
                $operatorIds = array($search["operator_id"]);
            }
        }else{
            $operatorIds = $search["operator_id"];
        }
        if(!($post["operator_all"] > 0)){
            $post->remove("operator_all");
        }
        if($post["add_operator_id"] > 0){
            $operatorIds[$post["add_operator_id"]] = $post["add_operator_id"];
            $post->remove("add_operator_id");
        }
        if($post["del_operator_id"] > 0){
            unset($operatorIds[$post["del_operator_id"]]);
            $post->remove("del_operator_id");
        }

        // グループIDの対象となるアカウントのリストを取得
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("AccountOperator");
        $search = $post["search"];
        $accountIds = $search["in:account_id"];
        if($post["operator_all"] > 0){
            if(!is_array($accountIds) || empty($accountIds)){
                // 全グループを指定した場合は全てのアカウントを対象にする。
                $accountIds = array();
            }
        }elseif(!empty($operatorIds)){
            $op = array("0");
            $cp = array("0");
            foreach($operatorIds as $operatorId){
                if(substr($operatorId, 0, 1) == "*"){
                    $cp[] = substr($operatorId, 1);
                }else{
                    $op[] = $operatorId;
                }
            }
            $newAccountIds = array();
            $models = $model->findAllBy(array("in:operator_id" => $op));
            foreach($models as $model){
                $newAccountIds[$model->account_id] = $model->account_id;
            }
            $models = $model->findAllBy(array("in:company_id" => $cp));
            foreach($models as $model){
                $newAccountIds[$model->account_id] = $model->account_id;
            }
            if(!is_array($accountIds)){
                $accountIds = $newAccountIds;
            }else{
                $accountIds = array_intersect($accountIds, $newAccountIds);
            }
            if(empty($accountIds)){
                $accountIds = array(0);
            }
        }elseif(!$params->check("default_all_operators")){
            // グループ未指定の場合は対象を無しにする。
            $accountIds = array(0);
        }
        if($post["no_operator"]){
            // アカウントグループに存在するアカウントIDを取得
            $loader = new Vizualizer_Plugin("Twitter");
            $model = $loader->loadModel("AccountOperator");
            $models = $model->findAllBy(array());
            $exceptIds = array();
            foreach($models as $model){
                $exceptIds[$model->account_id] = $model->account_id;
            }
            $model = $loader->loadModel("Account");
            $models = $model->findAllBy(array("nin:account_id" => array_values($exceptIds)));
            $newAccountIds = array(0);
            foreach($models as $model){
                $newAccountIds[$model->account_id] = $model->account_id;
            }
            if(!is_array($accountIds)){
                $accountIds = $newAccountIds;
            }else{
                $accountIds = array_merge($accountIds, $newAccountIds);
            }
        }
        $search["in:account_id"] = $accountIds;
        $post->set("search", $search);
    }
}
