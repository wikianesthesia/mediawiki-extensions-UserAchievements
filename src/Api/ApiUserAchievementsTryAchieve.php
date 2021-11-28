<?php

// TODO extends ApiUserAchievementsBasePost

namespace MediaWiki\Extension\UserAchievements\Api;

use ApiBase;
use MediaWiki\Extension\UserAchievements\UserAchievements;
use RequestContext;
use User;

class ApiUserAchievementsTryAchieve extends ApiUserAchievementsBase {
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

        $user = RequestContext::getMain()->getUser();

        if( !$user->isRegistered() ) {
            $output[ $uaaction ][ 'status' ] = 'error';
            $output[ $uaaction ][ 'message' ] = wfMessage( 'userachievements-error-usernotloggedin' );

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

        $achievement->tryAchieve( $user );

        // TODO result/status/error handling

        $this->getResult()->addValue( null, $this->apiUserAchievements->getModuleName(), $output );
    }

    /**
     * @inheritDoc
     */
    protected function getAction() {
        return 'tryachieve';
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