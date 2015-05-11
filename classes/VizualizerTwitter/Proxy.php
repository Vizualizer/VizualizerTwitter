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
 * プロキシ用のデータです。
 */
class VizualizerTwitter_ProxyData{
    private $host;

    private $port;

    public function __construct($host = null, $port = null){
        $this->host = $host;
        $this->port = $port;
    }

    public function getHost(){
        return $this->host;
    }

    public function getPort(){
        return $this->port;
    }
}

/**
 * Twitter接続のプロキシ用のモデルです。
 *
 * @package VizualizerTwitter
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerTwitter_Proxy{
    private static $proxys = array(
        array("192.168.122.96", "8888"),
        array("192.168.151.96", "8888"),
        array("219.94.254.202", "31280")
    );

    public static function getProxy(){
        $proxys = array();
        if($_SERVER["SERVER_NAME"] == "twt-system.com"){
            $proxys[] = array("192.168.122.96", "8888");
        }
        if($_SERVER["SERVER_NAME"] == "medinethub.com"){
            $proxys[] = array("192.168.151.96", "8888");
        }
        $proxys[] = array("219.94.254.202", "31280");
        $index = mt_rand(0, count($proxys));
        if($index < count($proxys)){
            return new VizualizerTwitter_ProxyData($proxys[$index][0], $proxys[$index][1]);
        }
        return new VizualizerTwitter_ProxyData();
    }
}
