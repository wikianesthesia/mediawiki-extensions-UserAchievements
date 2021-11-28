<?php

namespace MediaWiki\Extension\UserAchievements\Api;

use ApiBase;

abstract class ApiUserAchievementsBase extends ApiBase {

    /**
     * @var ApiUserAchievements
     */
    protected $apiUserAchievements;

    /**
     * @param ApiUserAchievements $api
     * @param string $modName
     * @param string $prefix
     */
    public function __construct( ApiUserAchievements $api, string $modName, string $prefix = '' ) {
        $this->apiUserAchievements = $api;

        parent::__construct( $api->getMain(), $modName, $prefix );
    }

    /**
     * Return the name of the practice groups action
     * @return string
     */
    abstract protected function getAction();

    /**
     * @inheritDoc
     */
    public function getParent() {
        return $this->apiUserAchievements;
    }

    public function simplifyError( string $error ) {
        return explode(':', $error )[ 0 ];
    }
}