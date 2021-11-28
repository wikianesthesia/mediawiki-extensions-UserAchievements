<?php

namespace MediaWiki\Extension\UserAchievements\Special;

use Html;
use MediaWiki\Extension\UserAchievements\AbstractAchievement;
use MediaWiki\Extension\UserAchievements\Badge;
use MediaWiki\Extension\UserAchievements\UserAchievements;
use SiteStats;
use SpecialPage;
use User;

class SpecialUserAchievements extends SpecialPage {

    public function __construct() {
        parent::__construct( 'UserAchievements' );
    }

    public function doesWrites() {
        return true;
    }

    public function execute( $subPage ) {
        $this->setHeaders();
        $this->outputHeader();

        $out = $this->getOutput();
        $req = $this->getRequest();

        $out->addModules( [ 'ext.userAchievements.special' ] );

        if( $req->getText( 'action' ) === 'admin' && UserAchievements::userIsUserAchievementsAdmin() ) {
            $this->showAdministration();
        }
        elseif( $req->getText( 'achievement' ) ) {
            $achievementId = $req->getText( 'achievement' );
            $level = $req->getIntOrNull( 'level' );

            $achievement = UserAchievements::getAchievement( $achievementId );

            if( !$achievement ) {
                $out->addHTML( wfMessage( 'userachievements-error-invalidachievementid' )->text() );
                return;
            }

            if( !$achievement->isEnabled() ) {
                $out->addHTML( wfMessage( 'userachievements-error-achievementdisabled' )->text() );
                return;
            }

            if( !$level ) {
                $this->showAchievementInfo( $achievement );
            } else {
                $badge = $achievement->getBadge( $level );

                if( !$badge) {
                    $out->addHTML( wfMessage( 'userachievements-error-invalidlevel' )->text() );
                    return;
                }

                $this->showBadgeInfo( $badge );
            }

        } else {
            if( UserAchievements::userIsUserAchievementsAdmin() ) {
                if( $req->getText( 'action' ) === 'admin' ) {
                    $this->showAchievementAdministration();

                    return;
                }

                $this->showAdminOptions();
            }

            $userName = $subPage ?? $req->getText( 'user' );

            if( $userName ) {
                $user = User::newFromName( $userName );

                if( !$user->isRegistered() ) {
                    $out->addHTML( wfMessage( 'userachievements-error-invaliduser' )->text() );
                    return;
                }
            } else {
                $user = $this->getUser();
            }

            $this->showAchievementList( $user );
        }
    }

    public function showAdministration() {
        $out = $this->getOutput();

        if( !UserAchievements::userIsUserAchievementsAdmin() ) {
            $out->addHTML( wfMessage( 'userachievements-error-permissiondenied' )->text() );
            return;
        }

        $out->addModules( [ 'ext.userAchievements.specialAdmin' ] );

        $templateParser = UserAchievements::getTemplateParser();

        $achievements = UserAchievements::getAchievements();

        $adminAchievementsHtml = '';

        foreach( $achievements as $achievement ) {
            $adminAchievementData = [
                'id' => $achievement->getId(),
                'name' => $achievement->getName(),
                'link' => $achievement->getLinkURL(),
                'enabled' => $achievement->isEnabled()
            ];

            $adminAchievementsHtml .= $templateParser->processTemplate( 'AdminAchievement', $adminAchievementData );
        }

        $adminData = [
            'adminachievements' => $adminAchievementsHtml
        ];

        $out->addHTML( $templateParser->processTemplate( 'Admin', $adminData ) );
    }

    public function showAchievementInfo( AbstractAchievement $achievement ) {
        $out = $this->getOutput();

        $out->setDisplayTitle( $achievement->getName() );

        $description = $achievement->getDescription();

        if( $description ) {
            $out->addHTML( $description );
        }

        foreach( $achievement->getBadges() as $badge ) {
            $this->showBadgeInfo( $badge, 10 );
        }
    }

    public function showAdminOptions() {
        $out = $this->getOutput();

        $adminOptionsHtml = '';

        // This should probably be in a template or something...
        $adminOptionsHtml .= Html::rawElement( 'a', [
            'class' => 'btn btn-primary',
            'href' => $this->getPageTitle()->getLinkURL() . '?action=admin',
            'role' => 'button',
            'style' => 'color: #fff;'
        ], wfMessage( 'userachievements-special-achievementadministration-button' )->text() );

        $adminOptionsHtml = Html::rawElement( 'div', [
            'class' => 'mb-2'
        ], $adminOptionsHtml );

        $out->addHTML( $adminOptionsHtml );
    }

    public function showBadgeInfo( Badge $badge, int $userLimit = 0 ) {
        $out = $this->getOutput();

        $templateParser = UserAchievements::getTemplateParser();

        $out->addHTMl( Html::rawElement( 'a', [ 'name' => $badge->getLevel() ] ) );
        $out->addHTML( Html::rawElement( 'h2', [], $badge->getName() ) );

        $requiredStats = $badge->getRequiredStats();
        $requiredStatsHtml = '';

        $achievedUserBadges = $badge->getAchievedUserBadges();

        foreach( $requiredStats as $stat => $requiredValue ) {
            $requiredStatsHtml .= Html::rawElement( 'tr', [],
                Html::rawElement( 'th', [], $badge->getAchievement()->getStatName( $stat ) ) .
                Html::rawElement( 'td', [], $requiredValue )
            );
        }

        $infoboxData = [
            'name' => $badge->getName(),
            'title' => $badge->getName(),
            'image' => $badge->getMediaFileRemotePath(),
            'requiredstats' => $requiredStatsHtml,
            'usercount' => count( $achievedUserBadges ) . ' (' .
                round( 100 * count( $achievedUserBadges ) / SiteStats::users(), 1 ) . '%)'
        ];

        $out->addHTML( $templateParser->processTemplate( 'BadgeInfobox', $infoboxData ) );

        $description = $badge->getDescription();

        if( $description ) {
            $out->addHTML( $description );
        }

        $out->addHTML( Html::rawElement( 'h3', [],
            wfMessage( 'userachievements-special-badgeinfo-userlist-heading' )->text() ) );

        if( !count( $achievedUserBadges ) ) {
            $out->addHTML( wfMessage( 'userachievements-special-badgeinfo-userlist-nousers' )->text() );
        } else {
            if( $userLimit && count( $achievedUserBadges ) > $userLimit ) {
                $out->addHTML(
                    wfMessage(
                        'userachievements-special-badgeinfo-userlist-limitnotice',
                        $userLimit,
                        Html::rawElement(
                            'a',
                            [ 'href' => $badge->getLinkURL() ],
                            wfMessage( 'userachievements-special-badgeinfo-userlist-showall' )->text()
                        )
                    )->text()
                );
            }

            $achievedUsersHtml = Html::rawElement( 'tr', [],
                Html::rawElement( 'th', [],
                    wfMessage( 'userachievements-special-badgeinfo-userlist-user' )->text() ) .
                Html::rawElement( 'th', [],
                    wfMessage( 'userachievements-special-badgeinfo-userlist-achievedon' )->text() )
            );

            $displayCount = 0;

            foreach( $achievedUserBadges as $userBadge ) {
                $userBadgeUser = $userBadge->getUser();

                if( !UserAchievements::isUserEligible( $userBadgeUser ) ) {
                    continue;
                }

                $achievedUsersHtml .= Html::rawElement( 'tr', [],
                    Html::rawElement( 'td', [], UserAchievements::getUserLink( $userBadgeUser ) ) .
                    Html::rawElement( 'td', [], $userBadge->getAchievedDateString( true ) )
                );

                $displayCount++;

                if( $displayCount === $userLimit ) {
                    break;
                }
            }

            $achievedUsersHtml = Html::rawElement( 'table', [
                'class' => 'wikitable'
            ], $achievedUsersHtml );

            $out->addHTML( $achievedUsersHtml );
        }
    }

    public function showAchievementList( User $user ) {
        $out = $this->getOutput();

        $templateParser = UserAchievements::getTemplateParser();

        $achievements = UserAchievements::getAchievements();
        
        usort( $achievements, function( AbstractAchievement $a, AbstractAchievement $b ) {
            if( $a->getPriority() !== $b->getPriority() ) {
                return $a->getPriority() > $b->getPriority() ? -1 : 1;
            }

            $anyAchievedA = false;
            $anyAchievedB = false;

            foreach( $a->getBadges() as $badge ) {
                if( UserAchievements::userHasAchieved( $a->getId(), $badge->getLevel() ) ) {
                    $anyAchievedA = true;
                    break;
                }
            }

            foreach( $b->getBadges() as $badge ) {
                if( UserAchievements::userHasAchieved( $b->getId(), $badge->getLevel() ) ) {
                    $anyAchievedB = true;
                    break;
                }
            }

            return $anyAchievedA === $anyAchievedB ? 0 : ( $anyAchievedA ? -1 : 1 );
        } );

        foreach( $achievements as $achievement ) {
            if( !$achievement->isEnabled() ) {
                continue;
            }

            $achievementData = [
                'id' => $achievement->getId(),
                'name' => $achievement->getName(),
                'description' => $achievement->getDescription(),
                'badges' => ''
            ];

            $anyBadgeAchieved = false;

            foreach( $achievement->getBadges() as $badge ) {
                if( UserAchievements::userHasAchieved( $achievement->getId(), $badge->getLevel(), $user ) ) {
                    $anyBadgeAchieved = true;
                } elseif( $badge->isSecret() ) {
                    // Do not show a secret badge if it has not been achieved
                    continue;
                }

                $achievementData[ 'badges' ] .= $badge->getFullHtml( $user );
            }

            if( $achievement->isSecret() && !$anyBadgeAchieved ) {
                // Do not show a secret achievement if no badges have been achieved
                continue;
            }

            $out->addHTML( $templateParser->processTemplate( 'AchievementSeries', $achievementData ) );
        }
    }
}