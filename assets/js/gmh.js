/**
 * Admin results-list page behaviour: "Scan now" button + row actions
 * (Keep / Delete / Restore). Localized values (nonces, ajax URL,
 * translated strings) are injected via wp_localize_script - see Scripts::register_scripts().
 *
 * @package GhostMediaHunter
 */

/* global jQuery, gmhAdminMenu */

( function ( $ ) {
	'use strict';

	var config = window.gmhAdminMenu || {};

	$( function () {
		var $scanButton = $( '#gmh-scan-now' );
		var $scanStatus = $( '#gmh-scan-status' );

		$scanButton.on( 'click', function () {
			$scanButton.prop( 'disabled', true );
			$scanStatus.text( config.scanningText );

			$.post( config.ajaxUrl, {
				action: config.scanAction,
				_wpnonce: config.scanNonce,
			} )
				.done( function ( result ) {
					if ( result && result.success ) {
						window.location.reload();
						return;
					}
					$scanButton.prop( 'disabled', false );
					$scanStatus.text( ( result && result.data && result.data.message ) ? result.data.message : config.failedText );
				} )
				.fail( function () {
					$scanButton.prop( 'disabled', false );
					$scanStatus.text( config.failedText );
				} );
		} );

		// Row actions (Keep / Delete / Restore) - delegated to .wrap via
		// jQuery's .on(), so it works regardless of whether the table
		// rows exist in the DOM yet at the time this script runs.
		$( '.wrap' ).on( 'click', '.gmh-row-action', function () {
			var $btn = $( this );
			var confirmMsg = $btn.data( 'gmh-confirm' );

			if ( confirmMsg && ! window.confirm( confirmMsg ) ) {
				return;
			}

			var $row = $btn.closest( 'tr' );
			var $buttons = $row.length ? $row.find( '.gmh-row-action' ) : $btn;
			$buttons.prop( 'disabled', true );

			$.post( config.ajaxUrl, {
				action: $btn.data( 'gmh-action' ),
				_wpnonce: $btn.data( 'gmh-nonce' ),
				attachment_id: $btn.data( 'attachment-id' ),
			} )
				.done( function ( result ) {
					if ( result && result.success ) {
						$row.remove();
						return;
					}
					$buttons.prop( 'disabled', false );
					window.alert( ( result && result.data && result.data.message ) ? result.data.message : config.failedText );
				} )
				.fail( function () {
					$buttons.prop( 'disabled', false );
					window.alert( config.failedText );
				} );
		} );
	} );
} )( jQuery );
