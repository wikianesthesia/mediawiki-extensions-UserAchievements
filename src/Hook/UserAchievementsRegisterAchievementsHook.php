<?php

namespace MediaWiki\Extension\UserAchievements\Hook;

use MediaWiki\Extension\JsonSchemaClasses\ClassRegistry;

interface UserAchievementsRegisterAchievementsHook {
    public function onUserAchievementsRegisterAchievements( ClassRegistry $achievementRegistry );
}