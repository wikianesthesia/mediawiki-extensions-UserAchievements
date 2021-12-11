<?php


namespace MediaWiki\Extension\UserAchievements\Api;

use ApiBase;
use ApiModuleManager;
use MediaWiki\MediaWikiServices;

class ApiUserAchievements extends ApiBase {

    private const MODULE_GROUP = 'uaaction';

    /**
     * @var ApiModuleManager
     */
    private $moduleManager;

    private static $uaActions = [
        'editachievement' => ApiUserAchievementsEditAchievement::class,
        'purgeuserbadges' => ApiUserAchievementsPurgeUserBadges::class,
        'rebuildachievement' => ApiUserAchievementsRebuildAchievement::class,
        'tryachieve' => ApiUserAchievementsTryAchieve::class
    ];

    public function __construct( $main, $action ) {
        parent::__construct( $main, $action );

        $this->moduleManager = new ApiModuleManager(
            $this,
            MediaWikiServices::getInstance()->getObjectFactory()
        );

        $this->moduleManager->addModules( self::$uaActions, self::MODULE_GROUP );
    }

    public function getModuleManager() {
        return $this->moduleManager;
    }

    public function execute() {
        $this->getMain()->getVal( '_' );

        $params = $this->extractRequestParams();

        /** @var $module ApiUserAchievementsBase */
        $module = $this->moduleManager->getModule( $params[ self::MODULE_GROUP ], self::MODULE_GROUP );

        // The checks for POST and tokens are the same as ApiMain.php
        $wasPosted = $this->getRequest()->wasPosted();
        if ( !$wasPosted && $module->mustBePosted() ) {
            $this->dieWithErrorOrDebug( [ 'apierror-mustbeposted', $params[ self::MODULE_GROUP ] ] );
        }

        if ( $module->needsToken() ) {
            if ( !isset( $params[ 'token' ] ) ) {
                $this->dieWithError( [ 'apierror-missingparam', 'token' ] );
            }

            $module->requirePostedParameters( [ 'token' ] );

            if ( !$module->validateToken( $params[ 'token' ], $params ) ) {
                $this->dieWithError( 'apierror-badtoken' );
            }
        }

        $module->extractRequestParams();
        $module->execute();
    }

    /**
     * @inheritDoc
     */
    public function getAllowedParams() {
        return [
            self::MODULE_GROUP => [
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'submodule',
            ],
            'token' => ''
        ];
    }

    public function isWriteMode() {
        // We can't use extractRequestParams() here because getHelpFlags() calls this function,
        // and we'd error out because the group parameter isn't set.
        $moduleName = $this->getMain()->getVal( self::MODULE_GROUP );
        $module = $this->moduleManager->getModule( $moduleName, self::MODULE_GROUP );
        return $module ? $module->isWriteMode() : false;
    }

    public function mustBePosted() {
        return false;
    }

    public function needsToken() {
        return false;
    }
}