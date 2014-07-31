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
 * フォローのモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Model_Follow extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("twitter");
        parent::__construct($loader->loadTable("Follows"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $follow_id フォローID
     */
    public function findByPrimaryKey($follow_id)
    {
        $this->findBy(array("follow_id" => $follow_id));
    }

    /**
     * アカウントIDでデータを取得する。
     *
     * @param $account_id アカウントID
     * @return フォローのリスト
     */
    public function findAllByAccountId($account_id, $sort = "", $reverse = false)
    {
        return $this->findAllBy(array("account_id" => $account_id), $sort, $reverse);
    }

    /**
     * ユーザーIDでデータを取得する。
     *
     * @param $user_id ユーザーID
     * @return フォローのリスト
     */
    public function findAllByUserId($user_id, $sort = "", $reverse = false)
    {
        return $this->findAllBy(array("user_id" => $user_id), $sort, $reverse);
    }

    /**
     * フォローに紐づいたアカウントを取得する
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
     * 指定のフォロー処理を実施する。
     */
    public function follow()
    {
        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");

        // フォローを実行するには、新規か被フォローのレコードでないといけない。
        if($this->friend_date == null && $this->friend_cancel_date == null){
            // フォロー上限に達している場合はエラーになる可能性があるため、いかなる理由であってもフォローを行わない。
            $account = $this->account();
            if ($account->friend_count < $account->followLimit()) {
                // フォロー数が上限に達していない場合はフォローを実行
                $result = $account->getTwitter()->friendships_create(array("user_id" => $this->user_id, "follow" => true));
                if (isset($result->errors)) {
                    if ($result->errors[0]->code == "161") {
                        $account->status()->updateFollow(3, Vizualizer::now()->strTotime("+1 hour")->date("Y-m-d 00:00:00"), true);
                    } elseif ($result->errors[0]->code == "108" || $result->errors[0]->code == "36") {
                        // フォロー処理の際に、ユーザーが既に存在しない場合は、データ自体を削除
                        $connection = Vizualizer_Database_Factory::begin("twitter");
                        try {
                            $this->delete();
                            Vizualizer_Database_Factory::commit($connection);
                        } catch (Exception $e) {
                            Vizualizer_Database_Factory::rollback($connection);
                            throw new Vizualizer_Exception_Database($e);
                        }
                    } elseif ($result->errors[0]->code == "162") {
                        // ブロックされている場合は、該当ユーザーをアンフォロー扱いにする。
                        $connection = Vizualizer_Database_Factory::begin("twitter");
                        try {
                            $this->friend_cancel_date = $this->friend_date = "1900-01-01 00:00:00";
                            $this->save();
                            Vizualizer_Logger::writeInfo("Following is blocked from " . $this->user_id . " in " . $account->screen_name);
                            Vizualizer_Database_Factory::commit($connection);
                        } catch (Exception $e) {
                            Vizualizer_Database_Factory::rollback($connection);
                            throw new Vizualizer_Exception_Database($e);
                        }
                    } elseif ($result->errors[0]->code == "160") {
                        // 既にフォロー済みの場合は、DBに日付のみを設定
                        $connection = Vizualizer_Database_Factory::begin("twitter");
                        try {
                            $this->friend_date = Vizualizer::now()->date("Y-m-d H:i:s");
                            $this->save();
                            Vizualizer_Logger::writeInfo("Already followed to " . $this->user_id . " in " . $account->screen_name);
                            Vizualizer_Database_Factory::commit($connection);
                        } catch (Exception $e) {
                            Vizualizer_Database_Factory::rollback($connection);
                            throw new Vizualizer_Exception_Database($e);
                        }
                        return true;
                    } elseif ($result->errors[0]->code == "64") {
                        // アカウントのステータスを凍結中に変更
                        $account->status()->updateStatus(1);
                    }else{
                        // アカウント凍結中を解除
                        $account->status()->updateStatus(0);
                    }
                    Vizualizer_Logger::writeError("Failed to Follow on " . $this->user_id . " in " . $account->screen_name . " by " . print_r($result->errors, true));
                    return false;
                } else {
                    $connection = Vizualizer_Database_Factory::begin("twitter");
                    try {
                        $this->friend_date = Vizualizer::now()->date("Y-m-d H:i:s");
                        $this->save();
                        Vizualizer_Logger::writeInfo("Followed to " . $this->user_id . " in " . $account->screen_name);
                        // エラーが無かった場合、処理をコミットする。
                        Vizualizer_Database_Factory::commit($connection);
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 指定のアンフォロー処理を実施する。
     */
    public function unfollow()
    {
        // トランザクションの開始
        $connection = Vizualizer_Database_Factory::begin("twitter");

        // アンフォローを実行するには、フォロー済みのレコードでないといけない。
        if($this->friend_date != null && $this->follow_date == null && $this->friend_cancel_date == null){
            $account = $this->account();

            // アンフォロー処理を実行する。
            $result = $account->getTwitter()->friendships_destroy(array("user_id" => $this->user_id));

            if (isset($result->errors)) {
                if ($result->errors[0]->code == "161" || $result->errors[0]->code == "162") {
                    $account->status()->updateFollow(3, Vizualizer::now()->strTotime("+1 hour")->date("Y-m-d 00:00:00"), true);
                } elseif ($result->errors[0]->code == "108" || $result->errors[0]->code == "34") {
                    try {
                        $this->delete();
                        // エラーが無かった場合、処理をコミットする。
                        Vizualizer_Database_Factory::commit($connection);
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                }
                Vizualizer_Logger::writeError("Failed to Unfollow on " . $follow->user_id . " in " . $this->screen_name . " by " . print_r($result->errors, true));
                return false;
            } else {
                try {
                    $this->friend_cancel_date = Vizualizer::now()->date("Y-m-d H:i:s");
                    $this->save();
                    Vizualizer_Logger::writeInfo("Unfollowed to " . $this->user_id . " in " . $account->screen_name);
                    // エラーが無かった場合、処理をコミットする。
                    Vizualizer_Database_Factory::commit($connection);
                } catch (Exception $e) {
                    Vizualizer_Database_Factory::rollback($connection);
                    throw new Vizualizer_Exception_Database($e);
                }
                return true;
            }
        }
        return false;
    }

}
