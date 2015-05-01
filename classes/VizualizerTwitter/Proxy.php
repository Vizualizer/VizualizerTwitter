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
        array("106.187.96.159", "8089"),
        array("43.251.16.103", "3128"),
        array("106.186.22.65", "8888"),
        array("157.7.48.92", "3128"),
        array("163.53.186.50", "8080"),
        array("114.33.172.80", "8088"),
        array("61.57.117.239", "8088"),
        array("111.185.126.102", "8888"),
        array("61.70.181.21", "8088"),
        array("118.163.235.36", "8088"),
        array("182.235.172.217", "8088"),
        array("123.110.163.116", "8088"),
        array("210.66.166.36", "8088"),
        array("210.65.10.76", "3128"),
        array("58.114.124.41", "8088"),
        array("60.250.81.118", "8080"),
        array("61.221.226.158", "8088"),
        array("122.116.163.113", "8080"),
        array("61.30.202.248", "8088"),
        array("61.219.70.133", "8080"),
        array("60.250.81.118", "80")
    );

    public static function getProxy(){
        $index = mt_rand(0, count(self::$proxys));
        if($index < count(self::$proxys)){
            return new VizualizerTwitter_ProxyData(self::$proxys[$index][0], self::$proxys[$index][1]);
        }
        return new VizualizerTwitter_ProxyData();
    }
}
