<?php

namespace MediaWiki\Extension\UserAchievements\Achievement;

use MediaWiki\Extension\UserAchievements\AbstractEditsAchievement;
use MediaWiki\Extension\UserAchievements\AchievementTrait;

class PracticeGroupEdits extends AbstractEditsAchievement {
    use AchievementTrait;

    protected function getEditsStat(): string {
        return 'practiceGroupEdits';
    }
}