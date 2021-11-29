<?php

namespace MediaWiki\Extension\UserAchievements\Achievement;

use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\UserAchievements\AbstractEditsAchievement;
use MediaWiki\Extension\UserAchievements\AchievementTrait;
use MediaWiki\Extension\UserAchievements\UserAchievements;
use MediaWiki\Extension\UserAchievements\UserStats;
use MWTimestamp;

class TalkEdits extends AbstractEditsAchievement {
    use AchievementTrait;

    protected function getEditsStat(): string {
        return 'talkEdits';
    }

    protected function doGetUserStats( UserStats &$userStats ) {
        global $wgNamespaceContentModels;

        $includeNamespacesConfigVar = 'IncludeNamespaces';

        $includeNamespaces = $this->getConfig( $includeNamespacesConfigVar );

        if( !$includeNamespaces || !is_array( $includeNamespaces ) ) {
            $includeNamespaces = [ NS_TALK ];
        }

        $defaultTalkNamespaces = [];
        $flowTalkNamespaces = [];

        foreach( $includeNamespaces as $namespace ) {
            // We need to generate separate queries for each type of content model. Thus, we will need to
            if( $wgNamespaceContentModels[ $namespace ] === 'flow-board' ) {
                $flowTalkNamespaces[] = $namespace;
            } else {
                $defaultTalkNamespaces[] = $namespace;
            }
        }

        if( count( $defaultTalkNamespaces ) ) {
            // Temporarily override the configuration value for the included namespaces
            $this->setConfig( $includeNamespacesConfigVar, $defaultTalkNamespaces );

            parent::doGetUserStats( $userStats );

            // Restore the correct configuration value for the included namespaces
            $this->setConfig( $includeNamespacesConfigVar, $includeNamespaces );
        }

        if( count( $flowTalkNamespaces ) ) {
            $db = UserAchievements::getDB();

            // TODO support filtering deleted posts based on achievement config var

            // Find all posts and replies attributed to the user
            $res = $db->select(
                'flow_revision',
                'rev_id',
                [
                    'rev_user_id' => $userStats->getUser()->getId(),
                    '(rev_change_type = \'new-post\' OR rev_change_type = \'reply\')',
                    'rev_mod_state' => ''
                ],
                __METHOD__
            );

            // Traverse the flow tree structure to find the workflow id
            while( $rowRevision = $res->fetchRow() ) {
                $revId = $rowRevision[ 'rev_id' ];
                $workflowId = null;

                $treeRevId = $revId;

                while( $treeRevId ) {
                    $resTree = $db->select(
                        'flow_tree_revision',
                        'tree_parent_id',
                        [
                            'tree_rev_id' => $treeRevId
                        ],
                        __METHOD__
                    );

                    $rowTree = $resTree->fetchRow();

                    if( !$rowTree || !$rowTree[ 'tree_parent_id' ] ) {
                        $workflowId = $treeRevId;
                        $treeRevId = null;
                    } else {
                        $treeRevId = $rowTree[ 'tree_parent_id' ];
                    }
                }

                // Look up the workflow to find the namespace of the talk page and make sure it is included
                if( $workflowId ) {
                    $resWorkflow = $db->select(
                        'flow_workflow',
                        'workflow_namespace',
                        [
                            'workflow_id' => $workflowId
                        ],
                        __METHOD__
                    );

                    $rowWorkflow = $resWorkflow->fetchRow();

                    if( $rowWorkflow && in_array( $rowWorkflow[ 'workflow_namespace' ], $flowTalkNamespaces ) ) {
                        // Extract the unix timestamp from the UID88
                        $timestamp = round( hexdec( substr( bin2hex( $revId ), 0, 12 ) ) / ( 4 * 1000 ) );
                        $time = MWTimestamp::convert( TS_MW, $timestamp );

                        $userStats->addEventTime( $this->getEditsStat(), $time );
                    }
                }
            }
        }
    }
}