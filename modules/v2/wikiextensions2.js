( function ( $, mw, OO ) {

	/**
	 * @constructor
	 */
	var WikiExtensions2 = function WikiExtensions( $element ) {
		this.$element = $element;
		this.api = new mw.Api();
		this.init();
	};

	WikiExtensions2.prototype.init = function () {
		var self = this, notice = {
			$element: $('<p><strong>Note:</strong><br>Only extensions installed from GIT are listed below</p>')
		};

		if ( OO.ui.hasOwnProperty('MessageWidget') ) {
			notice = new OO.ui.MessageWidget( {
				type: 'notice',
				label: new OO.ui.HtmlSnippet( '<strong>Note:</strong><br>Only extensions installed from GIT are listed below' )
			} );
		}

		var fieldset = new OO.ui.FieldsetLayout();
		var progressBar = new OO.ui.ProgressBarWidget();
		fieldset.addItems( [
			new OO.ui.FieldLayout( progressBar, {
				label: 'Fetching extensions meta-data, please wait..',
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
		var self = this, statusLine;
		data.wikiextensions.extensions.map( function ( extension ) {
			if ( !extension.is_git ) {
				return;
			}
			var button = new OO.ui.ButtonWidget( {
				label: 'Update',
				icon: 'reload',
				title: 'Update',
				flags: [ 'primary', 'progressive' ]
			} );
			var updateContentString = self.getContentString( extension );
			var InfoLine = new OO.ui.LabelWidget( {
				label: updateContentString,
				classes: [ 'extension-details-title' ]
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

			if ( OO.ui.hasOwnProperty('MessageWidget') ) {
				statusLine = new OO.ui.MessageWidget( {
					type: self.getStatus( extension ),
					inline: true,
					label: self.getMessage( extension )
				} );
			}else{
				statusLine = new OO.ui.LabelWidget( {
					label: self.getMessage( extension )
				} );
			}

			var layoutItems = [
				InfoStack,
				button,
				statusLine
			];

			if ( extension.can_pull ) {
				statusLine.toggle( false );
			} else {
				button.toggle( false );
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
					console.log( data );

					if ( data.wikiextensions.status !== 'OK' ) {
						console.log( data.wikiextensions.status );
						statusLine.setType( 'error' );
						statusLine.setLabel( data.wikiextensions.status );
						return;
					}

					if ( OO.ui.hasOwnProperty('MessageWidget') ) {
						statusLine.setType( 'success' );
					}
					statusLine.setLabel( 'Updated successfully!' );
					InfoLine.setLabel( self.getContentString( data.wikiextensions.extension ) );

				}, function ( code, error ) {
					panel.$element.removeClass( 'loading-row' );
					statusLine.toggle( true );
					button.toggle( false );
					if ( OO.ui.hasOwnProperty('MessageWidget') ) {
						statusLine.setType( 'error' );
					}
					statusLine.setLabel( error );
					console.log( code, error );
				} );
			} );
			this.stackLayout.addItems( [ panel ] );
		} );
	}

	WikiExtensions2.prototype.getContentString = function( extension ) {
		var updateContentString = extension.version || '';

		updateContentString += ' ' + extension.git.branch || extension.git.local_commit_short;

		if ( extension.git.local_commit_short && extension.git.remote_commit_short ) {
			updateContentString += ' ( ';
			updateContentString += extension.git.local_commit_short;
			updateContentString += ' -> ';
			updateContentString += extension.git.remote_commit_short;
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
		var message = 'No updates available';
		if ( !extension.can_pull ) {
			if ( extension.has_updates ) {
				message = 'No remote set, probably HEAD is detached';
			}
			if ( extension.has_uncommitted_changes ) {
				message = 'Has uncommitted changes';
			}
			if ( !extension.git.remote_commit ) {
				message = 'No remotes found or detached';
			}
			if ( !extension.is_writable ) {
				message = 'Directory is not writable';
			}
		}
		return message;
	}

	window.WikiExtensions2 = WikiExtensions2;

} )( jQuery, mediaWiki, OO );
