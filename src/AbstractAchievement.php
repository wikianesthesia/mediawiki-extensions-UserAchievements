<?php

namespace MediaWiki\Extension\UserAchievements;

use MediaWiki\MediaWikiServices;
use MWException;
use MWTimestamp;
use ReflectionClass;
use RequestContext;
use Title;
use User;

abstract class AbstractAchievement {
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
    protected $localdirectory = '';

    /**
     * @var string
     */
    protected $remotedirectory = '';

    /**
     * Definition data from achievement.json
     * @var array
     */
    protected $definition = [];

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


    public function __construct() {
        $this->loadDBConfig();
        $this->loadDefinition();

        if( true || $this->isEnabled() ) {
            $this->setHooks();
        }
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

        return $this->definition[ 'color' ] ?: $wgUserAchievementsDefaultAchievedColor;
    }

    /**
     * @return mixed|array
     */
    public function getConfig( string $var = '' ) {
        global $wgUserAchievementsAchievementsConfig;

        if( $var ) {
            return $wgUserAchievementsAchievementsConfig[ $this->getId() ][ $var ] ?? null;
        } else {
            return $wgUserAchievementsAchievementsConfig[ $this->getId() ];
        }
    }

    /**
     * @return string
     */
    public function getDescription(): string {
        if( $this->description === null ) {
            $descriptionmsg = '';

            if( $this->definition[ 'descriptionmsg' ] ) {
                $descriptionmsg = wfMessage( $this->definition[ 'descriptionmsg' ] );
            }

            if( !$descriptionmsg || !$descriptionmsg->exists() ) {
                $descriptionmsg = wfMessage( $this->getDefaultDescriptionMsgKey() );
            }

            if( $descriptionmsg->exists() ) {
                $description = $descriptionmsg->text();
            } elseif( $this->definition[ 'description' ] ) {
                $description = $this->definition[ 'description' ];
            } else {
                $description = '';
            }

            $this->description = $description;
        }

        return $this->description;
    }

    /**
     * @return string
     */
    public function getId(): string {
        if( !$this->id ) {
            $this->id = substr( strrchr( static::class, '\\' ), 1 );
        }

        return $this->id;
    }

    /**
     * @return integer
     */
    public function getLevels(): int {
        return $this->definition[ 'levels' ] ?? 0;
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
    public function getLocalDirectory(): string {
        if( !$this->localdirectory ) {
            $reflectionClass = new ReflectionClass( static::class );
            $classFilename = $reflectionClass->getFileName();

            $this->localdirectory = str_replace( '/' . $reflectionClass->getShortName() . '.php', '', $classFilename );
        }

        return $this->localdirectory;
    }

    /**
     * @return string
     */
    public function getMsgKeyPrefix(): string {
        return strtolower( UserAchievements::getExtensionName() . '-' . $this->getId() );
    }

    /**
     * @return string
     */
    public function getName(): string {
        if( $this->name === null ) {
            $namemsg = '';

            if( $this->definition[ 'namemsg' ] ) {
                $namemsg = wfMessage( $this->definition[ 'namemsg' ] );
            }

            if( !$namemsg || !$namemsg->exists() ) {
                $namemsg = wfMessage( $this->getDefaultNameMsgKey() );
            }

            if( $namemsg->exists() ) {
                $name = $namemsg->text();
            } elseif( $this->definition[ 'name' ] ) {
                $name = $this->definition[ 'name' ];
            } else {
                $name = $this->getId();
            }

            $this->name = $name;
        }

        return $this->name;
    }

    /**
     * @return integer
     */
    public function getPriority(): int {
        return $this->definition[ 'priority' ];
    }

    /**
     * @return string
     */
    public function getRemoteDirectory(): string {
        if( !$this->remotedirectory ) {
            $this->remotedirectory = str_replace( $_SERVER[ 'DOCUMENT_ROOT' ], '', $this->getLocalDirectory() );
        }

        return $this->remotedirectory;
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
        return $this->definition[ 'stats' ];
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
        return $this->definition[ 'secret' ];
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
     * @return array
     */
    public function setConfig( string $var, $value = null ) {
        global $wgUserAchievementsAchievementsConfig;

        $wgUserAchievementsAchievementsConfig[ $this->getId() ][ $var ] = $value;
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

        // TODO figure out validation.
        // affectedRows() sometimes returns 1 and sometimes returns 2 even though only one row is actually changed.
        return $db->affectedRows() > 0;
    }

    /**
     * Default tryAchieve function. Awards badges based upon required stats defined in achievement.json
     * Subclasses should override this function if they do not wish to use default user stats behavior.
     * @param User $user
     */
    protected function doTryAchieve( User $user ) {
        $userStats = $this->getUserStats( $user );

        for( $level = 1; $level <= $this->getLevels(); $level++ ) {
            if( $this->userHasAchieved( $user, $level ) ) {
                continue;
            }

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

    /**
     * @return string
     */
    protected function getDefaultDescriptionMsgKey(): string {
        return $this->getMsgKeyPrefix() . '-desc';
    }

    /**
     * @return string
     */
    protected function getDefaultNameMsgKey(): string {
        return $this->getMsgKeyPrefix() . '-name';
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

    /**
     * @return bool
     * @throws \MWException
     */
    protected function loadDefinition(): bool {
        global $wgUserAchievementsAchievementsConfig, $wgMessagesDirs;

        $achievementId = $this->getId();
        $achievementLocalDirectory = $this->getLocalDirectory();

        // Load achievement.json definition
        if( !is_dir( $achievementLocalDirectory ) ) {
            // TODO throw error achievement directory not found

            return false;
        }

        $achievementDefinitionFile = $achievementLocalDirectory . '/achievement.json';

        $achievementDefinitionJson = file_get_contents( $achievementDefinitionFile );

        if( $achievementDefinitionJson === false ) {
            // TODO log error achievement definition file not found or not accessible

            return false;
        }

        $achievementDefinition = json_decode( $achievementDefinitionJson, true );

        if( !is_array( $achievementDefinition ) ) {
            // TODO log error json file not valid

            return false;
        }

        // Get achievement.json schema
        $achievementSchema = UserAchievements::getAchievementSchema();

        // Levels may be defined statically in the definition or handled programmatically. Programmatic definition
        // should take priority.
        $achievementDefinition[ 'levels' ] = $this->getLevels() ?: ( $achievementDefinition[ 'levels' ] ?: 1 );

        // Make sure a badge is initialized for each level of the achievement
        for( $level = 1; $level <= $achievementDefinition[ 'levels' ]; $level++ ) {
            $achievementDefinition[ 'badges' ][ $level - 1 ] = $achievementDefinition[ 'badges' ][ $level - 1 ] ?? [];
        }

        $this->processDefinitionProperties( $achievementSchema, $achievementDefinition );

        // Add configuration directives
        // Initialize config for achievement regardless if any config directives are defined
        if( !isset( $wgUserAchievementsAchievementsConfig[ $achievementId ] ) ) {
            $wgUserAchievementsAchievementsConfig[ $achievementId ] = [];
        }

        if( isset( $achievementDefinition[ 'config' ] ) ) {
            foreach( $achievementDefinition[ 'config' ] as $configVar => $defaultValue ) {
                if( !isset( $wgUserAchievementsAchievementsConfig[ $achievementId ][ $configVar ] ) ) {
                    $wgUserAchievementsAchievementsConfig[ $achievementId ][ $configVar ] = $defaultValue;
                }
            }
        }

        // Add message directories
        if( isset( $achievementDefinition[ 'MessagesDirs' ] ) ) {
            foreach( $achievementDefinition[ 'MessagesDirs' ] as $messagesDir ) {
                $wgMessagesDirs[ 'UserAchievements' ][] = $this->getLocalDirectory() . '/' . $messagesDir;
            }
        }

        // Process badges
        if( $achievementDefinition[ 'levels' ] < 1 ) {
            throw new MWException( 'Achievement must have at least 1 level.' );
        } elseif( $achievementDefinition[ 'levels' ] < count( $achievementDefinition[ 'badges' ] ) ) {
            throw new MWException( 'Achievement levels must be greater than or equal to the number of badges defined.' );
        }

        $badges = [];

        // Create the badge definition for each level of the achievement
        for( $level = 1; $level <= $achievementDefinition[ 'levels' ]; $level++ ) {
            $badgeDefinition = $achievementDefinition[ 'badges' ][ $level - 1 ];

            $badgeDefinition[ 'achievementid' ] = $achievementId;
            $badgeDefinition[ 'level' ] = $level;

            $badges[] = new Badge( $badgeDefinition );
        }

        $this->definition = $achievementDefinition;
        $this->badges = $badges;

        return true;
    }

    protected function processDefinitionProperties( array $schema, array &$definition ) {
        // Validate and import definition data into static class property
        foreach( $schema[ 'properties' ] as $propertyName => $propertyDefinition ) {
            // Make sure required property defined
            if( isset( $schema[ 'properties' ][ $propertyName ][ 'required' ] ) ) {
                if( $schema[ 'properties' ][ $propertyName ][ 'required' ] &&
                    !isset( $definition[ $propertyName ]) ) {
                    // TODO throw exception required property missing
                    throw new MWException( 'Required property missing' );

                    return false;
                }
            }

            $propertyValue = null;

            if( isset( $definition[ $propertyName ] ) ) {
                $propertyValue = $definition[ $propertyName ];
            } else {
                if( isset( $schema[ 'properties' ][ $propertyName ][ 'default' ] ) ) {
                    $propertyValue = $schema[ 'properties' ][ $propertyName ][ 'default' ];
                } else {
                    // If the type is unambiguous (i.e. a string and not an array of possible types)
                    // cast null to the appropriate type
                    if( isset( $schema[ 'properties' ][ $propertyName ][ 'type' ] ) ) {
                        // If the type is an array of valid types, use the first type in the array
                        $nullType = gettype( $schema[ 'properties' ][ $propertyName ][ 'type' ] ) === 'array' ?
                            reset( $schema[ 'properties' ][ $propertyName ][ 'type' ] ) :
                            $schema[ 'properties' ][ $propertyName ][ 'type' ];

                        // Since objects are actually imported as arrays, if the type is object, change to array
                        if( $nullType === 'object' ) {
                            $nullType = 'array';
                        }

                        settype($propertyValue, $nullType );
                    }
                }
            }

            if( isset( $schema[ 'properties' ][ $propertyName ][ 'type' ] ) ) {
                $propertyTypes = $schema[ 'properties' ][ $propertyName ][ 'type' ];

                if( !is_array( $propertyTypes ) ) {
                    $propertyTypes = [ $propertyTypes ];
                }

                // Objects will be imported as arrays, so this is a hack to fix that type casting
                if( in_array( 'object', $propertyTypes ) ) {
                    $propertyTypes[] = 'array';
                }

                if( !in_array( gettype( $propertyValue ), $propertyTypes ) ) {
                    // TODO throw exception type mismatch
                    /*
                    echo( $propertyName);
                    var_dump( $propertyValue );
                    echo( gettype($propertyValue));
                    var_dump($propertyTypes);
                    */
                    throw new \MWException( 'Type mismatch' );

                    return false;
                }
            }

            $definition[ $propertyName ] = $propertyValue;

            if( isset( $schema[ 'properties' ][ $propertyName ][ 'properties' ] ) ) {
                $this->processDefinitionProperties( $schema[ 'properties' ][ $propertyName ], $definition[ $propertyName ] );
            }

            if( isset( $schema[ 'properties' ][ $propertyName ][ 'items' ] ) &&
                isset( $schema[ 'properties' ][ $propertyName ][ 'items' ][ 'properties' ] ) ) {
                foreach( $definition[ $propertyName ] as $itemIndex => $item ) {
                    $this->processDefinitionProperties( $schema[ 'properties' ][ $propertyName ][ 'items' ], $definition[ $propertyName ][ $itemIndex ] );
                }
            }
        }
    }

    protected function userCanAward( User $user, int $level ) {}

    protected function setHooks() {
        global $wgHooks;

        if( static::$hooksSet ) {
            return;
        }

        foreach( $this->definition[ 'Hooks' ] as $hook => $callback ) {
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