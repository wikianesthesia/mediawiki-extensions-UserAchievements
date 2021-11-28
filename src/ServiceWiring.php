<?php
namespace MediaWiki\Extension\UserAchievements;

use MediaWiki\Extension\UserAchievements\Hook\HookRunner;
use MediaWiki\MediaWikiServices;

return [
    'UserAchievementsHookRunner' => static function ( MediaWikiServices $services ): HookRunner {
        return new HookRunner( $services->getHookContainer() );
    },
];
