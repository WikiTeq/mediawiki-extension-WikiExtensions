( function ( $, mediaWiki ) {

	/**
	 * @constructor
	 */
	var WikiExtensions = function WikiExtensions( $element ) {
		this.$element = $element;
		this.api = new mw.Api();
		this.templateItem = mw.template.get( 'ext.wikiextensions.special', 'extension.mustache' );
		this.templateLoading = mw.template.get( 'ext.wikiextensions.special', 'loading.mustache' );
		this.init();
	};

	WikiExtensions.prototype.init = function () {
		this.$element.html( this.templateLoading.render() );
		this.api.get( {
			action: 'wikiextensions',
			do: 'list'
		} ).done( this.onDataLoaded.bind( this ) );
	};

	WikiExtensions.prototype.onDataLoaded = function ( data ) {
		var html, self = this;
		this.$element.html( '' );
		data.wikiextensions.extensions.map( function ( extension ) {
			html = self.getExtensionRowHtml( extension );
			self.$element.append( html );
		} );
	};

	WikiExtensions.prototype.getExtensionRowHtml = function ( extension ) {
		var html, status = 'green', message = 'No updates available';
		if ( !extension.can_pull ) {
			if ( extension.has_updates ) {
				status = 'red';
				message = 'No remote set, probably HEAD is detached';
			}
			if ( extension.has_uncommitted_changes ) {
				status = 'red';
				message = 'Has uncommitted changes';
			}
			if ( !extension.git.remote_commit ) {
				status = 'red';
				message = 'No remotes found or detached';
			}
			if ( !extension.is_writable ) {
				status = 'red';
				message = 'Directory is not writable';
			}
		}

		extension.status = status;
		extension.message = message;

		$.extend( extension, {
			_update: mw.msg('wikiextensions-special-update'),
			_notgit: mw.msg('wikiextensions-special-not-git')
		});

		html = this.templateItem.render( extension );
		$( html ).find( '.update-extension-button' ).bind( 'click', this.onUpdateClicked.bind( this ) );
		return html;
	};

	WikiExtensions.prototype.onUpdateClicked = function ( event ) {
		if ( $( event.target ).hasClass( 'btn-loading' ) ) {
			return false;
		}

		$( event.target ).addClass( 'btn-loading' );
		var extension = $( event.target ).data( 'extension' );
		this.api.get( {
			action: 'wikiextensions',
			do: 'update',
			extension: extension
		} ).done( function ( data ) {
			$( event.target ).removeClass( 'btn-loading' );
			this.onUpdateResult( data, extension );
		}.bind( this ) ).fail( function ( code, error ) {
			$( event.target ).removeClass( 'btn-loading' );
			this.onFailResult( code, error.error, extension );
		}.bind( this ) );
	};

	WikiExtensions.prototype.onFailResult = function ( code, error, extension ) {
		console.log( extension, code, error );
		alert( code + "\n" + error.info );
	};

	WikiExtensions.prototype.onUpdateResult = function ( data, extension ) {
		// TODO: rework and do some fallback to initial state for the row on error
		if ( data.wikiextensions.status !== 'OK' ) {
			alert( 'Something gone wrong, unable to update the extension. Changes were reverted.' );
			console.log( data.wikiextensions.status );
			return;
		}
		var row = this.$element.find( 'tr[data-extension="' + extension + '"]' );
		var newRow = this.getExtensionRowHtml( data.wikiextensions.extension );
		row.replaceWith( newRow );
		newRow.addClass( 'animation-new-in' );
	};

	window.WikiExtensions = WikiExtensions;

} )( jQuery, mediaWiki );
