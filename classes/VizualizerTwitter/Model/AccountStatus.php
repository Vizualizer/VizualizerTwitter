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
 * アカウントステータスのモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_AccountStatus extends Vizualizer_Plugin_Model
{
    // アカウントステータス
    const ACCOUNT_OK = 0;
    const ACCOUNT_SUSPENDED = 1;
    const APPLICATION_SUSPENDED = 2;

    // フォローステータス
    const FOLLOW_SUSPENDED = 0;
    const FOLLOW_STANDBY = 1;
    const FOLLOW_RUNNING = 2;
    const FOLLOW_FINISHED = 3;
    const FOLLOW_NODATA = 4;
    const UNFOLLOW_RUNNING = 5;
    const UNFOLLOW_FINISHED = 6;

    // ツイートステータス
    const TWEET_SUSPENDED = 0;
    const TWEET_RUNNING = 1;

    // BOTステータス
    const TWEET_BOT_DISABLED = 0;
    const TWEET_BOT_ENABLED = 1;

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("AccountStatuses"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $account_status_id アカウントステータスID
     */
    public function findByPrimaryKey($account_status_id)
    {
        $this->findBy(array("account_status_id" => $account_status_id));
    }

    /**
     * アカウントIDでデータを取得する。
     *
     * @param $account_id アカウントID
     */
    public function findByAccountId($account_id = 0)
    {
        $this->findBy(array("account_id" => $account_id));
    }

    /**
     * ステータスに紐づいたアカウントを取得する
     *
     * @return アカウント
     */
    public function account()
    {
        $loader = new Vizualizer_Plugin("twitter");
        $account = $loader->loadModel("Account");
        $account->findByPrimaryKey($this->account_id);
        return $account;
    }


    /**
     * アカウントステータスを更新する
     *
     * @param int $status アカウントステータス
     */
    public function updateStatus($statusId)
    {
        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");
        try {
            $this->account_status = $statusId;
            $this->save();
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
    }

    /**
     * フォローステータスを更新する
     *
     * @param int $status フォローステータス
     * @param int $next 次回のフォロー実行時間
     * @param boolean $reset
     *            フォローカウントのリセットフラグ（$nextが設定された場合、trueならカウントを0に、falseならカウントを1加算）
     */
    public function updateFollow($statusId, $next = "", $reset = false)
    {
        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");
        try {
            $this->follow_status = $statusId;
            if (!empty($next)) {
                $this->next_follow_time = $next;
                if ($reset) {
                    $this->follow_count = 0;
                } else {
                    $this->follow_count ++;
                }
            }
            $this->save();
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
    }

    /**
     * ツイートステータスを更新する
     *
     * @param int $status ツイートステータス
     * @param int $next 次回のツイート実行時間
     */
    public function updateTweet($statusId, $next = "")
    {
        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");
        try {
            $this->tweet_status = $statusId;
            if (!empty($next)) {
                $this->next_tweet_time = $next;
            }
            $this->save();
            Vizualizer_Database_Factory::commit($connection);
        } catch (Exception $e) {
            Vizualizer_Database_Factory::rollback($connection);
            throw new Vizualizer_Exception_Database($e);
        }
    }
}
