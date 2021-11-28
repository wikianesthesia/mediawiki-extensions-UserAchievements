<?php


namespace MediaWiki\Extension\UserAchievements\Parser;

use Html;
use MediaWiki\Extension\UserAchievements\UserAchievements;
use Parser;
use PPFrame;
use Title;
use User;

class UserBadges {
    public static function render( $input, array $args, Parser $parser, PPFrame $frame ) {
        $user = isset( $args[ 'user' ] ) ? User::newFromName( $args[ 'user' ] ) : $parser->getUser();

        if( !$user->isRegistered() ) {
            return '';
        }

        // If card is true, wrap the userbadges in a card with a title. If false, just return a <div> with the badges.
        $card = isset( $args[ 'card' ] ) ? (bool) $args[ 'card' ] : true;

        $parser->getOutput()->addModules( 'ext.userAchievements.userBadges' );
        $templateParser = UserAchievements::getTemplateParser();
        $userBadges = UserAchievements::getUserBadges( $user, true );

        $userBadgeThumbnails = '';

        foreach( $userBadges as $userBadge ) {
            if( $userBadge->isEnabled() ) {
                $userBadgeThumbnails .= $userBadge->getThumbnailHtml( $user );
            }
        }

        $specialTitle = Title::newFromText( 'UserAchievements/' . $user->getName(), NS_SPECIAL );

        $userBadgesData = [
            'card' => $card,
            'title' => wfMessage( 'userachievements-userbadges-title' )->text(),
            'userbadges' => $userBadgeThumbnails,
            'progresslink' => $specialTitle->getLinkURL(),
            'progresslinktext' => wfMessage( 'userachievements-userbadges-progresslink' )->text()
        ];

        return [
            $templateParser->processTemplate( 'UserBadges', $userBadgesData ),
            'markerType' => 'nowiki'
        ];
    }
}