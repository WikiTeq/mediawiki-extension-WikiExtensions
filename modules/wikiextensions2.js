( function ( $, mw, OO ) {

	/**
	 * @constructor
	 */
	var WikiExtensions2 = function WikiExtensions( $element ) {
		this.$element = $element;
		this.api = new mw.Api();
		this.wm = new OO.ui.WindowManager();
		this.init();
	};

	WikiExtensions2.prototype.PopupDialog = function( config ) {
		WikiExtensions2.prototype.PopupDialog.super.call( this, config );
	}

	OO.inheritClass( WikiExtensions2.prototype.PopupDialog, OO.ui.Dialog );
	WikiExtensions2.prototype.PopupDialog.static.name = 'popupDialog';

	WikiExtensions2.prototype.PopupDialog.prototype.initialize = function () {
		WikiExtensions2.prototype.PopupDialog.super.prototype.initialize.call( this );
		var self = this;
		this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
		this.content.$element.html( '<pre></pre>' );
		var closeButton = new OO.ui.ButtonWidget( {
			label: OO.ui.msg( 'ooui-dialog-process-dismiss' )
		} );
		closeButton.on( 'click', function () {
			self.close();
		} );
		this.content.$element.append( closeButton.$element );
		this.$body.append( this.content.$element );
	};

	WikiExtensions2.prototype.PopupDialog.prototype.getBodyHeight = function () {
		return this.content.$element.outerHeight( true );
	};

	WikiExtensions2.prototype.init = function () {
		var self = this, notice = {
			$element: $( mw.msg( 'wikiextensions-special-note-git' ) )
		};

		if ( OO.ui.hasOwnProperty( 'MessageWidget' ) ) {
			notice = new OO.ui.MessageWidget( {
				type: 'notice',
				label: new OO.ui.HtmlSnippet( mw.msg( 'wikiextensions-special-note-git' ) )
			} );
		}

		this.popupDialog = new WikiExtensions2.prototype.PopupDialog( {
			size: 'larger'
		} );
		$( document.body ).append( this.wm.$element );
		this.wm.addWindows( [ this.popupDialog ] );

		var fieldset = new OO.ui.FieldsetLayout();
		var progressBar = new OO.ui.ProgressBarWidget();
		fieldset.addItems( [
			new OO.ui.FieldLayout( progressBar, {
				label: mw.msg( 'wikiextensions-special-loading' ),
				align: 'top'
			} )
		] );
		this.$element.append( fieldset.$element );

		this.api.get( {
			action: 'wikiextensions',
			do: 'list'
		} ).done( function ( data ) {
			fieldset.$element.remove();
			this.stackLayout = new OO.ui.StackLayout( {
				expanded: false,
				continuous: true
			} );
			self.$element.append( notice.$element );
			self.$element.append( this.stackLayout.$element );
			self.onDataLoaded( data );
		} );
	};

	WikiExtensions2.prototype.onDataLoaded = function ( data ) {
		var self = this;
		data.wikiextensions.extensions.map( function ( extension ) {
			var statusLine;

			if ( !extension.is_git ) {
				return;
			}
			var button = new OO.ui.ButtonWidget( {
				label: mw.msg( 'wikiextensions-special-update' ),
				icon: 'reload',
				title: 'Update',
				flags: [ 'primary', 'progressive' ]
			} );
			var updateContentString = self.getContentString( extension );
			var InfoLine = new OO.ui.LabelWidget( {
				label: updateContentString,
				classes: [ 'extension-details-title' ]
			} );
			var detailsLink = new OO.ui.LabelWidget( {
				label: mw.msg( 'wikiextensions-special-details' ),
				classes: [ 'wikiextensions-details-link' ]
			} );
			var InfoStack = new OO.ui.StackLayout( {
				items: [
					new OO.ui.PanelLayout( {
						$content: extension.name,
						padded: false,
						framed: false,
						classes: [ 'extension-name-title' ]
					} ),
					new OO.ui.PanelLayout( {
						content: [
							new OO.ui.HorizontalLayout( {
								items: [
									new OO.ui.IconWidget( {
										icon: 'tag',
										title: 'Branch'
									} ),
									InfoLine
								]
							} )
						]
					} )
				],
				continuous: true,
				expanded: false,
				classes: [ 'extension-name' ]
			} );

			if ( OO.ui.hasOwnProperty( 'MessageWidget' ) ) {
				statusLine = new OO.ui.MessageWidget( {
					type: self.getStatus( extension ),
					inline: true,
					label: self.getMessage( extension )
				} );
			} else {
				statusLine = new OO.ui.LabelWidget( {
					label: self.getMessage( extension )
				} );
			}

			var layoutItems = [
				InfoStack,
				button,
				statusLine,
				detailsLink
			];

			detailsLink.$element.on('click', function(){
				self.popupDialog.content.$element.find('pre').html( extension.git.status.join("\n") );
				self.wm.openWindow( self.popupDialog );
			});

			detailsLink.toggle(false);

			if ( extension.can_pull ) {
				statusLine.toggle( false );
				button.setLabel(
					mw.msg( 'wikiextensions-special-update-to',
						extension.git.remote_commit_short
					) );
			} else {
				button.toggle( false );
				if ( extension.has_uncommitted_changes || extension.has_updates || extension.remote_commit ) {
					detailsLink.toggle( true );
				}
			}
			var layout = new OO.ui.HorizontalLayout( {
				items: layoutItems,
				classes: [ 'extension-layout' ]
			} );
			var panel = new OO.ui.PanelLayout( {
				content: [ layout ],
				padded: true,
				framed: true
			} );
			button.on( 'click', function () {
				panel.$element.addClass( 'loading-row' );
				button.setDisabled( true );
				self.UpdateClicked( extension, function ( data ) {
					button.setDisabled( false );
					button.toggle( false );
					panel.$element.removeClass( 'loading-row' );
					statusLine.toggle( true );

					if ( data.wikiextensions.status !== 'OK' ) {
						statusLine.setType( 'error' );
						statusLine.setLabel( data.wikiextensions.status );
						return;
					}

					if ( OO.ui.hasOwnProperty( 'MessageWidget' ) ) {
						statusLine.setType( 'success' );
					}
					statusLine.setLabel( 'Updated successfully!' );
					InfoLine.setLabel( self.getContentString( data.wikiextensions.extension ) );

				}, function ( code, error ) {
					panel.$element.removeClass( 'loading-row' );
					statusLine.toggle( true );
					button.toggle( false );
					if ( OO.ui.hasOwnProperty( 'MessageWidget' ) ) {
						statusLine.setType( 'error' );
					}
					statusLine.setLabel( error );
				} );
			} );
			this.stackLayout.addItems( [ panel ] );
		} );
	}

	WikiExtensions2.prototype.getContentString = function ( extension ) {
		var updateContentString = extension.version || '';

		updateContentString += ' ' + extension.git.branch || extension.git.local_commit_short;

		if ( extension.git.local_commit_short && extension.git.remote_commit_short ) {
			updateContentString += ' ( ';
			updateContentString += extension.git.local_commit_short;
			updateContentString += ' )';
		}

		return updateContentString;
	}

	WikiExtensions2.prototype.UpdateClicked = function ( extension, success, fail ) {
		this.api.get( {
			action: 'wikiextensions',
			do: 'update',
			extension: extension.name
		} ).done( function ( data ) {
			success( data );
		} ).fail( function ( code, error ) {
			fail( code, error.error );
		} );
	}

	WikiExtensions2.prototype.getStatus = function ( extension ) {
		var status = 'success';
		if ( !extension.can_pull ) {
			if ( extension.has_updates ) {
				status = 'warning';
			}
			if ( extension.has_uncommitted_changes ) {
				status = 'warning';
			}
			if ( !extension.git.remote_commit ) {
				status = 'warning';
			}
			if ( !extension.is_writable ) {
				status = 'warning';
			}
		}
		return status;
	}

	WikiExtensions2.prototype.getMessage = function ( extension ) {
		var message = mw.msg( 'wikiextensions-special-msg-no-updates' );
		if ( !extension.can_pull ) {
			if ( extension.has_updates ) {
				message = mw.msg( 'wikiextensions-special-msg-has_updates' );
			}
			if ( extension.has_uncommitted_changes ) {
				message = mw.msg( 'wikiextensions-special-msg-has_uncommitted_changes' );
			}
			if ( !extension.git.remote_commit ) {
				message = mw.msg( 'wikiextensions-special-msg-remote_commit' );
			}
			if ( !extension.is_writable ) {
				message = mw.msg( 'wikiextensions-special-msg-is_writable' );
			}
		}
		return message;
	}

	window.WikiExtensions2 = WikiExtensions2;

} )( jQuery, mediaWiki, OO );
