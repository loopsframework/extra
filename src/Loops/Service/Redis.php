<?php
/**
 * This file is part of the Loops framework.
 *
 * @author Lukas <lukas@loopsframework.com>
 * @license https://raw.githubusercontent.com/loopsframework/base/master/LICENSE
 * @link https://github.com/loopsframework/base
 * @link https://loopsframework.com/
 * @package extra
 * @version 0.1
 */

namespace Loops\Service;

use Loops;
use Loops\Misc;
use Loops\ArrayObject;
use Loops\Service;

class Redis extends Service {
    protected static $classname = "Redis";
    protected static $default_config = [ "host" => "localhost",
                                         "port" => 6379 ];
    
    public static function getDefaultConfig(Loops $loops = NULL) {
        $config = parent::getDefaultConfig($loops);
        
        if(getenv('REDIS_PORT') && preg_match('/^(.*?):\/\/(.*?):(.*?)$/', getenv('REDIS_PORT'), $match)) {
            $config->host = $match[2];
            $config->port = $match[3];
        }
        
        return $config;
    }
    
    public static function getService(ArrayObject $config, Loops $loops) {
        $redis = parent::getService($config, $loops);
        
        $params = static::getDefaultConfig($loops);
        $params->merge($config);
        $params = $params->toArray();

        //use closure to forward connect call - Redis objects can not be analyzed for some reason by the reflection api
        $connect = function($host = "localhost", $port = 6379, $timeout = 0, $persistent_id = NULL, $retry_interval = NULL, $persistent = FALSE) use ($redis) {
            if($persistent) {
                return $redis->pconnect($host, $port, $timeout, $persistent_id, $retry_interval);
            }
            else {
                return $redis->connect($host, $port, $timeout, $persistent_id, $retry_interval);
            }
        };
        
        Misc::reflectionFunction($connect, $params);
        
        if(!empty($params["password"])) {
            $redis->auth($params["password"]);
        }
        
        if(!empty($params["database"])) {
            $redis->select((int)$params["database"]);
        }
        
        return $redis;
    }
}