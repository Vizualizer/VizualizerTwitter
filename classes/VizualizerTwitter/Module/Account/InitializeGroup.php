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
 * アカウント検索用にgroup_idを初期化する。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Module_Account_InitializeGroup extends Vizualizer_Plugin_Module
{

    function execute($params)
    {
        // 取得対象のグループIDを調整
        $post = Vizualizer::request();
        if(!is_array($post["group_id"])){
            $post->set("group_id", array());
        }
        $groupIds = $post["group_id"];
        if(!($post["group_all"] > 0)){
            $post->remove("group_all");
        }
        if($post["add_group_id"] > 0){
            $groupIds[$post["add_group_id"]] = $post["add_group_id"];
            $post->remove("add_group_id");
        }
        if($post["del_group_id"] > 0){
            unset($groupIds[$post["del_group_id"]]);
            $post->remove("del_group_id");
        }
        $post->set("group_id", $groupIds);

        // グループIDの対象となるアカウントのリストを取得
        $loader = new Vizualizer_Plugin("Twitter");
        $model = $loader->loadModel("AccountGroup");
        $search = $post["search"];
        $accountIds = $search["in:account_id"];
        if($post["group_all"] > 0){
            // 全グループを指定した場合は全てのアカウントを対象にする。
            $accountIds = array();
        }elseif(!empty($groupIds)){
            $models = $model->findAllBy(array("in:group_id" => $groupIds));
            $newAccountIds = array();
            foreach($models as $model){
                $newAccountIds[$model->account_id] = $model->account_id;
            }
            if(!is_array($accountIds)){
                $accountIds = $newAccountIds;
            }else{
                $accountIds = array_intersect($accountIds, $newAccountIds);
            }
        }else{
            // グループ未指定の場合は対象を無しにする。
            $accountIds = array(0);
        }
        if($post["no_group"]){
            // アカウントグループに存在するアカウントIDを取得
            $loader = new Vizualizer_Plugin("Twitter");
            $model = $loader->loadModel("AccountGroup");
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
                $accountIds = array_intersect($accountIds, $newAccountIds);
            }
        }
        $search["in:account_id"] = $accountIds;
        $post->set("search", $search);
    }
}
