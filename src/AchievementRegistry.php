<?php

namespace MediaWiki\Extension\UserAchievements;

class  AchievementRegistry {
    protected $achievements;

    public function getAchievement( string $achievementId ) {
        return $this->achievements[ $achievementId ] ?? false;
    }

    public function getAchievements() {
        return $this->achievements;
    }

    public function registerAchievement( string $achievementId, string $class, string $localDirectory ): bool {
        $this->achievements[ $achievementId ] = [
            'class' => $class,
            'localDirectory' => $localDirectory
        ];

        return true;
    }
}