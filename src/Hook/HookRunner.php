<?php

namespace MediaWiki\Extension\UserAchievements\Hook;

use MediaWiki\Extension\JsonSchemaClasses\ClassRegistry;
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

    public function onUserAchievementsRegisterAchievements( ClassRegistry $achievementRegistry ) {
        return $this->hookContainer->run(
            'UserAchievementsRegisterAchievements',
            [ &$achievementRegistry ]
        );
    }
}