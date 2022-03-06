<?php

namespace MediaWiki\Extension\UserAchievements\Hook;

use MediaWiki\Extension\JsonClasses\Hook\JsonClassRegistrationHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\Extension\UserAchievements\UserAchievements;
use MediaWiki\Extension\UserAchievements\AchievementSchema;

class HookHandler implements
    JsonClassRegistrationHook,
    LoadExtensionSchemaUpdatesHook,
    ParserFirstCallInitHook {
    /**
     * @inheritDoc
     */
    public function onJsonClassRegistration( $classManager ) {
        $classManager->registerSchema( AchievementSchema::class );
        $classManager->loadClassDirectory( AchievementSchema::class, UserAchievements::getAchievementsLocalDirectory(), true );
    }

    /**
     * @inheritDoc
     */
    public function onLoadExtensionSchemaUpdates( $updater ) {
        # Make sure these are in the order you want them added to the database. The keys are the table names and the
        # values are any field in the table (used to see if the table is empty to insert the default data).
        $tableNames = [
            'userachievements_achievements' => 'achievement_id',
            'userachievements_userbadges' => 'achievement_id',
        ];

        $db = $updater->getDB();

        $sqlDir = __DIR__ . '/../../sql';

        # Create extension tables
        foreach( $tableNames as $tableName => $selectField) {
            if( file_exists( $sqlDir . "/tables/$tableName.sql" ) ) {
                $updater->addExtensionTable( $tableName, $sqlDir . "/tables/$tableName.sql" );

                # Import default data for tables if data exists
                if( file_exists( $sqlDir . "/data/$tableName.sql" ) ) {
                    $importTableData = false;

                    if( $updater->tableExists( $tableName ) ) {
                        $res = $db->select( $tableName, $selectField );

                        if( $res->numRows() === 0 ) {
                            $importTableData = true;
                        }
                    } else {
                        $importTableData = true;
                    }

                    if( $importTableData ) {
                        $updater->addExtensionUpdate( array( 'applyPatch', $sqlDir . "/data/$tableName.sql", true ) );
                    }
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function onParserFirstCallInit( $parser ) {
        $parser->setHook( 'userbadges', 'MediaWiki\\Extension\\UserAchievements\\Parser\\UserBadges::render' );
    }
}