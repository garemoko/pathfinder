<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 26.02.15
 * Time: 21:12
 */

namespace Model;

use Controller\Api\Route;
use DB\SQL\Schema;

class ConnectionModel extends BasicModel{

    protected $table = 'connection';

    protected $fieldConf = [
        'active' => [
            'type' => Schema::DT_BOOL,
            'nullable' => false,
            'default' => 1,
            'index' => true
        ],
        'mapId' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => 'Model\MapModel',
            'constraint' => [
                [
                    'table' => 'map',
                    'on-delete' => 'CASCADE'
                ]
            ]
        ],
        'source' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => 'Model\SystemModel',
            'constraint' => [
                [
                    'table' => 'system',
                    'on-delete' => 'CASCADE'
                ]
            ]
        ],
        'target' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => 'Model\SystemModel',
            'constraint' => [
                [
                    'table' => 'system',
                    'on-delete' => 'CASCADE'
                ]
            ]
        ],
        'scope' => [
            'type' => Schema::DT_VARCHAR128,
            'nullable' => false,
            'default' => ''
        ],
        'type' => [
            'type' => self::DT_JSON
        ]
    ];

    /**
     * set an array with all data for a system
     * @param $systemData
     */
    public function setData($systemData){

        foreach((array)$systemData as $key => $value){

            if( !is_array($value) ){
                if( $this->exists($key) ){
                    $this->$key = $value;
                }
            }elseif($key == 'type'){
                // json field
                $this->$key = $value;
            }
        }
    }

    /**
     * get connection data as array
     * @return array
     */
    public function getData(){

        $connectionData = [
            'id' => $this->id,
            'source' => $this->source->id,
            'target' => $this->target->id,
            'scope' => $this->scope,
            'type' => $this->type,
            'updated' => strtotime($this->updated)
        ];

        return $connectionData;
    }

    /**
     * check object for model access
     * @param CharacterModel $characterModel
     * @return mixed
     */
    public function hasAccess(CharacterModel $characterModel){
        return $this->mapId->hasAccess($characterModel);
    }

    /**
     * set default connection type by search route between endpoints
     */
    public function setDefaultTypeData(){
        if(
            is_object($this->source) &&
            is_object($this->target)
        ){
            $routeController = new Route();
            $routeController->initJumpData();
            $route = $routeController->findRoute($this->source->name, $this->target->name, 1);

            if($route['routePossible']){
                $this->scope = 'stargate';
                $this->type = ['stargate'];
            }else{
                $this->scope = 'wh';
                $this->type = ['wh_fresh'];
            }
        }
    }

    /**
     * check whether this connection is a wormhole or not
     * @return bool
     */
    public function isWormhole(){
        return ($this->scope === 'wh');
    }

    /**
     * check whether this model is valid or not
     * @return bool
     */
    public function isValid(){
        $isValid = true;

        // check if source/target system are not equal
        // check if source/target belong to same map
        if(
            is_object($this->source) &&
            is_object($this->target) &&
            $this->source->_id === $this->target->_id ||
            $this->source->mapId->_id !== $this->target->mapId->_id
        ){
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * Event "Hook" function
     * can be overwritten
     * return false will stop any further action
     */
    public function beforeInsertEvent(){
        // check for "default" connection type and add them if missing
        if(
            !$this->scope ||
            !$this->type
        ){
            $this->setDefaultTypeData();
        }

        return true;
    }

    /**
     * save connection and check if obj is valid
     * @return ConnectionModel|false
     */
    public function save(){
        return ( $this->isValid() ) ? parent::save() : false;
    }

    /**
     * delete a connection
     * @param CharacterModel $characterModel
     */
    public function delete(CharacterModel $characterModel){
        if( !$this->dry() ){
            // check if character has access
            if($this->hasAccess($characterModel)){
                $this->erase();
            }
        }
    }

    /**
     * see parent
     */
    public function clearCacheData(){
        parent::clearCacheData();

        // clear map cache as well
        $this->mapId->clearCacheData();
    }

    /**
     * overwrites parent
     * @param null $db
     * @param null $table
     * @param null $fields
     * @return bool
     */
    public static function setup($db=null, $table=null, $fields=null){
        $status = parent::setup($db,$table,$fields);

        if($status === true){
            $status = parent::setMultiColumnIndex(['source', 'target', 'scope']);
        }

        return $status;
    }
} 