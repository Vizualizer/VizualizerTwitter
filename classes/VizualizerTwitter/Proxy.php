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
        array("180.19.232.29", "80"),
        array("106.187.96.159", "8089"),
        array("114.141.47.173", "80"),
        array("125.143.136.21", "8080")
    );

    public static function getProxy(){
        $index = mt_rand(0, count(self::$proxys));
        if($index < count(self::$proxys)){
            return new VizualizerTwitter_ProxyData(self::$proxys[$index][0], self::$proxys[$index][1]);
        }
        return new VizualizerTwitter_ProxyData();
    }
}
