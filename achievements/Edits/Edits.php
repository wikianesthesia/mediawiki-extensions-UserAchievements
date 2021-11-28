<?php

namespace MediaWiki\Extension\UserAchievements\Achievement;

use ActorMigration;
use MediaWiki\Extension\UserAchievements\AbstractEditsAchievement;
use MediaWiki\Extension\UserAchievements\AchievementTrait;
use MediaWiki\Extension\UserAchievements\UserAchievements;
use MediaWiki\Extension\UserAchievements\UserStats;

/**
 * To make the edit count match the results of the Contribution Scores extension use config:
 *  IncludeDeletedRevisions = false
 *  IncludeNullRevisions = true
 *  IncludeRedirects = true
 */
class Edits extends AbstractEditsAchievement {
    use AchievementTrait;

    protected function getEditsStat(): string {
        return 'edits';
    }
}