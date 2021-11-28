<?php

namespace MediaWiki\Extension\UserAchievements;

/**
 * For any static properties, we have to use traits rather than class inheritance, since a static property declared
 * in the parent class uses the same memory location as all subclasses.
 */
trait AchievementTrait {
    /**
     * @var bool
     */
    protected static $hooksSet = false;

    /**
     * $userStats[ $userId ]
     * @var UserStats[]
     */
    protected static $userStats = [];
}