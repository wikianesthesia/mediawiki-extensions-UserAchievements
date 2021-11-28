<?php

namespace MediaWiki\Extension\UserAchievements\Api;

abstract class ApiUserAchievementsBasePost extends ApiUserAchievementsBase {

    /**
     * @inheritDoc
     */
    public function mustBePosted() {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isWriteMode() {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function needsToken() {
        return 'csrf';
    }
}