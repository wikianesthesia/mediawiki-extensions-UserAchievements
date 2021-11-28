<?php

namespace MediaWiki\Extension\UserAchievements;

use MediaWiki\MediaWikiServices;
use RequestContext;
use Title;
use User;

class Badge {
    protected $name = null;
    protected $description = null;

    protected $definition = [];

    protected $media = [
        'image' => null,
        'thumbnail' => null,
        'icon' => null
    ];

    /**
     * @param array $badgeDefinition
     */
    public function __construct( array $badgeDefinition = [] ) {
        $this->definition = $badgeDefinition;
    }

    /**
     * @return AbstractAchievement
     */
    public function getAchievement(): AbstractAchievement {
        return UserAchievements::getAchievement( $this->getAchievementId() );
    }

    /**
     * @return string
     */
    public function getAchievementId(): string {
        return $this->definition[ 'achievementid' ];
    }

    /**
     * @return string
     */
    public function getColor(): string {
        return $this->definition[ 'color' ] ?: $this->getAchievement()->getColor();
    }

    /**
     * @return array
     */
    public function getDefinition(): array {
        return $this->definition;
    }

    /**
     * @return string
     */
    public function getDescription(): string {
        if( $this->description === null ) {
            $description = '';

            $msgkeys = [];

            if( isset( $this->definition[ 'descriptionmsg' ] ) && $this->definition[ 'descriptionmsg' ] ) {
                $msgkeys[] = $this->definition[ 'descriptionmsg' ];
            }

            $msgkeys[] = $this->getMsgKeyPrefixLevel() . '-desc';
            $msgkeys[] = $this->getMsgKeyPrefixGeneric() . '-desc';

            foreach( $msgkeys as $msgkey ) {
                $msg = wfMessage( $msgkey );

                if( $msg->exists() ) {
                    $description = $msg->text();

                    break;
                }
            }

            if( !$description ) {
                if( isset( $this->definition[ 'description' ] ) && $this->definition[ 'description' ] ) {
                    $name = $this->definition[ 'description' ];
                } else {
                    $description = $this->getAchievement()->getDescription();
                }
            }

            $this->description = $this->parseMessageVars( $description );
        }

        return $this->description;
    }

    /**
     * @param User|null $user
     * @return string
     */
    public function getFullHtml( User $user = null ): string {
        $templateParser = UserAchievements::getTemplateParser();
        $achievement = $this->getAchievement();

        $color = $this->getColor();

        $userBadge = UserAchievements::getUserBadge( $this->getAchievementId(), $this->getLevel(), $user );

        $badgeData = [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'color' => $color,
            'image' => $this->getMediaFileRemotePath(),
            'link' => $achievement->getLinkURL( $this->getLevel() ),
            'class' => '',
            'achieved' => (bool) $userBadge,
            'progress' => false
        ];

        $userStats = $user ? $this->getAchievement()->getUserStats( $user ) : $this->getAchievement()->getStats();
        $requiredStats = $this->getRequiredStats();

        if( $userBadge ) {
            $badgeData[ 'class' ] .= ' userachievements-badgefull-achieved';

            $badgeData[ 'progress' ] .= '<i>' . wfMessage( 'userachievements-badge-progress-achieved', $userBadge->getAchievedDateString() ) . '</i>';
        } else {
            if( $user ) {
                // If rendering for a specific user and the badge is not achieved, apply appropriate style
                $badgeData[ 'class' ] .= ' userachievements-badgefull-notachieved';
            }

            foreach( $requiredStats as $stat => $requiredValue ) {
                $currentValue = $user ? $userStats->getValue( $stat ) : 0;

                $progressData = [
                    'stat' => $this->getAchievement()->getStatName( $stat ),
                    'currentvalue' => $currentValue,
                    'requiredvalue' => $requiredValue,
                    'pct' => round( 100 * $currentValue / $requiredValue ),
                    'color' => $color
                ];

                $badgeData[ 'progress' ] .= $templateParser->processTemplate( 'BadgeProgress', $progressData );
            }
        }

        return $templateParser->processTemplate( 'BadgeFull', $badgeData );
    }

    /**
     * @return int
     */
    public function getLevel(): int {
        return $this->definition[ 'level' ];
    }

    /**
     * @return string
     */
    public function getLinkURL(): string {
        $title = Title::newFromText( 'UserAchievements', NS_SPECIAL );

        return $title->getLinkURL( [
            'achievement' => $this->getAchievementId(),
            'level' => $this->getLevel()
        ] );
    }

    /**
     * @return string
     */
    public function getName(): string {
        if( $this->name === null ) {
            $name = '';

            $msgkeys = [];

            if( isset( $this->definition[ 'namemsg' ] ) && $this->definition[ 'namemsg' ] ) {
                $msgkeys[] = $this->definition[ 'namemsg' ];
            }

            $msgkeys[] = $this->getMsgKeyPrefixLevel() . '-name';
            $msgkeys[] = $this->getMsgKeyPrefixGeneric() . '-name';

            foreach( $msgkeys as $msgkey ) {
                $msg = wfMessage( $msgkey );

                if( $msg->exists() ) {
                    $name = $msg->text();

                    break;
                }
            }

            if( !$name ) {
                if( isset( $this->definition[ 'name' ] ) && $this->definition[ 'name' ] ) {
                    $name = $this->definition[ 'name' ];
                } else {
                    $achievement = $this->getAchievement();

                    $name = $achievement->getName();

                    if( $achievement->getLevels() > 1 ) {
                        $name .= ' ' . $this->getLevel();
                    }
                }
            }

            $this->name = $this->parseMessageVars( $name );
        }

        return $this->name;
    }

    /**
     * @param string $variant
     * @return string
     */
    public function getMediaFileLocalPath( string $variant = 'image' ): string {
        if( !array_key_exists( $variant, $this->media ) ) {
            // TODO error invalid variant
            return '';
        }

        if( $this->media[ $variant ] === null ) {
            $mediaFileLocalPath = '';

            $achievementMediaLocalDirectory = $this->getAchievement()->getLocalDirectory() . '/media';

            // Language-specific media files should take priority with the generic files as fallbacks
            $langCode = RequestContext::getMain()->getLanguage()->getCode();
            $achievementMediaLangLocalDirectory = $achievementMediaLocalDirectory . '/' . $langCode;

            $tryMediaFileLocalPaths = [];

            // If a media file is explicitly specified in the achievement.json definition, only use that and
            // do not try any default filenames for the variant.
            if( $this->definition[ 'media' ][ $variant ] ) {
                $tryMediaFileLocalPaths[] = $achievementMediaLangLocalDirectory . '/' . $this->definition[ 'media' ][ $variant ];
                $tryMediaFileLocalPaths[] = $achievementMediaLocalDirectory . '/' . $this->definition[ 'media' ][ $variant ];
            } else {
                // Filename with the level and the variant (e.g 3-image.png)
                $achievementLevelVariantMediaFilename = $this->getLevel() . '-' . $variant;
                // Filename with just the level (e.g. 3.png)
                $achievementLevelMediaFilename = $this->getLevel();
                // Filename with just the variant (e.g. image.png)
                $achievementVariantMediaFilename = $variant;

                $mediaFilenames = [
                    $achievementLevelVariantMediaFilename,
                    $achievementLevelMediaFilename,
                    $achievementVariantMediaFilename,
                ];

                $fileExtensions = [
                    'svg',
                    'png',
                    'gif',
                    'jpg'
                ];

                // Prioritize files in the achievement media directory
                foreach( $mediaFilenames as $mediaFilename ) {
                    foreach( $fileExtensions as $fileExtension ) {
                        $tryMediaFileLocalPaths[] =
                            $achievementMediaLangLocalDirectory .
                            '/' . $mediaFilename . '.' . $fileExtension;

                        $tryMediaFileLocalPaths[] =
                            $achievementMediaLocalDirectory .
                            '/' . $mediaFilename . '.' . $fileExtension;
                    }
                }

                $extensionMediaLocalDirectory = UserAchievements::getExtensionLocalDirectory() . '/resources/media';

                // Add fallback files from the extension media directory
                foreach( $mediaFilenames as $mediaFilename ) {
                    foreach( $fileExtensions as $fileExtension ) {
                        $tryMediaFileLocalPaths[] =
                            $extensionMediaLocalDirectory .
                            '/' . $mediaFilename . '.' . $fileExtension;
                    }
                }
            }

            foreach( $tryMediaFileLocalPaths as $tryMediaFileLocalPath ) {
                if( file_exists( $tryMediaFileLocalPath ) ) {
                    // As soon as a file is found, set the variable and exit the loop
                    $mediaFileLocalPath = $tryMediaFileLocalPath;

                    break;
                }
            }

            // Cache the result which was found
            $this->media[ $variant ] = $mediaFileLocalPath;
        }

        return $this->media[ $variant ];
    }

    /**
     * @param string $variant
     * @return string
     */
    public function getMediaFileRemotePath( string $variant = 'image' ): string {
        $mediaFileLocalPath = $this->getMediaFileLocalPath( $variant );

        return $mediaFileLocalPath ?
            str_replace( $_SERVER[ 'DOCUMENT_ROOT' ], '', $mediaFileLocalPath ) :
            '';
    }

    /**
     * @return string
     */
    public function getMsgKeyPrefixGeneric(): string {
        return $this->getAchievement()->getMsgKeyPrefix() .
            '-0';
    }

    /**
     * @return string
     */
    public function getMsgKeyPrefixLevel(): string {
        return $this->getAchievement()->getMsgKeyPrefix() .
            '-' . $this->getLevel();
    }

    /**
     * @return array
     */
    public function getRequiredStats(): array {
        return $this->definition[ 'requiredStats' ];
    }

    /**
     * @param User|null $user
     * @return string
     */
    public function getThumbnailHtml( User $user = null ): string {
        $templateParser = UserAchievements::getTemplateParser();
        $achievement = $this->getAchievement();

        $color = $this->getColor();

        $badgeData = [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'color' => $color,
            'image' => $this->getMediaFileRemotePath(),
            'link' => $achievement->getLinkURL( $this->getLevel() )
        ];

        $userBadge = UserAchievements::getUserBadge( $this->getAchievementId(), $this->getLevel(), $user );

        if( !$userBadge && $user ) {
            // If rendering for a specific user and the badge is not achieved, apply appropriate style
            $badgeData[ 'class' ] = 'userachievements-badge-notachieved';
        }

        return $templateParser->processTemplate( 'BadgeThumbnail', $badgeData );
    }

    /**
     * @return UserBadge[]
     */
    public function getAchievedUserBadges( int $limit = 0 ): array {
        $userBadges = [];

        $achievement = $this->getAchievement();

        $db = UserAchievements::getDB();

        $options = [
            'ORDER BY' => 'achieved_time ASC',
        ];

        if( $limit ) {
            $options[ 'LIMIT' ] = $limit;
        }

        $res = $db->select(
            'userachievements_userbadges',
            '*',
            [
                'achievement_id' => $achievement->getId(),
                'level' => $this->getLevel()
            ],
            __METHOD__,
            $options
        );

        while( $row = $res->fetchRow() ) {
            $userBadges[] = new UserBadge( $this->getDefinition(), $row );
        }

        return $userBadges;
    }

    public function isEnabled(): bool {
        return $this->getAchievement()->isEnabled();
    }

    /**
     * @return bool
     */
    public function isSecret(): bool {
        return $this->definition[ 'secret' ];
    }

    protected function parseMessageVars( string $message ) {
        $message = str_replace( '$level', $this->getLevel(), $message );

        return $message;
    }
}