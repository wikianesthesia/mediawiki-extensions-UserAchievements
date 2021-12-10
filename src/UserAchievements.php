<?php

namespace MediaWiki\Extension\UserAchievements;

use Linker;
use MediaWiki\Extension\JsonSchemaClasses\JsonSchemaClassManager;
use MediaWiki\Extension\UserAchievements\Hook\HookRunner;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use RequestContext;
use TemplateParser;
use User;
use Wikimedia\Rdbms\DBConnRef;

class UserAchievements {
    protected const SCHEMA_CLASS = AchievementSchema::class;

    protected static $achievementsLocalDirectory;
    protected static $extensionLocalDirectory;

    /**
     * $userBadges[ $userId ][]
     * @var UserBadge[][]
     */
    protected static $userBadges = [];

    /**
     * $userBadgesByAchievement[ $userId ][ $achievementId ][ $level ]
     * @var UserBadge[][][]
     */
    protected static $userBadgesByAchievement = [];

    /**
     * @var JsonSchemaClassManager
     */
    protected static $classManager;

    /**
     * @var LoggerInterface
     */
    protected static $logger;

    /**
     * @var TemplateParser
     */
    protected static $templateParser;


    /**
     * @param string $achievementId
     * @return AbstractAchievement|null
     */
    public static function getAchievement( string $achievementId ): ?AbstractAchievement {
        return static::$classManager->getClassInstanceForSchema( static::SCHEMA_CLASS, $achievementId );
    }



    /**
     * @return AbstractAchievement[]
     */
    public static function getAchievements(): array {
        return static::$classManager->getClassInstancesForSchema( static::SCHEMA_CLASS );
    }



    /**
     * @return string
     */
    public static function getAchievementsLocalDirectory(): string {
        if( !static::$achievementsLocalDirectory ) {
            $achievementsLocalDirectory = static::getExtensionLocalDirectory() . '/achievements';

            if( !is_dir( $achievementsLocalDirectory ) ) {
                // TODO throw error achievements directory not found

                return false;
            }

            static::$achievementsLocalDirectory = $achievementsLocalDirectory;
        }

        return static::$achievementsLocalDirectory;
    }



    /**
     * @param User $user
     * @return UserBadge[]
     */
    public static function getBadgesForUser( User $user ): array {
        // TODO
        return [];
    }



    /**
     * @param int $i
     * @return DBConnRef
     */
    public static function getDB( $i = DB_MASTER ): DBConnRef {
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        return $lb->getConnectionRef( $i );
    }



    /**
     * @return string
     */
    public static function getExtensionLocalDirectory(): string {
        if( !static::$extensionLocalDirectory ) {
            static::$extensionLocalDirectory = realpath( __DIR__ . '/..' );
        }

        return static::$extensionLocalDirectory;
    }



    /**
     * @return string
     */
    public static function getExtensionName(): string {
        return 'UserAchievements';
    }



    /**
     * @return LoggerInterface
     */
    public static function getLogger(): LoggerInterface {
        if( !static::$logger ) {
            static::$logger = LoggerFactory::getInstance( static::getExtensionName() );
        }

        return static::$logger;
    }



    /**
     * @return TemplateParser
     */
    public static function getTemplateParser(): TemplateParser {
        if( static::$templateParser === null ) {
            static::$templateParser = new TemplateParser( static::getExtensionLocalDirectory() . '/resources/templates' );
        }

        return static::$templateParser;
    }



    /**
     * @param User|null $user
     * @param string $achievementId
     * @param int $level
     * @return UserBadge|false
     */
    public static function getUserBadge( string $achievementId, int $level = 1, User $user = null ) {
        $user = $user ?: RequestContext::getMain()->getUser();

        if( !isset( static::$userBadgesByAchievement[ $user->getId() ] ) ) {
            static::loadUserBadges( $user );
        }

        if( !isset( static::$userBadgesByAchievement[ $user->getId() ][ $achievementId ] ) ||
            !isset( static::$userBadgesByAchievement[ $user->getId() ][ $achievementId ][ $level ] ) ) {
            return false;
        }

        return static::$userBadgesByAchievement[ $user->getId() ][ $achievementId ][ $level ];
    }



    /**
     * @param User|null $user
     * @return UserBadge[]
     */
    public static function getUserBadges( User $user = null, bool $onlyMaxLevel = false ): array {
        $user = $user ?: RequestContext::getMain()->getUser();

        if( !isset( static::$userBadges[ $user->getId() ] ) ) {
            static::loadUserBadges( $user );
        }

        if( $onlyMaxLevel ) {
            $maxLevelUserBadges = [];

            foreach( static::$userBadges[ $user->getId() ] as $userBadge ) {
                $maxLevelUserBadges[ $userBadge->getAchievementId() ] = $userBadge;
            }

            return array_values( $maxLevelUserBadges );
        } else {
            return static::$userBadges[ $user->getId() ];
        }
    }



    /**
     * @param User|null $user
     * @return string
     */
    public static function getUserLink( User $user = null ): string {
        global $wgUserAchievementsUseRealName;

        $user = $user ?: RequestContext::getMain()->getUser();
        $altUserText = $wgUserAchievementsUseRealName && $user->getRealName() ? $user->getRealName() : false;

        return Linker::userLink( $user->getId(), $user->getName(), $altUserText );
    }



    /**
     *
     */
    public static function initialize() {
        static::$classManager = MediaWikiServices::getInstance()->get( 'JsonSchemaClassManager' );
        static::$classManager->registerSchema(AchievementSchema::class );
    }


    /**
     * @param User|null $user
     * @return bool
     */
    public static function isUserEligible( User $user = null ): bool {
        global $wgUserAchievementsIgnoreUsernames;

        $user = $user ?: RequestContext::getMain()->getUser();

        return $user->isRegistered() &&
            !$user->isBot() &&
            !$user->isSystemUser() &&
            !in_array( $user->getName(), $wgUserAchievementsIgnoreUsernames );
    }



    /**
     * @param User|null $user
     */
    public static function tryAchieveAll( User $user = null ) {
        $user = $user ?: RequestContext::getMain()->getUser();

        foreach( static::getAchievements() as $achievement ) {
            $achievement->tryAchieve( $user );
        }
    }



    /**
     * @param $achievementId
     * @param int $level
     * @param User|null $user
     * @return bool
     */
    public static function userHasAchieved( $achievementId, int $level = 1, User $user = null ): bool {
        return (bool) static::getUserBadge( $achievementId, $level, $user );
    }



    /**
     * @param User|null $user
     * @return bool
     */
    public static function userIsUserAchievementsAdmin( User $user = null ): bool {
        $user = $user ?: RequestContext::getMain()->getUser();

        return MediaWikiServices::getInstance()->getPermissionManager()->userHasRight(
            $user,
            'userachievements-admin'
        );
    }



    /**
     * @param User|null $user
     * @return bool
     */
    protected static function loadUserBadges( User $user = null ): bool {
        $user = $user ?: RequestContext::getMain()->getUser();

        static::$userBadges[ $user->getId() ] = UserBadge::getForUser( $user );

        // $userBadgesAchieved is a map of whether the current user has achieved every registered badge
        static::$userBadgesByAchievement[ $user->getId() ] = [];

        foreach( static::getAchievements() as $achievement ) {
            if( !isset( static::$userBadgesByAchievement[ $user->getId() ][ $achievement->getId() ] ) ) {
                static::$userBadgesByAchievement[ $user->getId() ][ $achievement->getId() ] = [];
            }

            foreach( $achievement->getBadges() as $badge ) {
                static::$userBadgesByAchievement[ $user->getId() ][ $achievement->getId() ][ $badge->getLevel() ] = false;
            }
        }

        // Set the badges the user has achieved in the map to true
        foreach( static::$userBadges[ $user->getId() ] as $userBadge ) {
            static::$userBadgesByAchievement[ $user->getId() ][ $userBadge->getAchievementId() ][ $userBadge->getLevel() ] = $userBadge;
        }

        return true;
    }
}