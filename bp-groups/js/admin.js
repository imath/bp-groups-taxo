/*!
 * BP Groups Tag admin script
 */

;
( function( $ ) {

	if ( $( '#edittag' ).length ) {
		$( '#edittag' ).prop( 'action', BP_Groups_Tag_Admin.edit_action );
	}

	if ( $( '#addtag' ).length ) {
		$( '#addtag' ).prop( 'action', BP_Groups_Tag_Admin.edit_action );
		$( '#addtag input[name="screen"]' ).prop( 'value', BP_Groups_Tag_Admin.ajax_screen );
	}

	if ( $( '.search-form' ).length ) {
		$( '.search-form' ).append( '<input type="hidden" name="page" value="' + BP_Groups_Tag_Admin.search_page + '"/>' );
	}

	if ( $( '.wp-list-table .column-posts a' ).length ) {
		$.each( $( '.wp-list-table td.column-posts a' ), function( i, link ) {
			var href = $( link ).prop( 'href' ),
				found = href.split( BP_Groups_Tag_Admin.taxonomy + '=' )[1],
				tag = null;

			if ( !found ) {
				found = href.split( 'term=' )[1];
			}

			if ( found ) {
				tag = decodeURIComponent( found.split('&')[0] );
				$( link ).prop( 'href', BP_Groups_Tag_Admin.count_base_link + '&' + BP_Groups_Tag_Admin.taxonomy + '=' + tag );
			}

		} );
	}

})( jQuery );
