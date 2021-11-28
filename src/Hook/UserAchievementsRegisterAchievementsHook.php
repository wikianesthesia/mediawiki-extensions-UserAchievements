<?php

namespace MediaWiki\Extension\UserAchievements\Hook;

use MediaWiki\Extension\UserAchievements\AchievementRegistry;

interface UserAchievementsRegisterAchievementsHook {
    public function onUserAchievementsRegisterAchievements( AchievementRegistry $achievementRegistry );
}