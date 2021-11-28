<?php

namespace MediaWiki\Extension\UserAchievements\Api;

use ApiBase;
use MediaWiki\Extension\UserAchievements\UserAchievements;

class ApiUserAchievementsPurgeUserBadges extends ApiUserAchievementsBasePost {
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

        $achievements = [];

        if( $params[ 'achievement_id' ] === '*' ) {
            $achievements = UserAchievements::getAchievements();
        } else {
            $achievement = UserAchievements::getAchievement( $params[ 'achievement_id' ] );

            if( !$achievement ) {
                $output[ $uaaction ][ 'status' ] = 'error';
                $output[ $uaaction ][ 'message' ] = wfMessage( 'userachievements-error-invalidachievementid' );

                $this->getResult()->addValue( null, $this->apiUserAchievements->getModuleName(), $output );

                return;
            }

            $achievements[] = $achievement;
        }

        foreach( $achievements as $achievement ) {
            if( !$achievement->purgeUserBadges() ) {
                $output[ $uaaction ][ 'status' ] = 'error';
            }
        }

        $this->getResult()->addValue( null, $this->apiUserAchievements->getModuleName(), $output );
    }

    /**
     * @inheritDoc
     */
    protected function getAction() {
        return 'purgeuserbadges';
    }

    public function getAllowedParams() {
        return [
            'achievement_id' => [
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'string'
            ]
        ];
    }
}