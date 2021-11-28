<?php

namespace MediaWiki\Extension\UserAchievements\Achievement;

use MediaWiki\Extension\UserAchievements\AbstractEditsAchievement;
use MediaWiki\Extension\UserAchievements\AchievementTrait;

/**
 * To make the edit count match the results of the Contribution Scores extension use config:
 *  IncludeDeletedRevisions = false
 *  IncludeNullRevisions = true
 *  IncludeRedirects = true
 */
class ArticlesEdited extends AbstractEditsAchievement {
    use AchievementTrait;

    protected function getEditsStat(): string {
        return 'articlesEdited';
    }

    protected function doGetEditsQueryInfo( array &$editsQueryInfo ) {
        foreach( $editsQueryInfo as $queryId => $queryInfo ) {
            $editsQueryInfo[ $queryId ][ 'options' ][ 'GROUP BY' ] = $queryInfo[ 'edits_table' ] === 'revision' ?
                'rev_page' : 'ar_page_id';
        }
    }
}