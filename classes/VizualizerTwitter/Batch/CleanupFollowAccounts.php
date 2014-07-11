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
 * フォロー情報を整理するためのバッチです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Batch_CleanupFollowAccounts extends Vizualizer_Plugin_Batch
{

    public function getDaemonName()
    {
        return "cleanup_follow_accounts";
    }

    public function getDaemonInterval()
    {
        return 900;
    }

    public function getName()
    {
        return "Cleanup Follow Account";
    }

    public function getFlows()
    {
        return array("cleanupFollows", "cleanupFollowers", "cleanupCancelFollows", "cleanupFollowStatus");
    }

    /**
     * フォロー状態を整理する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function cleanupFollows($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $follows = $loader->loadTable("Follows");
        $friends = $loader->loadTable("AccountFriends");

        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");

        try {
            $loader = new Vizualizer_Plugin("Twitter");
            $update = new Vizualizer_Query_Update($follows);
            $update->joinLeft($friends, array($follows->account_id." = ".$friends->account_id, $follows->user_id." = ".$friends->user_id, $friends->checked_time." > ?"), array(Vizualizer::now()->strToTime("-48 hour")->date("Y-m-d H:i:s")));
            $update->addSets($follows->friend_date." = ".$friends->checked_time);
            $update->addSets($follows->update_time." = ?", array(Vizualizer::now()->date("Y-m-d H:i:s")));
            $update->addWhere($follows->friend_date." IS NULL");
            $update->execute();
        // エラーが無かった場合、処理をコミットする。
        Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }

        return $data;
    }

    /**
     * フォロワー状態を整理する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function cleanupFollowers($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $follows = $loader->loadTable("Follows");
        $followers = $loader->loadTable("AccountFollowers");

        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");

        try {
            $loader = new Vizualizer_Plugin("Twitter");
            $update = new Vizualizer_Query_Update($follows);
            $update->joinLeft($followers, array($follows->account_id." = ".$followers->account_id, $follows->user_id." = ".$followers->user_id, $followers->checked_time." > ?"), array(Vizualizer::now()->strToTime("-48 hour")->date("Y-m-d H:i:s")));
            $update->addSets($follows->follow_date." = ".$followers->checked_time);
            $update->addSets($follows->update_time." = ?", array(Vizualizer::now()->date("Y-m-d H:i:s")));
            $update->addWhere($follows->follow_date." IS NULL OR ".$followers->checked_time." IS NULL");
            $update->execute();
        // エラーが無かった場合、処理をコミットする。
        Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }

        return $data;
    }

    /**
     * フォロワー状態を整理する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function cleanupCancelFollows($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $follows = $loader->loadTable("Follows");
        $friends = $loader->loadTable("AccountFriends");

        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");

        try {
            $loader = new Vizualizer_Plugin("Twitter");
            $update = new Vizualizer_Query_Update($follows);
            $update->joinLeft($friends, array($follows->account_id." = ".$friends->account_id, $follows->user_id." = ".$friends->user_id, $friends->checked_time." > ?"), array(Vizualizer::now()->strToTime("-48 hour")->date("Y-m-d H:i:s")));
            $update->addSets($follows->friend_cancel_date." = ?", array(Vizualizer::now()->date("Y-m-d H:i:s")));
            $update->addSets($follows->update_time." = ?", array(Vizualizer::now()->date("Y-m-d H:i:s")));
            $update->addWhere($follows->friend_date." IS NOT NULL");
            $update->addWhere($follows->friend_cancel_date." IS NULL");
            $update->addWhere($friends->checked_time." IS NULL");
            $update->execute();
        // エラーが無かった場合、処理をコミットする。
        Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }

        return $data;
    }

    /**
     * フォロー整合性状態を整理する。
     *
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function cleanupFollowStatus($params, $data)
    {
        $loader = new Vizualizer_Plugin("Twitter");
        $follows = $loader->loadTable("Follows");

        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");

        try {
            $loader = new Vizualizer_Plugin("Twitter");
            $update = new Vizualizer_Query_Update($follows);
            $update->addSets($follows->friend_date." = NULL");
            $update->addSets($follows->friend_cancel_date." = NULL");
            $update->addSets($follows->update_time." = ?", array(Vizualizer::now()->date("Y-m-d H:i:s")));
            $update->addWhere($follows->friend_date." IS NOT NULL");
            $update->addWhere($follows->follow_date." IS NOT NULL");
            $update->addWhere($follows->friend_cancel_date." IS NOT NULL");
            $update->execute();
        // エラーが無かった場合、処理をコミットする。
        Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }

        return $data;
    }
}
