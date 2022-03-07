<?php

namespace MediaWiki\Extension\UserAchievements\Achievement;

use GuzzleHttp\Stream\InflateStream;
use MediaWiki\Extension\UserAchievements\AbstractEditsAchievement;
use MediaWiki\Extension\UserAchievements\AchievementTrait;
use MediaWiki\Extension\UserAchievements\UserAchievements;
use MWTimestamp;
use User;

/**
 * While this is not a typical edits-based achievement, for the contributor level of the achievement we must identify
 * if the user made an edit within the inaugural time period. User::getFirstEditTimestamp() is not adaptable enough for
 * this task (cannot filter based upon namespace, deleted, or null revisions). Thus, we will use
 * AbstractEditsAchievement::getEditsQueryInfo() to generate the query and then modify it to suit this use case.
 * In addition we will also overload doTryAchieve() since this achievement doesn't actually use tracked stats.
 */
class InauguralMember extends AbstractEditsAchievement {
    use AchievementTrait;

    protected static $installationTimestamp = 0;

    protected function doTryAchieve( User $user ): void {
        $membershipTime = $user->getRegistration();

        if( $membershipTime ) {
            // The email authentication timestamp will change anytime the user changes (and confirms) their email.
            // Thus it is important to not compare this to the epoch period, but rather just check to make sure
            // the email address is confirmed at all.
            if( $this->getConfig( 'RequireEmailConfirmation' ) && !$user->getEmailAuthenticationTimestamp() ) {
                return;
            }

            $epochStart = $this->getConfig( 'EpochStart' );
            $epochLength = $this->getConfig( 'EpochLength' );

            $epochStartTimestamp = $epochStart ? strtotime( $epochStart ) : static::getInstallationTimestamp();
            $epochEndTimestamp = strtotime( '+' . $epochLength, $epochStartTimestamp );

            $membershipTimestamp = ( new MWTimestamp( $membershipTime ) )->getTimestamp();

            if( $membershipTimestamp <= $epochEndTimestamp ) {
                // Level 1 is inaugural member
                $this->achieve( $user, 1, $membershipTime );

                // User::getFirstEditTimestamp() does not have any consideration for which namespace the edit was in.
                // This may not be ideal if an extension like CreateUserPage is being used (and thus a "first edit"
                // of their user page will automatically occur when the user registers). getFirstEditTimestamp()
                // also does not support adjustments for deleted and null revisions.
                $db = UserAchievements::getDB();

                $editsQueryInfo = $this->getEditsQueryInfo( $user );

                $firstEditTime = INF;

                // Search all possible queries to find the earliest edit time
                foreach( $editsQueryInfo as $queryInfo ) {
                    $res = $db->select(
                        $queryInfo[ 'tables' ],
                        $queryInfo[ 'vars' ],
                        $queryInfo[ 'conds' ],
                        __METHOD__,
                        $queryInfo[ 'options' ],
                        $queryInfo[ 'join_conds' ]
                    );

                    if( $res->numRows() ) {
                        // Limited to one row representing the earliest edit in the query
                        $row = $res->fetchRow();

                        $firstEditTime = min( $firstEditTime, $row[ $queryInfo[ 'timestamp_var' ] ] );
                    }
                }

                if( $firstEditTime !== INF ) {
                    $firstEditTimestamp = ( new MWTimestamp( $firstEditTime ) )->getTimestamp();

                    if( $firstEditTimestamp <= $epochEndTimestamp ) {
                        // Level 2 is inaugural contributor
                        $this->achieve( $user, 2, $firstEditTime );
                    }
                }
            }
        }
    }

    protected function getEditsQueryInfo( User $user ): array {
        $editsQueryInfo = parent::getEditsQueryInfo( $user );

        // We only want the first result
        foreach( $editsQueryInfo as $queryId => $QueryInfo ) {
            $editsQueryInfo[ $queryId ][ 'options' ][ 'LIMIT' ] = 1;
        }

        return $editsQueryInfo;
    }

    protected function getEditsStat(): string {
        return '';
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