<?php

namespace MediaWiki\Extension\UserAchievements\Achievement;

use MediaWiki\Extension\UserAchievements\AbstractAchievement;
use MediaWiki\Extension\UserAchievements\AchievementTrait;
use MediaWiki\Extension\UserAchievements\UserAchievements;
use MediaWiki\Extension\UserAchievements\UserStats;
use MWTimestamp;
use User;

class MembershipYears extends AbstractAchievement {
    use AchievementTrait;

    protected static $installationTimestamp = 0;

    public function getLevels(): int {
        $installationTimestamp = new MWTimestamp( $this->getInstallationTimestamp() );

        return $installationTimestamp->diff( new MWTimestamp() )->y + 1;
    }

    protected function doTryAchieve( User $user ) {
        $registration = $user->getRegistration();

        if( !$registration ||
            ( $this->getConfig( 'RequireEmailConfirmation' ) && !$user->isEmailConfirmed() ) ) {
            return;
        }

        $registrationTimestamp = new MWTimestamp( $registration );
        $yearsSinceRegistration = $registrationTimestamp->diff( new MWTimestamp() )->y;

        $registrationUnixTimestamp = $registrationTimestamp->getTimestamp();

        for( $iYear = 1; $iYear <= $yearsSinceRegistration; $iYear++ ) {
            $achieveUnixTimestamp = strtotime( '+' . $iYear . ' year', $registrationUnixTimestamp );
            $achieveTime = MWTimestamp::convert( TS_MW, $achieveUnixTimestamp );

            $this->achieve( $user, $iYear, $achieveTime );
        }
    }


    protected function getInstallationTimestamp(): int {
        if( !static::$installationTimestamp ) {
            $db = UserAchievements::getDB();
            $res = $db->select(
                'user',
                'MIN(user_registration) as first_registration'
            );
            $row = $db->fetchObject( $res );

            $installationTimestamp = new MWTimestamp( $row->first_registration );
            static::$installationTimestamp = $installationTimestamp->getTimestamp();
        }

        return static::$installationTimestamp;
    }
}