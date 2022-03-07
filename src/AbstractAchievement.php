<?php

namespace MediaWiki\Extension\UserAchievements;

use MediaWiki\Extension\JsonClasses\AbstractJsonClass;
use MediaWiki\MediaWikiServices;
use MWException;
use MWTimestamp;
use ReflectionClass;
use RequestContext;
use Title;
use User;

abstract class AbstractAchievement extends AbstractJsonClass {
    /**
     * @var string
     */
    protected $id = '';

    /**
     * @var bool
     */
    protected $enabled = false;

    /**
     * @var string
     */
    protected $name = null;

    /**
     * @var string
     */
    protected $description = null;

    /**
     * @var Badge[]
     */
    protected $badges = [];


    public function __construct( array $definition ) {
        $this->loadDBConfig();

        parent::__construct( $definition );
    }

    /**
     * @return Badge|false
     */
    public function getBadge( int $level = 1 ) {
        return count( $this->badges ) >= $level ? $this->badges[ $level - 1 ] : false;
    }

    /**
     * @return Badge[]
     */
    public function getBadges(): array {
        return $this->badges;
    }

    /**
     * @return string
     */
    public function getColor(): string {
        global $wgUserAchievementsDefaultAchievedColor;

        return $this->getDefinition( 'color' ) ?: $wgUserAchievementsDefaultAchievedColor;
    }

    /**
     * @return string
     */
    public function getId(): string {
        if( !$this->id ) {
            $this->id = $this->getClassString( static::class, false );
        }

        return $this->id;
    }

    /**
     * @return integer
     */
    public function getLevels(): int {
        return $this->getDefinition( 'levels' ) ?: 0;
    }

    public function getLinkURL( int $level = 0 ): string {
        $title = Title::newFromText( 'UserAchievements', NS_SPECIAL );

        if( $level ) {
            $title->setFragment( '#' . $level );
        }

        return $title->getLinkURL( [
            'achievement' => $this->getId()
        ] );
    }

    /**
     * @return string
     */
    public function getMsgKeyPrefix(): string {
        return strtolower( UserAchievements::getExtensionName() . '-' . $this->getId() );
    }

    /**
     * @return integer
     */
    public function getPriority(): int {
        return $this->getDefinition( 'priority' );
    }

    /**
     * @param string $stat
     * @return string
     */
    public function getStatName( string $stat ): string {
        $statMsg = wfMessage( $this->getMsgKeyPrefix() . '-stat-' . strtolower( $stat ) . '-name' );
        return $statMsg->exists() ? $statMsg->text() : $stat;
    }

    public function getStats(): array{
        return $this->getDefinition( 'stats' );
    }

    /**
     * @param User|null $user
     * @return UserStats
     */
    public function getUserStats( User $user = null ): UserStats {
        $user = $user ?: RequestContext::getMain()->getUser()->getId();

        if( !$user->isRegistered() ) {
            return new UserStats( $this->getStats() );
        }

        if( isset( static::$userStats[ $user->getId() ] ) ) {
            if( static::$userStats !== null ) {
                return static::$userStats[ $user->getId() ];
            }
        }

        $stats = $this->getStats();
        $userStats = new UserStats( $stats, $user );

        $this->doGetUserStats( $userStats );

        foreach( $stats as $stat => $defaultValue ) {
            if( !$userStats->getValue( $stat ) ) {
                $events = $userStats->getEventTimes( $stat );

                if( $events ) {
                    $userStats->setValue( $stat, count( $events ) );
                }
            }
        }

        static::$userStats[ $user->getId() ] = $userStats;

        return static::$userStats[ $user->getId() ];
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }

    /**
     * @return bool
     */
    public function isSecret(): bool {
        return $this->getDefinition( 'secret' );
    }

    /**
     * This should only be called from a posted API request
     */
    public function purgeUserBadges(): bool {
        if( !UserAchievements::userIsUserAchievementsAdmin() ) {
            return false;
        }

        $db = UserAchievements::getDB();

        $db->delete(
            'userachievements_userbadges',
            [
                'achievement_id' => $this->getId()
            ],
            __METHOD__
        );

        // TODO validation

        return true;
    }

    /**
     * This should only be called from a posted API request
     */
    public function rebuildAchievement(): bool {
        if( !UserAchievements::userIsUserAchievementsAdmin() ) {
            return false;
        }

        if( !$this->isEnabled() ) {
            return false;
        }

        $db = UserAchievements::getDB();

        $allUsersQueryInfo = $this->getAllUsersQueryInfo();

        $res = $db->select(
            $allUsersQueryInfo[ 'tables' ],
            $allUsersQueryInfo[ 'vars' ],
            $allUsersQueryInfo[ 'conds' ],
            __METHOD__,
            $allUsersQueryInfo[ 'options' ],
            $allUsersQueryInfo[ 'join_conds' ]

        );

        while( $row = $res->fetchRow() ) {
            $this->tryAchieve( User::newFromId( $row[ 'user_id' ] ) );
        }

        return true;
    }

    /**
     * This should only be called from a posted API request
     *
     * @param User $user
     */
    public function tryAchieve( User $user ) {
        if( !$this->isEnabled() || !UserAchievements::isUserEligible( $user ) ) {
            return;
        }

        UserAchievements::getLogger()->info(
            wfMessage( 'userachievements-log-tryachieve')->plain(), [
            'user' => $user->getName(),
            'achievement' => $this->getName()
        ] );

        $this->doTryAchieve( $user );
    }

    /**
     * @param User $user
     * @param int $level
     * @return bool
     */
    public function userHasAchieved( User $user, int $level = 1 ): bool {
        if( !$user->isRegistered() ) {
            return false;
        }

        $db = UserAchievements::getDB();

        $res = $db->select(
            'userachievements_userbadges',
            'achieved_time',
            [
                'user_id' => $user->getId(),
                'achievement_id' => $this->getId(),
                'level' => $level
            ],
            __METHOD__
        );

        if( $row = $res->fetchRow() ) {
            if( $row[ 'achieved_time' ] ) {
                return true;
            }
        }

        return false;
    }

    public function userMaxAchievedLevel( User $user ): int {
        $maxAchievedLevel = 0;

        if( !$user->isRegistered() ) {
            return false;
        }

        $db = UserAchievements::getDB();

        // TODO theoretically achieved_time could be 0?
        $res = $db->select(
            'userachievements_userbadges',
            'level',
            [
                'user_id' => $user->getId(),
                'achievement_id' => $this->getId(),
            ],
            __METHOD__,
            [
                'ORDER BY' => 'level DESC',
                'LIMIT' => 1
            ]
        );

        if( $row = $res->fetchRow() ) {
            $maxAchievedLevel = $row[ 'level' ];
        }

        return $maxAchievedLevel;
    }

    /**
     * This should only be called from a posted API request
     *
     * @param User $user
     * @param int $level
     * @param string $time
     * @param User|null $awardedByUser
     * @return bool
     */
    protected function achieve( User $user, int $level, string $time = '', User $awardedByUser = null ): bool {
        if( !UserAchievements::isUserEligible( $user ) ) {
            return false;
        }

        $requestUser = RequestContext::getMain()->getUser();

        if( $requestUser->getId() !== $user->getId() && !UserAchievements::userIsUserAchievementsAdmin() ) {
            return false;
        }

        $badge = $this->getBadge( $level );

        if( !$badge ) {
            return false;
        }

        if( $this->userHasAchieved( $user, $level ) ) {
            return true;
        }

        $uniqueKeys = [
            'user_id' => $user->getId(),
            'achievement_id' => $this->getId(),
            'level' => $level
        ];

        $userBadgeData = [
            'achieved_time' => $time ?: MWTimestamp::now()
        ];

        if( $awardedByUser && UserAchievements::userIsUserAchievementsAdmin( $awardedByUser ) ) {
            $userBadgeData[ 'awarded_by_user_id' ] = $awardedByUser->getId();
        }

        $db = UserAchievements::getDB();

        $db->upsert(
            'userachievements_userbadges',
            array_merge( $uniqueKeys, $userBadgeData ),
            [
                array_keys( $uniqueKeys )
            ],
            $userBadgeData,
            __METHOD__
        );

        $success = $db->affectedRows() > 0;

        if( $success ) {
            UserAchievements::getLogger()->info(
                wfMessage( 'userachievements-log-badgeachieved')->plain(), [
                    'user' => $user->getName(),
                    'badge' => $badge->getName()
            ] );
        }

        // TODO figure out validation.
        // affectedRows() sometimes returns 1 and sometimes returns 2 even though only one row is actually changed.
        return $db->affectedRows() > 0;
    }

    /**
     * Default tryAchieve function. Awards badges based upon required stats defined in achievement.json
     * Subclasses should override this function if they do not wish to use default user stats behavior.
     * @param User $user
     */
    protected function doTryAchieve( User $user ): void {
        $userStats = $this->getUserStats( $user );

        // Start with the next level the user hasn't achieved
        for( $level = $this->userMaxAchievedLevel( $user ) + 1; $level <= $this->getLevels(); $level++ ) {
            $badge = $this->getBadge( $level );

            if( !$badge ) {
                // TODO error handling
                continue;
            }

            $requiredStats = $badge->getRequiredStats();

            // If required stats are not defined, this achievement cannot be automatically achieved from stats data
            // Example would be an achievement awarded manually
            if( !count( $requiredStats ) ) {
                continue;
            }

            // Record the times when each required stat was satisfied. Even if the time is not known,
            // the array will still be populated with false for each stat. The achievement should be awarded
            // if this array contains the same number of values as required stats (since they will only be defined if
            // the threshold is met).
            $thresholdsMetTimes = [];

            foreach( $requiredStats as $stat => $threshold ) {
                $statValue = $userStats->getValue( $stat );

                if( $statValue >= $threshold ) {
                    $thresholdsMetTimes[ $stat ] = $userStats->getEventTime( $stat, $threshold );
                }
            }

            if( count( $thresholdsMetTimes ) === count( $requiredStats ) ) {
                // Set the time to the latest time in the thresholds met time array (i.e. the time when all required
                // stats were met).
                $this->achieve( $user, $level, (string) max( $thresholdsMetTimes ) );
            } else {
                // If the user hasn't met the criteria to achieve this level, there is no reason to check higher levels.
                break;
            }
        }
    }

    /**
     * Subclasses should override this function if they will award achievements automatically based upon stats thresholds
     * @param UserStats $userStats
     */
    protected function doGetUserStats( UserStats &$userStats ) {}

    /**
     * @return array
     */
    protected function getAllUsersQueryInfo(): array {
        return [
            'tables' => 'user',
            'vars' => 'user_id',
            'conds' => '',
            'options' => [],
            'join_conds' => []
        ];
    }

    protected function getSchemaClass(): string {
        return AchievementSchema::class;
    }

    /**
     * @return bool
     */
    protected function loadDBConfig(): bool {
        $db = UserAchievements::getDB();

        if( !$db->tableExists( 'userachievements_achievements', __METHOD__ ) ) {
            return false;
        }

        $res = $db->select(
            'userachievements_achievements',
            'enabled',
            [
                'achievement_id' => $this->getId()
            ],
            __METHOD__
        );

        if( $res->numRows() ) {
            $row = $res->fetchRow();

            $this->enabled = (bool) $row[ 'enabled' ];
        }

        return true;
    }



    protected function postprocessDefinition( array &$definition ) {
        // Process badges
        if( $definition[ 'levels' ] < 1 ) {
            throw new MWException( 'Achievement must have at least 1 level.' );
        } elseif( $definition[ 'levels' ] < count( $definition[ 'badges' ] ) ) {
            throw new MWException( 'Achievement levels must be greater than or equal to the number of badges defined.' );
        }

        $badges = [];

        // Create the badge definition for each level of the achievement
        for( $level = 1; $level <= $definition[ 'levels' ]; $level++ ) {
            $badgeDefinition = $definition[ 'badges' ][ $level - 1 ];

            $badgeDefinition[ 'achievementid' ] = $this->getId();
            $badgeDefinition[ 'level' ] = $level;

            $badges[] = new Badge( $badgeDefinition );
        }

        $this->badges = $badges;
    }

    protected function preprocessDefinition( array &$definition ) {
        // Levels may be defined statically in the definition or handled programmatically. Programmatic definition
        // should take priority.
        $definition[ 'levels' ] = $this->getLevels() ?: ( $definition[ 'levels' ] ?: 1 );

        // Make sure a badge is initialized for each level of the achievement
        for( $level = 1; $level <= $definition[ 'levels' ]; $level++ ) {
            $definition[ 'badges' ][ $level - 1 ] = $definition[ 'badges' ][ $level - 1 ] ?? [];
        }
    }

    protected function userCanAward( User $user, int $level ) {}

    protected function setHooks() {
        global $wgHooks;

        if( static::$hooksSet || !$this->isEnabled() ) {
            return;
        }

        foreach( $this->getDefinition( 'Hooks' ) as $hook => $callback ) {
            if( $callback === 'tryAchieve' ) {
                $callback = function() {
                    // tryachieve API call or job?
                    // MediaWiki documentation suggests that since these may be triggered during GET requests
                    // a job should be used. However, this could delay the ideal instant gratification of getting the achievement
                    // on the request that met the required stats. Thus we could alternatively use a FauxRequest.
                    //
                    // Another consideration is that the current approach only allows for achievements based upon data stored in the database
                    // This would not allow for achievements which need to independently store data (and thus would be non-rebuildable).
                    // This couldn't work for something like a "Reader" achievement, for reading a certain number of unique pages.
                    // We'd need a canRebuild() function. Might need a subclass of something like AbstractAmnesticAchievement

                    // For now, only support hooks which are called in valid database-writing scenarios (i.e. posted API calls)
                    // This may or may not work with other hooks.
                    $this->tryAchieve( RequestContext::getMain()->getUser() );
                };
            }

            $wgHooks[ $hook ][] = $callback;
        }

        static::$hooksSet = true;
    }
}