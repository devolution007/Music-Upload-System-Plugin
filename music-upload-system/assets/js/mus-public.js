( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var fileInput = document.getElementById( 'mus_song_file' );
		var form = fileInput ? fileInput.closest( 'form' ) : null;

		if ( ! fileInput || ! form ) {
			return;
		}

		var MAX_BYTES = 25 * 1024 * 1024;

		form.addEventListener( 'submit', function ( event ) {
			var file = fileInput.files && fileInput.files[ 0 ];

			if ( ! file ) {
				return;
			}

			var isMp3 = /\.mp3$/i.test( file.name ) || file.type === 'audio/mpeg';

			if ( ! isMp3 ) {
				event.preventDefault();
				window.alert( 'Please choose an MP3 audio file.' );
				return;
			}

			if ( file.size > MAX_BYTES ) {
				event.preventDefault();
				window.alert( 'File is too large. Maximum size is 25MB.' );
			}
		} );
	} );
} )();
