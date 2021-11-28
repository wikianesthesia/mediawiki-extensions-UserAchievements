<?php

namespace MediaWiki\Extension\UserAchievements;

use RequestContext;
use User;

class UserBadge extends Badge {
    protected $userId = 0;
    protected $achievedTime = 0;
    protected $awardedByUserId = 0;
    protected $notified = false;

    public function __construct( array $badgeDefinition = [], array $userBadgeData = [] ) {
        parent::__construct( $badgeDefinition );

        $this->userId = $userBadgeData[ 'user_id' ] ?? $this->userId;
        $this->achievedTime = $userBadgeData[ 'achieved_time' ] ?? $this->achievedTime;
        $this->awardedByUserId = $userBadgeData[ 'awarded_by_user_id' ] ?? $this->awardedByUserId;
        $this->notified = $userBadgeData[ 'notified' ] ?? $this->notified;
    }

    public function getAchievedTime(): int {
        return $this->achievedTime;
    }

    public function getAchievedDateString( $showTime = false ): string {
        $lang = RequestContext::getMain()->getLanguage();

        return $showTime ?
            $lang->userTimeAndDate( $this->getAchievedTime(), RequestContext::getMain()->getUser() ) :
            $lang->userDate( $this->getAchievedTime(), RequestContext::getMain()->getUser() );
    }

    public function getAwardedByUser(): User {
        return User::newFromId( $this->awardedByUserId );
    }

    public function getUser(): User {
        return User::newFromId( $this->userId );
    }

    public function isAchieved(): bool {
        return $this->achievedTime > 0;
    }

    public function userNotified(): bool {
        return $this->notified;
    }

    public static function getForUser( User $user ): array {
        $userBadges = [];

        if( !$user->isRegistered() ) {
            return $userBadges;
        }

        UserAchievements::tryAchieveAll( $user );

        $db = UserAchievements::getDB();

        $res = $db->select(
            'userachievements_userbadges',
            '*',
            [
                'user_id' => $user->getId()
            ],
            __METHOD__
        );

        while( $row = $res->fetchRow() ) {
            $achievement = UserAchievements::getAchievement( $row[ 'achievement_id' ] );

            if( !$achievement ) {
                continue;
            }

            $badge = $achievement->getBadge( $row[ 'level' ] );

            $userBadges[] = new UserBadge( $badge->getDefinition(), $row );
        }

        usort( $userBadges, function( UserBadge $a, UserBadge $b ) {
            // Sort order is priority descending, achievement name ascending, level ascending
            $aAchievement = $a->getAchievement();
            $bAchievement = $b->getAchievement();

            $aPriority = $aAchievement->getPriority();
            $bPriority = $bAchievement->getPriority();

            if( $aPriority === $bPriority ) {
                $aName = $aAchievement->getName();
                $bName = $bAchievement->getName();

                if( $aName === $bName ) {
                    return ( $a->getLevel() < $b->getLevel() ) ? -1 : 1;
                } else {
                    return ( $aName < $bName ) ? -1 : 1;
                }
            } else {
                return ( $aPriority > $bPriority ) ? -1 : 1;
            }
        } );

        return $userBadges;
    }
}