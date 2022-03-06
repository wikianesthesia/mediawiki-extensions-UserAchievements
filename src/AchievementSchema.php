<?php

namespace MediaWiki\Extension\UserAchievements;

use MediaWiki\Extension\JsonClasses\AbstractSchema;
use MediaWiki\MediaWikiServices;

class AchievementSchema extends AbstractSchema {
    public function getBaseClass(): string {
        return AbstractAchievement::class;
    }

    public function getClassDefinitionFileName(): string {
        return 'achievement.json';
    }

    public function getExtensionName(): string {
        return UserAchievements::getExtensionName();
    }

    public function getSchemaFile(): string {
        return UserAchievements::getExtensionLocalDirectory() . '/resources/schema/achievement.schema.json';
    }

    public function getSchemaName(): string {
        return 'Achievement';
    }
}