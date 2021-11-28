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
class ArticlesCreated extends AbstractEditsAchievement {
    use AchievementTrait;

    protected function getEditsStat(): string {
        return 'articlesCreated';
    }

    protected function doGetEditsQueryInfo( array &$editsQueryInfo ) {
        foreach( $editsQueryInfo as $queryId => $queryInfo ) {
            $parentFieldId = $queryInfo[ 'edits_table' ] === 'revision' ?
                'revision.rev_parent_id' : 'archive.ar_parent_id';

            $editsQueryInfo[ $queryId ][ 'conds' ][ $parentFieldId ] = 0;
        }
    }
}