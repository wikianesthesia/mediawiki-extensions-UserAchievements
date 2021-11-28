<?php

namespace MediaWiki\Extension\UserAchievements\Api;

use ApiBase;
use MediaWiki\Extension\UserAchievements\UserAchievements;

class ApiUserAchievementsEditAchievement extends ApiUserAchievementsBasePost {
    public function __construct( $api, $modName ) {
        parent::__construct( $api, $modName, '' );
    }

    /**
     * @inheritDoc
     */
    public function execute() {
        $uaaction = $this->getAction();

        $output = [ $uaaction => [
            'result' => [],
            'status' => 'ok',
        ] ];

        if( !UserAchievements::userIsUserAchievementsAdmin() ) {
            $output[ $uaaction ][ 'status' ] = 'error';
            $output[ $uaaction ][ 'message' ] = wfMessage( 'userachievements-error-permissiondenied' );

            $this->getResult()->addValue( null, $this->apiUserAchievements->getModuleName(), $output );

            return;
        }

        $params = $this->extractRequestParams();

        $achievement = UserAchievements::getAchievement( $params[ 'achievement_id' ] );

        if( !$achievement ) {
            $output[ $uaaction ][ 'status' ] = 'error';
            $output[ $uaaction ][ 'message' ] = wfMessage( 'userachievements-error-invalidachievementid' );

            $this->getResult()->addValue( null, $this->apiUserAchievements->getModuleName(), $output );

            return;
        }

        $achievementData = [];

        // Params which are database fields
        $databaseParams = [
            'enabled'
        ];

        foreach( $params as $param => $value ) {
            if( in_array( $param, $databaseParams ) && $value !== null ) {
                $achievementData[ $param ] = $value;
            }
        }

        if( count( $achievementData ) ) {
            $db = UserAchievements::getDB();

            $db->upsert(
                'userachievements_achievements',
                array_merge( [
                    'achievement_id' => $achievement->getId()
                ], $achievementData ),
                [
                    [ 'achievement_id' ]
                ],
                $achievementData,
                __METHOD__
            );

            // TODO error handling
        }

        $this->getResult()->addValue( null, $this->apiUserAchievements->getModuleName(), $output );
    }

    /**
     * @inheritDoc
     */
    protected function getAction() {
        return 'editachievement';
    }

    public function getAllowedParams() {
        return [
            'achievement_id' => [
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'string'
            ],
            'enabled' => [
                ApiBase::PARAM_REQUIRED => false,
                ApiBase::PARAM_TYPE => 'integer'
            ]
        ];
    }
}