<?php
/**
 * Created by PhpStorm.
 * User: Exodus
 * Date: 28.05.2016
 * Time: 16:05
 */

namespace lib;


class Config extends \Prefab {

    const PREFIX_KEY                                = 'PF';
    const ARRAY_DELIMITER                           = '-';
    const HIVE_KEY_ENVIRONMENT                      = 'ENVIRONMENT';

    /**
     * all environment data
     * @var array
     */
    private $serverConfigData                       = [];

    public function __construct(){
        // set server data
        // -> CGI params (Nginx)
        // -> .htaccess (Apache)
        $this->setServerData();
        // set environment data
        $this->setAllEnvironmentData();
        // set hive configuration variables
        // -> overwrites default configuration
        $this->setHiveVariables();
    }

    /**
     * get environment configuration data
     * @return array|null
     */
    protected function getAllEnvironmentData(){
        $f3 = \Base::instance();
        $environmentData = null;

        if( $f3->exists(self::HIVE_KEY_ENVIRONMENT) ){
            $environmentData = $f3->get(self::HIVE_KEY_ENVIRONMENT);
        }else{
            $environmentData =  $this->setAllEnvironmentData();
        }
        return $environmentData;
    }

    /**
     * set some global framework variables
     * that depend on environment settings
     */
    protected function setHiveVariables(){
        $f3 = \Base::instance();

        // hive  keys that should be overwritten by environment config
        $hiveKeys = ['BASE', 'URL', 'DEBUG'];
        foreach($hiveKeys as $key){
            $f3->set($key, self::getEnvironmentData($key));
        }
    }

    /**
     * set all environment configuration data
     * @return array|null
     */
    protected function setAllEnvironmentData(){
        $environmentData = null;
        $f3 = \Base::instance();

        if( !empty($this->serverConfigData['ENV']) ){
            // get environment config from $_SERVER data
            $environmentData = (array)$this->serverConfigData['ENV'];
            $environmentData['TYPE'] = 'PHP: environment variables';
        }else{
            // get environment data from *.ini file config
            $f3->config('app/environment.ini');

            if(
                $f3->exists(self::HIVE_KEY_ENVIRONMENT) &&
                ($environment = $f3->get(self::HIVE_KEY_ENVIRONMENT . '.SERVER')) &&
                ($environmentData = $f3->get(self::HIVE_KEY_ENVIRONMENT . '.' . $environment))
            ){
                $environmentData['TYPE'] = 'Config: environment.ini';
            }
        }

        if( !is_null($environmentData) ){
            ksort($environmentData);
            $f3->set(self::HIVE_KEY_ENVIRONMENT, $environmentData);
        }

        return $environmentData;
    }

    /**
     * get/extract all server data passed to PHP
     * this can be done by either:
     * OS Environment variables:
     *  -> add to /etc/environment
     * OR:
     * Nginx (server config):
     * -> FastCGI syntax
     *      fastcgi_param PF-ENV-DEBUG 3;
     *
     * @return array
     */
    protected function setServerData(){
        $data = [];
        foreach($_SERVER as $key => $value){
            if( strpos($key, self::PREFIX_KEY . self::ARRAY_DELIMITER) === 0 ){
                $path = explode( self::ARRAY_DELIMITER, $key);
                // remove prefix
                array_shift($path);

                $tmp = &$data;
                foreach ($path as $segment) {
                    $tmp[$segment] = (array)$tmp[$segment];
                    $tmp = &$tmp[$segment];
                }

                // type cast values
                // (e.g. '1.2' => (float); '4' => (int),...)
                $tmp = is_numeric($value) ? $value + 0 : $value;
            }
        }

        $this->serverConfigData = $data;
    }

    /**
     * get a environment variable by hive key
     * @param $key
     * @return string|null
     */
    static function getEnvironmentData($key){
        $f3 = \Base::instance();
        $hiveKey = self::HIVE_KEY_ENVIRONMENT . '.' . $key;
        $data = null;
        if( $f3->exists($hiveKey) ){
            $data = $f3->get($hiveKey);
        }

        return $data;
    }

}