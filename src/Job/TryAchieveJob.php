<?php

use MediaWiki\Extension\UserAchievements\UserAchievements;
use User;

class TryAchieveJob extends Job {
    public function __construct( string $achievementId, int $userId ) {
        parent::__construct( 'userAchievementsTryAchieve', [
            'achievementId' => $achievementId,
            'userId' => $userId
        ] );
    }

    /**
     * @inheritDoc
     */
    public function run() {
        $achievement = UserAchievements::getAchievement( $this->params[ 'achievementId' ] );
        $user = User::newFromId( $this->params[ 'achievementId' ] );

        if( $achievement && $user->isRegistered() ) {
            $achievement->tryAchieve( $user );
        }
    }
}