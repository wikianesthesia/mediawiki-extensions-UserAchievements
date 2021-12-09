<?php

namespace MediaWiki\Extension\UserAchievements;

use MediaWiki\Extension\JsonSchemaClasses\AbstractSchema;
use MediaWiki\MediaWikiServices;

class AchievementSchema extends AbstractSchema {
    public function getBaseClass(): string {
        return AbstractAchievement::class;
    }

    public function getClassDefinitionFileName(): string {
        return 'achievement.json';
    }

    public function getSchemaFile(): string {
        return UserAchievements::getExtensionLocalDirectory() . '/resources/schema/achievement.schema.json';
    }

    public function registerClasses( &$classRegistry ) {
        MediaWikiServices::getInstance()->get( 'UserAchievementsHookRunner' )
            ->onUserAchievementsRegisterAchievements( $classRegistry );
    }
}