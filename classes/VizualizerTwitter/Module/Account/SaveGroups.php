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
 * アカウントのグループデータを保存する。
 *
 * @package VizualizerAdmin
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Module_Account_SaveGroups extends Vizualizer_Plugin_Module_Save
{

    function execute($params)
    {
        $post = Vizualizer::request();
        if ($post["add"] || $post["save"]) {
            $loader = new Vizualizer_Plugin("Twitter");
            $groupIds = array();
            if(is_array($post["group_id"])){
                foreach($post["group_id"] as $groupId){
                    if($groupId > 0){
                        $groupIds[] = $groupId;
                    }
                }
            }

            // トランザクションの開始
            $connection = Vizualizer_Database_Factory::begin("twitter");
            try {
                // 登録済みのグループを取得
                $group = $loader->loadModel("AccountGroup");
                $groups = $group->findAllByAccountId($post["account_id"]);

                // 登録から外れたグループを削除
                foreach($groups as $group){
                    if(!in_array($group->group_id, $groupIds)){
                        $group->delete();
                    }
                }
            // エラーが無かった場合、処理をコミットする。
            Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }

            // 指定されたグループを追加
            $group = $loader->loadModel("AccountGroup");
            foreach($groupIds as $groupId){
                $group->addAccountGroup($post["account_id"], $groupId);
            }
        }
    }
}
