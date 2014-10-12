<?php

class VizualizerTwitter_Json_SettingText
{

    public function execute()
    {
        $post = Vizualizer::request();

        // 商品プラグインの初期化
        $loader = new Vizualizer_Plugin("twitter");
        $setting = $loader->loadModel("Setting");

        if(class_exists("VizualizerAdmin")){
            $operator = Vizualizer_Session::get(VizualizerAdmin::SESSION_KEY);
            if($operator["operator_id"] > 0){
                // ターゲットパラメータが指定されている場合は、データを書き換える
                if (!empty($post["target"])) {
                    if (preg_match("/^([a-zA-Z0-9_]+)_([0-9]+)$/", $post["target"], $p) > 0) {
                        $post->set("account_id", $p[2]);
                        $setting->findBy(array("operator_id" => $operator["operator_id"], "account_id" => $post["account_id"]));
                        $attribute = $p[1];
                        if($post["type"] == "add"){
                            $attributes = explode("\r\n", $setting->$attribute);
                            if(!empty($post["value"])){
                                $attributes[] = str_replace("　", " ", $post["value"]);
                            }
                            $setting->$attribute = str_replace(" ", "\r\n", implode("\r\n", $attributes));
                        }elseif($post["type"] == "delete"){
                            $attributes = explode("\r\n", str_replace(" ", "\r\n", $setting->$attribute));
                            foreach($attributes as $index => $attr){
                                if($attr == $post["value"]){
                                    unset($attributes[$index]);
                                }
                            }
                            $setting->$attribute = implode("\r\n", $attributes);
                        }else{
                            $setting->$attribute = $post["value"];
                        }

                        // トランザクションの開始
                        $connection = Vizualizer_Database_Factory::begin("twitter");

                        try {

                            if ($attribute == "follow_target") {
                                $status = $setting->account->status();
                                if($setting->$attribute > 0){
                                    if(!($status->follow_status > 0)){
                                        $status->follow_status = 1;
                                        $status->save();
                                    }
                                }else{
                                    if($status->follow_status > 0){
                                        $status->follow_status = 0;
                                        $status->save();
                                    }
                                }
                            }

                            $setting->save();

                            // エラーが無かった場合、処理をコミットする。
                            Vizualizer_Database_Factory::commit($connection);
                        } catch (Exception $e) {
                            Vizualizer_Database_Factory::rollback($connection);
                            throw new Vizualizer_Exception_Database($e);
                        }
                    }
                    $post->remove("target");
                }

                $settings = $setting->findAllByOperatorId($operator["operator_id"]);
                $attributes = array();
                foreach($settings as $s){
                    if(!empty($s->account_attribute)){
                        $attributes[$s->account_attribute] = $s->account_attribute;
                    }
                }

                $setting->findBy(array("operator_id" => $operator["operator_id"], "account_id" => $post["account_id"]));
                $setting->attributes = $attributes;

                $keywords = array();
                foreach(explode("\r\n", str_replace(" ", "\r\n", $setting->follow_keywords)) as $keyword){
                    if(!empty($keyword)){
                        $keywords[] = $keyword;
                    }
                }
                $setting->follow_keywords = $keywords;
                $keywords = array();
                foreach(explode("\r\n", str_replace(" ", "\r\n", $setting->follower_keywords)) as $keyword){
                    if(!empty($keyword)){
                        $keywords[] = $keyword;
                    }
                }
                $setting->follower_keywords = $keywords;
                $keywords = array();
                foreach(explode("\r\n", str_replace(" ", "\r\n", $setting->ignore_keywords)) as $keyword){
                    if(!empty($keyword)){
                        $keywords[] = $keyword;
                    }
                }
                $setting->ignore_keywords = $keywords;

                return $setting->toArray();
            }
        }
    }
}
