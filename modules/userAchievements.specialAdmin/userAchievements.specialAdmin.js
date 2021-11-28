( function () {

    if( typeof mw === 'undefined' || mw === null ) {
        throw "";
    }

    mw.userAchievements = mw.userAchievements || {};

    mw.userAchievements.specialAdmin = {
        postApi: function( apiData ) {
            apiData.action = 'userachievements';

            var api = new mw.Api();

            api.postWithEditToken( apiData ).done( function ( apiResult ) {
                console.log( apiResult );
            } );
        },
        initialize: function() {
            mw.userAchievements.specialAdmin.initializeForms();
        },
        initializeForms: function() {
            $( '.userachievements-admin-buttons-rebuildallachievements' ).click( function() {
                var apiData = {
                    'uaaction': 'rebuildachievement',
                    'achievement_id': '*'
                };

                mw.userAchievements.specialAdmin.postApi( apiData );
            } );

            $( '.userachievements-admin-buttons-purgealluserbadges' ).click( function() {
                var apiData = {
                    'uaaction': 'purgeuserbadges',
                    'achievement_id': '*'
                };

                mw.userAchievements.specialAdmin.postApi( apiData );
            } );

            $( '.userachievements-admin-achievement-enable' ).change( function() {
                var apiData = {
                    'uaaction': 'editachievement',
                    'achievement_id': $( this ).closest( 'form' ).find( '.userachievements-admin-achievement-achievement_id' ).val(),
                    'enabled': $( this ).is(':checked') ? 1 : 0
                };

                mw.userAchievements.specialAdmin.postApi( apiData );
            } );

            $( '.userachievements-admin-achievement-rebuildachievement' ).click( function() {
                var apiData = {
                    'uaaction': 'rebuildachievement',
                    'achievement_id': $( this ).closest( 'form' ).find( '.userachievements-admin-achievement-achievement_id' ).val()
                };

                mw.userAchievements.specialAdmin.postApi( apiData );
            } );

            $( '.userachievements-admin-achievement-purgeuserbadges' ).click( function() {
                var apiData = {
                    'uaaction': 'purgeuserbadges',
                    'achievement_id': $( this ).closest( 'form' ).find( '.userachievements-admin-achievement-achievement_id' ).val()
                };

                mw.userAchievements.specialAdmin.postApi( apiData );
            } );
        }
    };

    mw.userAchievements.specialAdmin.initialize();
}() );