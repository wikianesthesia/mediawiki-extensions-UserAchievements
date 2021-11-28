<?php

namespace MediaWiki\Extension\UserAchievements;

use ActorMigration;
use User;

abstract class AbstractEditsAchievement extends AbstractAchievement {
    abstract protected function getEditsStat(): string;

    protected function doGetEditsQueryInfo( array &$editsQueryInfo ) {}

    protected function doGetUserStats( UserStats &$userStats ) {
        $db = UserAchievements::getDB();

        $editsQueryInfo = $this->getEditsQueryInfo( $userStats->getUser() );

        foreach( $editsQueryInfo as $queryInfo ) {
            $res = $db->select(
                $queryInfo[ 'tables' ],
                $queryInfo[ 'vars' ],
                $queryInfo[ 'conds' ],
                __METHOD__,
                $queryInfo[ 'options' ],
                $queryInfo[ 'join_conds' ]
            );

            while( $row = $res->fetchRow() ) {
                $userStats->addEventTime( $queryInfo[ 'stat' ], $row[ $queryInfo[ 'timestamp_var' ] ] );
            }
        }
    }

    /**
     * @return array
     */
    protected function getEditsQueryInfo( User $user ): array {
        global $wgContentNamespaces;

        $editsQueryInfo = [];

        $db = UserAchievements::getDB();

        $revisionQueryInfo = $this->getEditsQueryInfoPrototype();

        $revisionQueryInfo[ 'edits_table' ] = 'revision';
        $revisionQueryInfo[ 'tables' ][] = $revisionQueryInfo[ 'edits_table' ];
        $revisionQueryInfo[ 'timestamp_var' ] = 'rev_timestamp';
        $revisionQueryInfo[ 'vars' ][] = $revisionQueryInfo[ 'timestamp_var' ];
        $revisionQueryInfo[ 'options' ] = [ 'ORDER BY' => $revisionQueryInfo[ 'timestamp_var' ] ];

        $editsQueryInfo[] = $revisionQueryInfo;

        if( $this->getConfig( 'IncludeDeletedRevisions' ) ) {
            $archiveQueryInfo = $this->getEditsQueryInfoPrototype();

            $archiveQueryInfo[ 'edits_table' ] = 'archive';
            $archiveQueryInfo[ 'tables' ][] = $archiveQueryInfo[ 'edits_table' ];
            $archiveQueryInfo[ 'timestamp_var' ] = 'ar_timestamp';
            $archiveQueryInfo[ 'vars' ][] = $archiveQueryInfo[ 'timestamp_var' ];
            $archiveQueryInfo[ 'options' ] = [ 'ORDER BY' => $archiveQueryInfo[ 'timestamp_var' ] ];

            $editsQueryInfo[] = $archiveQueryInfo;
        }

        foreach( $editsQueryInfo as $queryId => $queryInfo ) {
            // Some revisions may not involve content changing, and thus shouldn't count as an edit
            // if null edits aren't included.
            if( !$this->getConfig( 'IncludeNullRevisions' ) ) {
                // Content changed (i.e. was an actual edit) only if slot_origin=slot_revision_id
                $editsQueryInfo[ $queryId ][ 'tables' ][ 'slots' ] = 'slots';
                $editsQueryInfo[ $queryId ][ 'conds' ][] = 'slots.slot_origin = slots.slot_revision_id';

                // Select the correct table/field depending on whether we're searching revision or archive
                $revIdField = $queryInfo[ 'edits_table' ] === 'revision' ? 'revision.rev_id' : 'archive.ar_rev_id';

                $editsQueryInfo[ $queryId ][ 'join_conds' ][ 'slots' ] = [
                    'JOIN',
                    $revIdField . ' = slots.slot_revision_id'
                ];
            }

            $revUserField = $queryInfo[ 'edits_table' ] === 'revision' ? 'rev_user' : 'ar_user';

            $actorWhere = ActorMigration::newMigration()->getWhere( $db, $revUserField, $user );

            // Add actor migration parameters to select user
            $editsQueryInfo[ $queryId ][ 'tables' ] += $actorWhere[ 'tables' ];
            $editsQueryInfo[ $queryId ][ 'conds' ][] = $actorWhere[ 'conds' ];
            $editsQueryInfo[ $queryId ][ 'join_conds' ] += $actorWhere[ 'joins' ];

            // Depending on the configuration the revision table may need to join the page table
            $revisionJoinPage = false;

            $includeNamespaces = $this->getConfig( 'IncludeNamespaces' );

            if( $includeNamespaces !== '*' ) {
                if( !$includeNamespaces ) {
                    $includeNamespaces = $wgContentNamespaces;
                } elseif( !is_array( $includeNamespaces ) ) {
                    $includeNamespaces = [ $includeNamespaces ];
                }

                $listIncludeNamespaces = $db->makeList( $includeNamespaces );

                if( $queryInfo[ 'edits_table' ] === 'revision' ) {
                    // For the revision table, the namespace is determined by joining to the page table
                    $revisionJoinPage = true;

                    $editsQueryInfo[ $queryId ][ 'conds' ][] = 'page.page_namespace IN (' . $listIncludeNamespaces . ')';
                } else {
                    // For the archive table, the namespace is stored directly in the archive table
                    $editsQueryInfo[ $queryId ][ 'conds' ][] = 'archive.ar_namespace IN (' . $listIncludeNamespaces . ')';
                }
            }

            $includeRedirects = $this->getConfig( 'IncludeRedirects' );

            if( !$includeRedirects ) {
                if( $queryInfo[ 'edits_table' ] === 'revision' ) {
                    // For the revision table, redirects can be determined by joining to the page table
                    $revisionJoinPage = true;

                    $editsQueryInfo[ $queryId ][ 'conds' ][] = 'page.page_is_redirect = 0';
                }
                // TODO figure out how to determine if revisions in the archive table were redirects
            }

            if( $revisionJoinPage ) {
                $editsQueryInfo[ $queryId ][ 'tables' ][ 'page' ] = 'page';

                $editsQueryInfo[ $queryId ][ 'join_conds' ][ 'page' ] = [
                    'JOIN',
                    'page.page_id = revision.rev_page'
                ];
            }
        }

        $this->doGetEditsQueryInfo( $editsQueryInfo );

        return $editsQueryInfo;
    }

    protected function getEditsQueryInfoPrototype(): array {
        return [
            'tables' => [],
            'vars' => [],
            'conds' => [],
            'options' => [],
            'join_conds' => [],
            'edits_table' => '',
            'stat' => $this->getEditsStat(),
            'timestamp_var' => ''
        ];
    }
}