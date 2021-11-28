<?php

namespace MediaWiki\Extension\UserAchievements\Hook;

use MediaWiki\Extension\UserAchievements\AchievementRegistry;
use MediaWiki\HookContainer\HookContainer;

class HookRunner implements UserAchievementsRegisterAchievementsHook {
    /** @var HookContainer */
    private $hookContainer;

    /**
     * @param HookContainer $hookContainer
     */
    public function __construct( HookContainer $hookContainer ) {
        $this->hookContainer = $hookContainer;
    }

    public function onUserAchievementsRegisterAchievements( AchievementRegistry $achievementRegistry ) {
        return $this->hookContainer->run(
            'UserAchievementsRegisterAchievements',
            [ &$achievementRegistry ]
        );
    }
}