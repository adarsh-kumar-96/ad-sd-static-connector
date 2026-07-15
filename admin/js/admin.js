/* global adsdData, ADSDEditor */
/**
 * AD-SD WP Static Connector — Admin JS
 * Handles all tab interactions, AJAX, built-in code editor, SEO, layouts.
 */
( function ( $ ) {
	'use strict';

	var ADSD = {

		currentZipId    : 0,
		currentFilePath : '',
		currentDataUri  : '',
		currentBaseUrl  : '',
		editor          : null,
		editorTheme     : 'dark',
		originalContent : '',
		activePanel     : '', // 'editor' | 'seo'
		pendingGotoLine : 0,
		_previewTimer   : null, // FIX: debounce handle for updatePreview
		_previewBlobUrl : null, // FIX: current blob: URL for preview iframe (avoid srcdoc warning)

		/* ── Init ──────────────────────────────────────── */
		init: function () {
			ADSD.bindDashboard();
			ADSD.bindFileManager();
			ADSD.bindMapping();
			ADSD.bindBridge();
			ADSD.bindSettings();
			ADSD.bindModals();
			ADSD.loadLiveBadge();

			// Load tab-specific data on page load.
			var tab = ADSD.getCurrentTab();
			var urlParams = new URLSearchParams( window.location.search );
			var initZipId = parseInt( urlParams.get( 'zip_id' ) || 0, 10 );
			if ( tab === 'dashboard' )    { ADSD.loadZipCards(); }
			if ( tab === 'file-manager' ) {
				var openFile  = urlParams.get( 'open_file' );
				var gotoLine  = parseInt( urlParams.get( 'goto_line' ) || 0, 10 );
				ADSD.loadFmZipSelector( initZipId || undefined, openFile, gotoLine );
			}
			if ( tab === 'mapping' )      { ADSD.loadMappingZipSelector( initZipId || undefined ); ADSD.loadMappingLiveStatus(); }
			if ( tab === 'bridge' )       { ADSD.loadLayouts(); ADSD.loadBridgeLayoutSelector(); ADSD.loadFilterTermSuggestions( $( '#adsd-filter-post-type' ).val() || 'post' ); }
			if ( tab === 'settings' )     { ADSD.loadLogs(); ADSD.loadErrorLog(); }
		},

		getCurrentTab: function () {
			var params = new URLSearchParams( window.location.search );
			return params.get( 'tab' ) || 'dashboard';
		},

		/* ── AJAX helper ──────────────────────────────── */
		ajax: function ( action, data, callback ) {
			data = data || {};
			data.action = action;
			data.nonce  = adsdData.nonce;
			$.ajax( {
				url     : adsdData.ajaxUrl,
				type    : 'POST',
				data    : data,
				timeout : 30000,
				success : function ( res ) {
					if ( typeof callback === 'function' ) { callback( res ); }
				},
				error   : function ( xhr, status ) {
					var msg = status === 'timeout'
						? 'Request timed out. Please try again.'
						: 'Server error (' + ( xhr.status || 'unknown' ) + '). Please try again.';
					if ( typeof callback === 'function' ) {
						callback( { success: false, data: { message: msg } } );
					}
				}
			} );
		},

		/* ── Live Badge (header) ─────────────────────── */
		loadLiveBadge: function () {
			ADSD.ajax( 'adsd_get_live_info', {}, function ( res ) {
				var badge = $( '#adsd-live-badge' );
				if ( res.success && res.data.is_live ) {
					badge.removeClass( 'adsd-live-badge--off' ).addClass( 'adsd-live-badge--on' );
					badge.find( '.adsd-live-label' ).text( 'LIVE: ' + res.data.zip_name );
				} else {
					badge.removeClass( 'adsd-live-badge--on' ).addClass( 'adsd-live-badge--off' );
					badge.find( '.adsd-live-label' ).text( 'No Live Site' );
				}
			} );
		},

		/* ════════════════════════════════════════════════
		   DASHBOARD
		   ════════════════════════════════════════════════ */
		bindDashboard: function () {
			// Instructions toggle.
			$( document ).on( 'click', '.adsd-instructions-toggle', function () {
				$( this ).toggleClass( 'open' ).next( '.adsd-instructions-content' ).slideToggle( 200 );
			} );

			// Dropzone click — clicking anywhere on the dropzone (except the label)
			// also opens the file picker because the label is inside the dropzone.
			// The label#adsd-browse-btn with for="adsd-zip-input" handles the
			// file picker natively — no JS trigger needed (trigger() is blocked by browsers).
			$( document ).on( 'click', '#adsd-dropzone', function ( e ) {
				// If user clicked directly on label or its children, browser handles it natively.
				// If clicked elsewhere on dropzone, programmatically click the label.
				if ( ! $( e.target ).closest( '#adsd-browse-btn' ).length ) {
					document.getElementById( 'adsd-browse-btn' ).click();
				}
			} );

			// Drag & drop.
			$( document ).on( 'dragover dragenter', '#adsd-dropzone', function ( e ) {
				e.preventDefault();
				$( this ).addClass( 'drag-over' );
			} );
			$( document ).on( 'dragleave drop', '#adsd-dropzone', function ( e ) {
				e.preventDefault();
				$( this ).removeClass( 'drag-over' );
				if ( e.type === 'drop' ) {
					var files = e.originalEvent.dataTransfer.files;
					if ( files.length ) { ADSD.uploadZip( files[0] ); }
				}
			} );

			// File input change.
			$( document ).on( 'change', '#adsd-zip-input', function () {
				if ( this.files.length ) { ADSD.uploadZip( this.files[0] ); }
				this.value = '';
			} );

			// Refresh.
			$( document ).on( 'click', '#adsd-refresh-zips', function () {
				ADSD.loadZipCards();
			} );

			// ZIP card buttons (delegated).
			$( document ).on( 'click', '.adsd-zip-action-check',   ADSD.onCheckCode );
			$( document ).on( 'click', '.adsd-zip-action-edit',    ADSD.onEditZip );
			$( document ).on( 'click', '.adsd-zip-action-live',    ADSD.onGoLiveFromCard );
			$( document ).on( 'click', '.adsd-zip-action-stop',    ADSD.onStopLiveFromCard );
			$( document ).on( 'click', '.adsd-zip-action-delete',  ADSD.onDeleteZip );
		},

		uploadZip: function ( file ) {
			var formData = new FormData();
			formData.append( 'action',    'adsd_upload_zip' );
			formData.append( 'nonce',     adsdData.uploadNonce );
			formData.append( 'zip_file',  file );

			$( '#adsd-upload-progress' ).show();
			$( '#adsd-upload-result' ).empty();
			$( '#adsd-browse-btn' ).addClass( 'adsd-disabled' ).text( adsdData.i18n.uploading );

			$.ajax( {
				url        : adsdData.ajaxUrl,
				type       : 'POST',
				data       : formData,
				processData: false,
				contentType: false,
				xhr        : function () {
					var xhr = new window.XMLHttpRequest();
					xhr.upload.addEventListener( 'progress', function ( e ) {
						if ( e.lengthComputable ) {
							var pct = Math.round( ( e.loaded / e.total ) * 100 );
							$( '#adsd-progress-fill' ).css( 'width', pct + '%' );
							$( '#adsd-progress-text' ).text( adsdData.i18n.uploading + ' ' + pct + '%' );
						}
					} );
					return xhr;
				},
				success: function ( res ) {
					$( '#adsd-upload-progress' ).hide();
					$( '#adsd-browse-btn' ).removeClass( 'adsd-disabled' ).html( '<span class="dashicons dashicons-folder-open"></span> Browse Files' );
					if ( res.success ) {
						ADSD.showMsg( '#adsd-upload-result', 'success', '&#10003; ' + res.data.message );
						ADSD.loadZipCards();
					} else {
						ADSD.showMsg( '#adsd-upload-result', 'error', '&#10007; ' + ( res.data ? res.data.message : 'Upload failed.' ) );
					}
				},
				error: function () {
					$( '#adsd-upload-progress' ).hide();
					$( '#adsd-browse-btn' ).removeClass( 'adsd-disabled' ).html( '<span class="dashicons dashicons-folder-open"></span> Browse Files' );
					ADSD.showMsg( '#adsd-upload-result', 'error', 'Server error. Check your PHP upload limits.' );
				}
			} );
		},


		/* ── CONTAINER WIDTH LIVE PREVIEW ─────────────────────────────── */
		containerPreview: function () {
			var $box     = $( '#adsd-preview-box' );
			var $screen  = $( '.adsd-container-preview-screen' );
			var desktop  = parseInt( $( '#adsd_container_desktop' ).val() ) || 1200;
			var padding  = parseInt( $( '#adsd_container_padding' ).val() ) || 16;
			var screenW  = $screen.width() || 300;
			// Scale: preview box width as % of 1440px reference screen.
			var pct = Math.min( ( desktop / 1440 ) * 100, 100 );
			$box.css( { 'width': pct + '%' } );
			var marginTop = parseInt( $( '#adsd_container_margin_top' ).val() ) || 0;
			$box.css( 'margin-top', '' ); // preview doesn't shift, just show in label
			$box.find( 'span' ).text( desktop + 'px width, ' + padding + 'px pad, ' + marginTop + 'px top' );
		},

		loadZipCards: function () {
			ADSD.ajax( 'adsd_get_zip_files', {}, function ( res ) {
				var grid = $( '#adsd-zip-grid' );
				if ( ! res.success || ! res.data.zips.length ) {
					grid.html( '<div class="adsd-empty-state"><span class="dashicons dashicons-media-archive adsd-empty-icon"></span><p>No files uploaded yet. Upload your first ZIP above.</p></div>' );
					return;
				}
				var html = '';
				res.data.zips.forEach( function ( z ) {
					var isActive  = z.status === 'active';
					var statusBadge = isActive
						? '<span class="adsd-zip-status-badge adsd-zip-status-badge--active"><span class="dashicons dashicons-controls-play"></span>LIVE</span>'
						: '<span class="adsd-zip-status-badge adsd-zip-status-badge--inactive">Inactive</span>';
					html += '<div class="adsd-zip-card ' + ( isActive ? 'adsd-zip-card--active' : '' ) + '" data-id="' + z.id + '">';
					html += '<div class="adsd-zip-card-header">';
					html += '<span class="dashicons dashicons-media-archive adsd-zip-card-icon"></span>';
					html += '<div class="adsd-zip-card-info">';
					html += '<div class="adsd-zip-card-name">' + ADSD.esc( z.file_name ) + '</div>';
					html += '<div class="adsd-zip-card-meta">' + ADSD.esc( z.file_size ) + ' &bull; ' + ADSD.esc( z.uploaded_at ) + '</div>';
					html += '</div>' + statusBadge + '</div>';
					// Inline error indicator — filled by onCheckCode.
					html += '<div class="adsd-zip-card-errors" id="adsd-card-errors-' + z.id + '" style="display:none;"></div>';
					html += '<div class="adsd-zip-card-actions">';
					html += '<button class="adsd-btn adsd-btn--ghost adsd-btn--sm adsd-zip-action-check" data-id="' + z.id + '"><span class="dashicons dashicons-search"></span>Check Code</button>';
					html += '<button class="adsd-btn adsd-btn--ghost adsd-btn--sm adsd-zip-action-edit" data-id="' + z.id + '"><span class="dashicons dashicons-edit"></span>Edit</button>';
					if ( isActive ) {
						html += '<button class="adsd-btn adsd-btn--danger adsd-btn--sm adsd-zip-action-stop" data-id="' + z.id + '"><span class="dashicons dashicons-controls-pause"></span>Stop Live</button>';
					} else {
						html += '<button class="adsd-btn adsd-btn--success adsd-btn--sm adsd-zip-action-live" data-id="' + z.id + '"><span class="dashicons dashicons-controls-play"></span>Live</button>';
					}
					html += '<button class="adsd-btn adsd-btn--danger adsd-btn--sm adsd-zip-action-delete" data-id="' + z.id + '"><span class="dashicons dashicons-trash"></span>Delete</button>';
					html += '</div></div>';
				} );
				grid.html( html );
			} );
		},

		onCheckCode: function () {
			var id = $( this ).data( 'id' );
			$( '#adsd-code-check-modal' ).show();
			$( '#adsd-code-check-body' ).html( '<div class="adsd-spinner-wrap"><span class="adsd-spinner"></span><p style="margin-left:10px">' + adsdData.i18n.checking + '</p></div>' );
			ADSD.ajax( 'adsd_check_code', { zip_id: id }, function ( res ) {
				if ( ! res.success ) { $( '#adsd-code-check-body' ).html( '<p>' + ADSD.esc( res.data.message ) + '</p>' ); return; }
				var d = res.data;
				if ( ! d.count ) {
					$( '#adsd-code-check-body' ).html( '<div class="adsd-check-ok"><span class="dashicons dashicons-yes-alt"></span><p>' + adsdData.i18n.noErrors + '</p></div>' );
					// Clear any previous inline error badge on the card.
					$( '#adsd-card-errors-' + id ).hide().empty();
					return;
				}
				// Show inline red error badge on the card immediately.
				$( '#adsd-card-errors-' + id ).html(
					'<span class="adsd-card-error-badge"><span class="dashicons dashicons-warning"></span> ' +
					d.count + ' issue' + ( d.count !== 1 ? 's' : '' ) + ' found — click Check Code for details</span>'
				).show();
				var html = '<p style="margin-bottom:12px;font-weight:600;">Found <strong>' + d.count + '</strong> issue(s):</p><ul class="adsd-check-list">';
				d.errors.forEach( function ( e ) {
					var fixUrl = adsdData.siteUrl + '/wp-admin/admin.php?page=ad-sd-wsc&tab=file-manager&zip_id=' + id + '&open_file=' + encodeURIComponent( e.file ) + '&goto_line=' + e.line;
					html += '<li class="adsd-check-item adsd-check-item--' + ADSD.esc( e.type ) + '">';
					html += '<span class="adsd-check-item-icon">' + e.type.toUpperCase() + '</span>';
					html += '<div class="adsd-check-item-detail"><div class="adsd-check-item-file">' + ADSD.esc( e.file ) + '</div>';
					html += '<div class="adsd-check-item-msg">' + ADSD.esc( e.message ) + '</div>';
					html += '<div class="adsd-check-item-line">Line ' + e.line;
					html += ' &nbsp;<a class="adsd-fix-now-btn" href="' + fixUrl + '"><span class="dashicons dashicons-edit"></span> Fix Now</a>';
					html += '</div></div></li>';
				} );
				html += '</ul>';
				$( '#adsd-code-check-body' ).html( html );
			} );
		},

		onEditZip: function () {
			var id = $( this ).data( 'id' );
			window.location.href = adsdData.siteUrl + '/wp-admin/admin.php?page=ad-sd-wsc&tab=file-manager&zip_id=' + id;
		},

		onGoLiveFromCard: function () {
			var id = $( this ).data( 'id' );
			window.location.href = adsdData.siteUrl + '/wp-admin/admin.php?page=ad-sd-wsc&tab=mapping&zip_id=' + id;
		},

		onStopLiveFromCard: function () {
			if ( ! confirm( adsdData.i18n.confirmStopLive ) ) { return; }
			ADSD.ajax( 'adsd_stop_live', {}, function ( res ) {
				ADSD.loadZipCards();
				ADSD.loadLiveBadge();
				ADSD.showMsg( '#adsd-upload-result', res.success ? 'success' : 'error', res.data ? res.data.message : '' );
			} );
		},

		onDeleteZip: function () {
			if ( ! confirm( adsdData.i18n.confirmDelete ) ) { return; }
			var id = $( this ).data( 'id' );
			ADSD.ajax( 'adsd_delete_zip', { zip_id: id }, function ( res ) {
				ADSD.loadZipCards();
				ADSD.loadLiveBadge();
				ADSD.showMsg( '#adsd-upload-result', res.success ? 'success' : 'error', res.data ? res.data.message : 'Done.' );
			} );
		},

		/* ════════════════════════════════════════════════
		   FILE MANAGER
		   ════════════════════════════════════════════════ */
		bindFileManager: function () {
			$( document ).on( 'change', '#adsd-fm-zip-select', function () {
				var id = $( this ).val();
				ADSD.currentZipId = id ? parseInt( id, 10 ) : 0;
				if ( ADSD.currentZipId ) {
					$( '#adsd-fm-layout' ).show();
					ADSD.loadFileTree( ADSD.currentZipId );
					ADSD.showFmPanel( 'empty' );
				} else {
					$( '#adsd-fm-layout' ).hide();
				}
			} );

			$( document ).on( 'click', '#adsd-fm-refresh', function () {
				ADSD.loadFmZipSelector();
			} );

			// Click on file ROW = open editor directly.
			$( document ).on( 'click', '.adsd-file-item', function ( e ) {
				if ( $( e.target ).closest( '.adsd-file-item-actions' ).length ) { return; }
				var path = $( this ).data( 'path' );
				if ( path ) { ADSD.openEditor( ADSD.currentZipId, path ); }
			} );

			// Individual action buttons in tree.
			$( document ).on( 'click', '.adsd-file-item-btn--edit', function ( e ) {
				e.stopPropagation();
				var path = $( this ).data( 'path' ) || $( this ).closest( '.adsd-file-item' ).data( 'path' );
				ADSD.openEditor( ADSD.currentZipId, path );
			} );
			$( document ).on( 'click', '.adsd-file-item-btn--seo', function ( e ) {
				e.stopPropagation();
				var path = $( this ).data( 'path' ) || $( this ).closest( '.adsd-file-item' ).data( 'path' );
				ADSD.openSeo( ADSD.currentZipId, path );
			} );
			$( document ).on( 'click', '.adsd-file-item-btn--del', function ( e ) {
				e.stopPropagation();
				if ( ! confirm( adsdData.i18n.confirmDelete ) ) { return; }
				var path = $( this ).data( 'path' ) || $( this ).closest( '.adsd-file-item' ).data( 'path' );
				ADSD.ajax( 'adsd_delete_file', { zip_id: ADSD.currentZipId, file_path: path }, function ( res ) {
					if ( res.success ) { ADSD.loadFileTree( ADSD.currentZipId ); ADSD.showFmPanel( 'empty' ); }
					else { alert( res.data ? res.data.message : 'Delete failed.' ); }
				} );
			} );

			// Download file button.
			$( document ).on( 'click', '.adsd-file-item-btn--download', function ( e ) {
				e.stopPropagation();
				var path = $( this ).data( 'path' ) || $( this ).closest( '.adsd-file-item' ).data( 'path' );
				ADSD.ajax( 'adsd_download_file', { zip_id: ADSD.currentZipId, file_path: path }, function ( res ) {
					if ( res.success && res.data ) {
						// Decode base64 and trigger browser download.
						var byteChars = atob( res.data.content );
						var byteArr   = new Uint8Array( byteChars.length );
						for ( var i = 0; i < byteChars.length; i++ ) {
							byteArr[ i ] = byteChars.charCodeAt( i );
						}
						var blob = new Blob( [ byteArr ] );
						var url  = URL.createObjectURL( blob );
						var a    = document.createElement( 'a' );
						a.href     = url;
						a.download = res.data.file_name;
						document.body.appendChild( a );
						a.click();
						document.body.removeChild( a );
						URL.revokeObjectURL( url );
					} else {
						alert( res.data ? res.data.message : 'Download failed.' );
					}
				} );
			} );

			// New File button in sidebar header.
			$( document ).on( 'click', '#adsd-fm-new-file', function () {
				if ( ! ADSD.currentZipId ) {
					alert( 'Please select a ZIP first.' );
					return;
				}
				var fileName = prompt( 'Enter new file name (e.g. contact.html or pages/about.html):' );
				if ( ! fileName ) { return; }
				fileName = fileName.trim();
				if ( ! fileName ) { return; }
				ADSD.ajax( 'adsd_create_file', { zip_id: ADSD.currentZipId, file_path: fileName, content: '' }, function ( res ) {
					if ( res.success ) {
						ADSD.loadFileTree( ADSD.currentZipId );
						// Open the newly created file in editor after a short delay.
						setTimeout( function () {
							var newPath = res.data && res.data.file_path ? res.data.file_path : fileName;
							ADSD.openEditor( ADSD.currentZipId, newPath );
						}, 300 );
					} else {
						alert( res.data ? res.data.message : 'Could not create file.' );
					}
				} );
			} );

			// Sidebar collapse / expand.
			$( document ).on( 'click', '#adsd-fm-sidebar-toggle', function () {
				$( '#adsd-fm-sidebar' ).addClass( 'adsd-fm-sidebar--collapsed' );
				$( '#adsd-fm-sidebar-expand' ).show();
			} );
			$( document ).on( 'click', '#adsd-fm-sidebar-expand', function () {
				$( '#adsd-fm-sidebar' ).removeClass( 'adsd-fm-sidebar--collapsed' );
				$( this ).hide();
			} );


			$( document ).on( 'click', '#adsd-editor-save', ADSD.saveFile );

			/* ── Keyboard Shortcuts ── */
			$( document ).on( 'keydown', function ( e ) {
				// Only fire when file editor is active.
				if ( ! ADSD.editor ) { return; }
				var isMac  = navigator.platform.toUpperCase().indexOf( 'MAC' ) >= 0;
				var ctrlKey = isMac ? e.metaKey : e.ctrlKey;
				if ( ! ctrlKey ) { return; }

				// Ctrl/Cmd + S → Save
				if ( e.key === 's' || e.key === 'S' ) {
					e.preventDefault();
					ADSD.saveFile();
					return;
				}
				// Ctrl/Cmd + F → Find & Replace
				if ( e.key === 'f' || e.key === 'F' ) {
					// Don't intercept if focus is on browser's own elements outside editor.
					var tag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
					if ( tag === 'input' && document.activeElement.id !== 'adsd-edt-find-input' && document.activeElement.id !== 'adsd-edt-replace-input' ) { return; }
					e.preventDefault();
					$( '#adsd-edt-gotobar' ).hide();
					var $fb = $( '#adsd-edt-findbar' );
					$fb.toggle();
					if ( $fb.is( ':visible' ) ) {
						$( '#adsd-edt-find-result' ).text( '' );
						$( '#adsd-edt-find-input' ).trigger( 'focus' );
					}
					return;
				}
				// Ctrl/Cmd + G → Go to Line
				if ( e.key === 'g' || e.key === 'G' ) {
					e.preventDefault();
					$( '#adsd-edt-findbar' ).hide();
					var $gb = $( '#adsd-edt-gotobar' );
					$gb.toggle();
					if ( $gb.is( ':visible' ) ) { $( '#adsd-edt-goto-input' ).trigger( 'focus' ).trigger( 'select' ); }
					return;
				}
			} );
			// Escape → close find/goto bars and refocus editor
			$( document ).on( 'keydown', function ( e ) {
				if ( e.key !== 'Escape' ) { return; }
				var fbVisible = $( '#adsd-edt-findbar' ).is( ':visible' );
				var gbVisible = $( '#adsd-edt-gotobar' ).is( ':visible' );
				if ( fbVisible || gbVisible ) {
					$( '#adsd-edt-findbar, #adsd-edt-gotobar' ).hide();
					if ( ADSD.editor ) { ADSD.editor.focus(); }
				}
			} );
			$( document ).on( 'click', '#adsd-editor-reset', function () {
				if ( ADSD.editor ) { ADSD.editor.setValue( ADSD.originalContent ); ADSD.updatePreview(); }
			} );
			$( document ).on( 'click', '#adsd-editor-history', function () {
				ADSD.openVersionHistory( ADSD.currentZipId, ADSD.currentFilePath );
			} );
			$( document ).on( 'click', '#adsd-preview-refresh', function () {
				ADSD.updatePreview();
			} );

			/* ── Find & Replace ── */
			$( document ).on( 'click', '#adsd-editor-find', function () {
				$( '#adsd-edt-gotobar' ).hide();
				var $bar = $( '#adsd-edt-findbar' );
				$bar.toggle();
				if ( $bar.is( ':visible' ) ) {
					$( '#adsd-edt-find-result' ).text( '' );
					$( '#adsd-edt-find-input' ).trigger( 'focus' );
				}
			} );
			$( document ).on( 'click', '#adsd-edt-find-close', function () { $( '#adsd-edt-findbar' ).hide(); if ( ADSD.editor ) { ADSD.editor.focus(); } } );
			$( document ).on( 'click', '#adsd-edt-find-next', function () {
				if ( ! ADSD.editor ) { return; }
				var res = ADSD.editor.findReplace( { find: $( '#adsd-edt-find-input' ).val() } );
				$( '#adsd-edt-find-result' ).text( res.found ? '' : adsdData.i18n.notFound );
			} );
			$( document ).on( 'click', '#adsd-edt-replace-one', function () {
				if ( ! ADSD.editor ) { return; }
				var res = ADSD.editor.findReplace( { find: $( '#adsd-edt-find-input' ).val(), replace: $( '#adsd-edt-replace-input' ).val() } );
				$( '#adsd-edt-find-result' ).text( res.found ? '' : adsdData.i18n.notFound );
				ADSD.updatePreview();
			} );
			$( document ).on( 'click', '#adsd-edt-replace-all', function () {
				if ( ! ADSD.editor ) { return; }
				var res = ADSD.editor.findReplace( { find: $( '#adsd-edt-find-input' ).val(), replace: $( '#adsd-edt-replace-input' ).val(), mode: 'all' } );
				$( '#adsd-edt-find-result' ).text( res.count + ' ' + adsdData.i18n.replaced );
				ADSD.updatePreview();
			} );
			$( document ).on( 'keydown', '#adsd-edt-find-input, #adsd-edt-replace-input', function ( e ) {
				if ( e.key === 'Enter' ) { $( '#adsd-edt-find-next' ).trigger( 'click' ); }
			} );

			/* ── Go to line ── */
			$( document ).on( 'click', '#adsd-editor-goto', function () {
				$( '#adsd-edt-findbar' ).hide();
				var $bar = $( '#adsd-edt-gotobar' );
				$bar.toggle();
				if ( $bar.is( ':visible' ) ) { $( '#adsd-edt-goto-input' ).trigger( 'focus' ).trigger( 'select' ); }
			} );
			$( document ).on( 'click', '#adsd-edt-goto-close', function () { $( '#adsd-edt-gotobar' ).hide(); if ( ADSD.editor ) { ADSD.editor.focus(); } } );
			$( document ).on( 'click', '#adsd-edt-goto-go', function () {
				if ( ! ADSD.editor ) { return; }
				var ln = parseInt( $( '#adsd-edt-goto-input' ).val(), 10 );
				if ( ln > 0 ) { ADSD.editor.goToLine( ln ); }
			} );
			$( document ).on( 'keydown', '#adsd-edt-goto-input', function ( e ) {
				if ( e.key === 'Enter' ) { $( '#adsd-edt-goto-go' ).trigger( 'click' ); }
			} );

			/* ── Copy / Cut / Paste ── */
			$( document ).on( 'click', '#adsd-editor-copy', function () {
				if ( ! ADSD.editor ) { return; }
				ADSD.editor.copy().then( function () {
					ADSD.showEditorMsg( 'success', adsdData.i18n.copied );
				} ).catch( function () {
					ADSD.showEditorMsg( 'error', adsdData.i18n.clipboardError );
				} );
			} );
			$( document ).on( 'click', '#adsd-editor-cut', function () {
				if ( ! ADSD.editor ) { return; }
				ADSD.editor.cut().then( function () {
					ADSD.updatePreview();
					ADSD.showEditorMsg( 'success', adsdData.i18n.cut );
				} ).catch( function () {
					ADSD.showEditorMsg( 'error', adsdData.i18n.clipboardError );
				} );
			} );
			$( document ).on( 'click', '#adsd-editor-paste', function () {
				if ( ! ADSD.editor ) { return; }
				ADSD.editor.paste().then( function () {
					ADSD.updatePreview();
					ADSD.showEditorMsg( 'success', adsdData.i18n.pasted );
				} ).catch( function () {
					ADSD.showEditorMsg( 'error', adsdData.i18n.clipboardError );
				} );
			} );

			/* ── Check Code (basic client-side validation) ── */
			$( document ).on( 'click', '#adsd-editor-checkcode', function () {
				if ( ! ADSD.editor ) { return; }
				var issues = ADSD.runCodeCheck( ADSD.editor.getValue(), ADSD.currentFilePath.split( '.' ).pop().toLowerCase() );
				if ( ! issues.length ) {
					ADSD.showEditorMsg( 'success', adsdData.i18n.noErrors );
				} else {
					ADSD.showEditorMsg( 'error', issues.join( ' | ' ) );
				}
			} );

			/* ── Dark / Light theme toggle ── */
			$( document ).on( 'click', '#adsd-editor-theme', function () {
				ADSD.editorTheme = ( ADSD.editorTheme === 'dark' ) ? 'light' : 'dark';
				if ( ADSD.editor ) { ADSD.editor.setTheme( ADSD.editorTheme ); }
				var isDark = ADSD.editorTheme === 'dark';
				$( this ).find( '.dashicons' ).attr( 'class', 'dashicons ' + ( isDark ? 'dashicons-lightbulb' : 'dashicons-moon' ) );
				$( this ).contents().filter( function () { return this.nodeType === 3; } ).remove();
				$( this ).append( document.createTextNode( ' ' + ( isDark ? adsdData.i18n.lightMode : adsdData.i18n.darkMode ) ) );
				// If currently in full screen, apply the chosen theme to the
				// whole full-screen layout (sidebar + editor + preview), not
				// just the code editor.
				$( 'body' ).toggleClass( 'adsd-fullscreen-dark', isDark && $( 'body' ).hasClass( 'adsd-fullscreen-active' ) );
			} );

			/* ── Run (open live preview in a new tab) ── */
			$( document ).on( 'click', '#adsd-editor-run', function () {
				if ( ! ADSD.editor ) { return; }
				var ext = ADSD.currentFilePath.split( '.' ).pop().toLowerCase();
				if ( ext !== 'html' && ext !== 'htm' ) {
					ADSD.showEditorMsg( 'error', adsdData.i18n.runHtmlOnly );
					return;
				}
				var html = ADSD.injectBaseTag( ADSD.editor.getValue() );
				var blob = new Blob( [ html ], { type: 'text/html' } );
				var url  = URL.createObjectURL( blob );
				window.open( url, '_blank' );
				setTimeout( function () { URL.revokeObjectURL( url ); }, 30000 );
			} );

			/* ── Open Preview in New Tab (real server URL — avoids CORS/404 issues) ── */
			$( document ).on( 'click', '#adsd-editor-preview-newtab', function () {
				if ( ! ADSD.currentBaseUrl || ! ADSD.currentFilePath ) { return; }
				var fileName = ADSD.currentFilePath.split( '/' ).pop();
				window.open( ADSD.currentBaseUrl + fileName, '_blank' );
			} );

			/* ── Beautify (auto re-indent based on tag/brace nesting) ── */
			$( document ).on( 'click', '#adsd-editor-beautify', function () {
				if ( ! ADSD.editor ) { return; }
				var ok = ADSD.editor.beautify();
				if ( ok ) {
					ADSD.showEditorMsg( 'success', adsdData.i18n.beautifySuccess || 'Code beautified.' );
					ADSD.updatePreview();
				} else {
					ADSD.showEditorMsg( 'error', adsdData.i18n.beautifyUnsupported || 'Beautify supports HTML and CSS files.' );
				}
			} );

			/* ── Responsive preview: device size switcher ── */
			$( document ).on( 'click', '#adsd-preview-devices .adsd-device-btn', function () {
				var device = $( this ).data( 'device' );
				$( '#adsd-preview-devices .adsd-device-btn' ).removeClass( 'adsd-device-btn--active' );
				$( this ).addClass( 'adsd-device-btn--active' );
				$( '#adsd-preview-frame-wrap' )
					.removeClass( 'adsd-device--desktop adsd-device--tablet adsd-device--mobile' )
					.addClass( 'adsd-device--' + device );
			} );

			/* ── Fullscreen toggle ── */
			$( document ).on( 'click', '#adsd-editor-fullscreen', function () {
				// FIX: fullscreen now covers the entire file-manager layout
				// (file sidebar + editor + live preview), matching the
				// reference layout, instead of just the editor panel.
				var $layout = $( '#adsd-fm-layout' );
				var isFs    = $layout.hasClass( 'adsd-fm-layout--fullscreen' );
				if ( isFs ) {
					// Exit fullscreen.
					$layout.removeClass( 'adsd-fm-layout--fullscreen' );
					$( 'body' ).removeClass( 'adsd-fullscreen-active adsd-fullscreen-dark' );
					$( this ).find( '.dashicons' ).attr( 'class', 'dashicons dashicons-fullscreen-alt' );
					$( this ).find( '.adsd-btn-label' ).text( adsdData.i18n.fullscreen );
					// Restore WP admin nav bars.
					$( '#wpadminbar, #adminmenuwrap, #adminmenuback' ).show();
				} else {
					// Enter fullscreen.
					$layout.addClass( 'adsd-fm-layout--fullscreen' );
					$( 'body' ).addClass( 'adsd-fullscreen-active' );
					// If the editor is currently in dark mode, apply that
					// dark theme across the whole full-screen layout.
					$( 'body' ).toggleClass( 'adsd-fullscreen-dark', ADSD.editorTheme === 'dark' );
					$( this ).find( '.dashicons' ).attr( 'class', 'dashicons dashicons-fullscreen-exit-alt' );
					$( this ).find( '.adsd-btn-label' ).text( adsdData.i18n.exitFullscreen );
					// Hide WP admin chrome for clean full-screen experience.
					$( '#wpadminbar, #adminmenuwrap, #adminmenuback' ).hide();
				}
				// Refocus editor after resize.
				if ( ADSD.editor ) { setTimeout( function () { ADSD.editor.focus(); }, 50 ); }
			} );

			// ── Layout Orientation Switcher ──────────────────────────────────
			$( document ).on( 'click', '.adsd-layout-btn', function () {
				var layout   = $( this ).data( 'layout' );
				var $body    = $( '.adsd-editor-body' ).first();
				var $divider = $body.find( '.adsd-editor-divider' );

				// Remove all layout classes.
				$body.removeClass( 'layout--right-left layout--top-bottom layout--bottom-top' );

				// Reset inline sizes set by drag-resize.
				$body.find( '.adsd-editor-code-wrap, .adsd-editor-preview-wrap' ).css( { width: '', height: '' } );

				if ( layout === 'right-left' ) {
					$body.addClass( 'layout--right-left' );
					$divider.css( { cursor: 'col-resize', width: '', height: '' } );
				} else if ( layout === 'top-bottom' ) {
					$body.addClass( 'layout--top-bottom' );
					$divider.css( { cursor: 'row-resize', width: '100%', height: '6px' } );
				} else if ( layout === 'bottom-top' ) {
					$body.addClass( 'layout--bottom-top' );
					$divider.css( { cursor: 'row-resize', width: '100%', height: '6px' } );
				} else {
					// left-right (default) — no extra class needed.
					$divider.css( { cursor: 'col-resize', width: '', height: '' } );
				}

				// Update active button.
				$( '.adsd-layout-btn' ).removeClass( 'adsd-layout-btn--active' );
				$( this ).addClass( 'adsd-layout-btn--active' );

				// Save preference.
				if ( window.localStorage ) {
					localStorage.setItem( 'adsd_editor_layout', layout );
				}

				// Refresh CodeMirror so it recalculates gutter.
				if ( ADSD.editor ) { setTimeout( function () { ADSD.editor.renderHighlight(); ADSD.editor.updateGutter(); }, 80 ); }
			} );

			// Restore saved layout on page load (after editor ready).
			setTimeout( function () {
				if ( ! window.localStorage ) { return; }
				var saved = localStorage.getItem( 'adsd_editor_layout' );
				if ( saved && saved !== 'left-right' ) {
					$( '.adsd-layout-btn[data-layout="' + saved + '"]' ).trigger( 'click' );
				}
			}, 300 );

			// Exit fullscreen on Escape key.
			$( document ).on( 'keydown.adsd-fullscreen', function ( e ) {
				if ( e.key === 'Escape' && $( '#adsd-editor-panel' ).hasClass( 'adsd-editor-panel--fullscreen' ) ) {
					$( '#adsd-editor-fullscreen' ).trigger( 'click' );
				}
			} );

			// SEO actions.
			$( document ).on( 'click', '#adsd-seo-save', ADSD.saveSeo );
			$( document ).on( 'click', '#adsd-seo-cancel', function () { ADSD.showFmPanel( 'empty' ); } );
			$( document ).on( 'click', '.adsd-btn--auto-seo', function () {
				var field = $( this ).data( 'field' );
				$( this ).prop( 'disabled', true ).text( adsdData.i18n.generating );
				var self = this;
				ADSD.ajax( 'adsd_auto_seo', { zip_id: ADSD.currentZipId, file_path: ADSD.currentFilePath, field: field }, function ( res ) {
					$( self ).prop( 'disabled', false ).html( '<span class="dashicons dashicons-magic"></span> Auto Generate' );
					if ( res.success ) {
						$( '#adsd-seo-' + field ).val( res.data.value );
						ADSD.updateSeoCharCount( field );
						ADSD.updateSeoScorePreview();
					}
				} );
			} );

			// Char counts.
			$( document ).on( 'input', '.adsd-seo-input', function () {
				var name = $( this ).attr( 'name' );
				ADSD.updateSeoCharCount( name );
				ADSD.updateSeoScorePreview();
			} );

			// Pre-load zip if URL param exists.
			// zip_id URL param pre-selection is handled in init() via loadFmZipSelector().
		},

		loadFmZipSelector: function ( preselectId, openFile, gotoLine ) {
			ADSD.ajax( 'adsd_get_zip_files', {}, function ( res ) {
				var sel = $( '#adsd-fm-zip-select' );
				sel.find( 'option:not(:first)' ).remove();
				if ( res.success && res.data.zips && res.data.zips.length ) {
					res.data.zips.forEach( function ( z ) {
						var label = z.file_name + ' (' + z.uploaded_at + ')';
						var opt = $( '<option>' ).val( z.id ).text( label );
						sel.append( opt );
					} );
					var toSelect = preselectId || res.data.zips[0].id;
					ADSD.currentZipId = toSelect;
					sel.val( toSelect );
					$( '#adsd-fm-layout' ).show();
					// If Fix Now sent us here, open that file directly at the right line.
					if ( openFile ) {
						ADSD.loadFileTree( toSelect, openFile, gotoLine );
					} else {
						ADSD.loadFileTree( toSelect );
						ADSD.showFmPanel( 'empty' );
					}
				} else {
					$( '#adsd-fm-layout' ).hide();
				}
			} );
		},

		loadFileTree: function ( zipId, openFile, gotoLine ) {
			$( '#adsd-file-tree' ).html( '<div class="adsd-spinner-wrap"><span class="adsd-spinner"></span></div>' );
			ADSD.loadActualFileTree( zipId, openFile, gotoLine );
		},

		loadActualFileTree: function ( zipId, openFile, gotoLine ) {
			$.post( adsdData.ajaxUrl, {
				action   : 'adsd_get_file_content',
				nonce    : adsdData.nonce,
				zip_id   : zipId,
				file_path: '__ADSD_LIST__'
			}, function ( res ) {
				// FIX: handle both new wp_send_json_success envelope (res.data.files)
				// and legacy bare format (res.files) for backward compatibility.
				var files = ( res && res.success && res.data && res.data.files )
					? res.data.files
					: ( res && res.files ? res.files : [] );
				ADSD.buildFileTree( files, zipId );
				// Auto-open file from Fix Now redirect.
				if ( openFile ) {
					ADSD.openEditor( zipId, openFile, gotoLine );
				}
			} ).fail( function () {
				ADSD.buildFileTree( [], zipId );
			} );
		},

		buildFileTree: function ( files, zipId ) {
			if ( ! files || ! files.length ) {
				$( '#adsd-file-tree' ).html( '<p style="padding:14px;font-size:12.5px;color:#6b7280;">No files found. Make sure your ZIP extracted correctly.</p>' );
				return;
			}

			// Group files by folder for a folder-tree layout.
			var folders = {};
			files.forEach( function ( f ) {
				var parts = f.path.split( '/' );
				var folder = parts.length > 1 ? parts.slice( 0, -1 ).join( '/' ) : '__root__';
				if ( ! folders[ folder ] ) { folders[ folder ] = []; }
				folders[ folder ].push( f );
			} );

			var html = '';
			Object.keys( folders ).sort().forEach( function ( folder ) {
				if ( folder !== '__root__' ) {
					html += '<div class="adsd-folder-row"><span class="dashicons dashicons-category"></span>' + ADSD.esc( folder ) + '</div>';
				}
				folders[ folder ].forEach( function ( f ) {
					var icon   = ADSD.fileIcon( f.ext );
					var canSeo = ( f.ext === 'html' || f.ext === 'htm' );
					var indent = folder !== '__root__' ? ' adsd-file-item--indented' : '';
					html += '<div class="adsd-file-item' + indent + '" data-path="' + ADSD.esc( f.path ) + '" data-ext="' + ADSD.esc( f.ext ) + '" title="Click to edit: ' + ADSD.esc( f.path ) + '">';
					html += '<span class="dashicons ' + icon + ' adsd-file-item-icon"></span>';
					html += '<span class="adsd-file-item-name">' + ADSD.esc( f.name ) + '</span>';
					html += '<span class="adsd-file-item-actions">';
					html += '<button class="adsd-file-item-btn adsd-file-item-btn--edit" title="Edit file" data-path="' + ADSD.esc( f.path ) + '"><span class="dashicons dashicons-edit"></span></button>';
					if ( canSeo ) {
						html += '<button class="adsd-file-item-btn adsd-file-item-btn--seo" title="Manage SEO" data-path="' + ADSD.esc( f.path ) + '"><span class="dashicons dashicons-chart-line"></span></button>';
					}
					html += '<button class="adsd-file-item-btn adsd-file-item-btn--download" title="Download file" data-path="' + ADSD.esc( f.path ) + '"><span class="dashicons dashicons-download"></span></button>';
					html += '<button class="adsd-file-item-btn adsd-file-item-btn--del" title="Delete file" data-path="' + ADSD.esc( f.path ) + '"><span class="dashicons dashicons-trash"></span></button>';
					html += '</span>';
					html += '</div>';
				} );
			} );
			$( '#adsd-file-tree' ).html( html );
		},

		fileIcon: function ( ext ) {
			if ( ext === 'html' || ext === 'htm' ) { return 'dashicons-media-code'; }
			if ( ext === 'css' )  { return 'dashicons-editor-paste-text'; }
			if ( ext === 'js' )   { return 'dashicons-editor-code'; }
			if ( ['png','jpg','jpeg','gif','svg','webp'].indexOf( ext ) > -1 ) { return 'dashicons-format-image'; }
			if ( ['woff','woff2','ttf','eot'].indexOf( ext ) > -1 )           { return 'dashicons-editor-textcolor'; }
			return 'dashicons-media-default';
		},

		showFmPanel: function ( panel ) {
			ADSD.activePanel = panel;
			$( '#adsd-fm-empty' ).toggle( panel === 'empty' );
			$( '#adsd-editor-panel' ).toggle( panel === 'editor' );
			$( '#adsd-seo-panel' ).toggle( panel === 'seo' );
			if ( panel === 'empty' ) {
				$( '.adsd-file-item' ).removeClass( 'adsd-file-item--active' );
			}
		},

		openEditor: function ( zipId, filePath, gotoLine ) {
			if ( ! zipId || ! filePath ) {
				ADSD.showEditorMsg( 'error', 'No file selected.' );
				return;
			}
			ADSD.currentZipId    = zipId;
			ADSD.currentFilePath = filePath;
			ADSD.pendingGotoLine = gotoLine ? parseInt( gotoLine, 10 ) : 0;
			ADSD.showFmPanel( 'editor' );

			// Mark active in tree.
			$( '.adsd-file-item' ).removeClass( 'adsd-file-item--active' );
			$( '.adsd-file-item[data-path="' + filePath + '"]' ).addClass( 'adsd-file-item--active' );

			// Show loading state in editor area.
			$( '#adsd-editor-filename' ).text( filePath );
			$( '#adsd-editor-filesize' ).text( '' );
			$( '#adsd-editor-modified' ).text( '' );
			// FIX: previously this always replaced #adsd-monaco-editor's innerHTML
			// with a "Loading file..." placeholder. That destroyed the existing
			// editor's DOM (gutter/textarea) once ADSDEditor had been created,
			// so on the 2nd+ file the AJAX response landed in initEditor's
			// "reuse" branch (ADSD.editor.setValue) which updated detached nodes
			// no longer attached to the page — the visible panel stayed stuck on
			// the loading placeholder forever. Only show the placeholder before
			// the editor instance exists; otherwise just dim the existing editor.
			if ( ! ADSD.editor ) {
				$( '#adsd-monaco-editor' ).html( '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6b7280;gap:10px;"><span class="adsd-spinner"></span> Loading file...</div>' );
			} else {
				$( '#adsd-monaco-editor' ).css( 'opacity', '0.5' );
			}
			ADSD.showEditorMsg( '', '' );

			ADSD.ajax( 'adsd_get_file_content', { zip_id: zipId, file_path: filePath }, function ( res ) {
				$( '#adsd-monaco-editor' ).css( 'opacity', '' );
				if ( ! res.success ) {
					ADSD.showEditorMsg( 'error', res.data ? res.data.message : 'Could not load file. Please try again.' );
					$( '#adsd-monaco-editor' ).html( '' );
					return;
				}
				$( '#adsd-editor-filename' ).text( res.data.path );
				$( '#adsd-editor-filesize' ).text( res.data.size );
				$( '#adsd-editor-modified' ).text( 'Modified: ' + res.data.modified );

				if ( res.data.is_image ) {
					// Image file: nothing to edit — show image preview only.
					ADSD.originalContent = '';
					ADSD.currentDataUri  = res.data.data_uri;
					ADSD.showImagePreview( res.data.data_uri, res.data.path );
					return;
				}

				ADSD.currentDataUri  = '';
				ADSD.currentBaseUrl  = res.data.base_url || '';
				ADSD.originalContent = res.data.content;
				ADSD.initEditor( res.data.content, res.data.ext );
			} );
		},

		/**
		 * Create (once) or reuse the built-in ADSDEditor instance for the
		 * current file. Instant — no CDN, no loading delay.
		 */
		initEditor: function ( content, ext ) {
			// Re-enable toolbar buttons (may have been disabled by image preview).
			$( '#adsd-editor-save, #adsd-editor-find, #adsd-editor-reset, #adsd-editor-goto, #adsd-editor-copy, #adsd-editor-cut, #adsd-editor-paste, #adsd-editor-checkcode, #adsd-editor-beautify, #adsd-editor-run, #adsd-editor-fullscreen, #adsd-editor-preview-newtab, #adsd-editor-history' ).prop( 'disabled', false );

			var langMap = { html: 'html', htm: 'html', css: 'css', js: 'javascript', json: 'json', xml: 'xml', svg: 'xml', txt: 'plaintext' };
			var lang    = langMap[ ext ] || 'plaintext';

			var container = document.getElementById( 'adsd-monaco-editor' );
			if ( ! container ) { return; }

			if ( ! ADSD.editor ) {
				ADSD.editor = ADSDEditor.create( container, { value: content, language: lang, theme: ADSD.editorTheme || 'dark' } );
				ADSD.editor.onChange( function () {
					ADSD.updatePreview();
				} );
			} else {
				ADSD.editor.setValue( content );
				ADSD.editor.setLanguage( lang );
			}

			if ( ADSD.pendingGotoLine > 0 ) {
				var ln = ADSD.pendingGotoLine;
				ADSD.pendingGotoLine = 0;
				setTimeout( function () { ADSD.editor.goToLine( ln ); }, 30 );
			}

			ADSD.updatePreview();
		},

		showImagePreview: function ( dataUri, path ) {
			// Code editor stays empty for images (per requirement).
			if ( ADSD.editor ) {
				ADSD.editor.setValue( '' );
			} else {
				$( '#adsd-monaco-editor' ).html(
					'<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6b7280;flex-direction:column;gap:8px;text-align:center;padding:20px;">' +
						'<span class="dashicons dashicons-format-image" style="font-size:32px;width:32px;height:32px;"></span>' +
						'<span>' + ADSD.esc( path ) + '<br>' + ( adsdData.i18n.imageNoCode || 'Image files have no code to display. See preview on the right.' ) + '</span>' +
					'</div>'
				);
			}

			// Image preview, centered — use blob URL to avoid srcdoc warning.
			var html = '<div style="display:flex;align-items:center;justify-content:center;height:100%;width:100%;padding:16px;box-sizing:border-box;background:#f3f4f6;">' +
				'<img src="' + dataUri + '" alt="' + ADSD.esc( path ) + '" style="max-width:100%;max-height:100%;object-fit:contain;box-shadow:0 1px 4px rgba(0,0,0,.15);background:#fff;">' +
				'</div>';
			ADSD._setPreviewContent( html );

			// Disable editing actions for images — nothing to edit/save.
			$( '#adsd-editor-save, #adsd-editor-find, #adsd-editor-reset, #adsd-editor-goto, #adsd-editor-copy, #adsd-editor-cut, #adsd-editor-paste, #adsd-editor-checkcode, #adsd-editor-beautify, #adsd-editor-run, #adsd-editor-fullscreen, #adsd-editor-preview-newtab, #adsd-editor-history' ).prop( 'disabled', true );
			$( '#adsd-edt-findbar, #adsd-edt-gotobar' ).hide();
		},

		updatePreview: function () {
			// FIX: Debounce preview updates — previously every keystroke triggered
			// a full srcdoc rewrite on the iframe, causing significant lag especially
			// on the second file opened (iframe teardown + rebuild each time).
			if ( ADSD._previewTimer ) { clearTimeout( ADSD._previewTimer ); }
			ADSD._previewTimer = setTimeout( function () {
				ADSD._doUpdatePreview();
			}, 300 );
		},

		_doUpdatePreview: function () {
			if ( ! ADSD.editor ) { return; }
			var ext = ADSD.currentFilePath.split( '.' ).pop().toLowerCase();
			if ( ext !== 'html' && ext !== 'htm' ) {
				ADSD._setPreviewContent( '<div style="padding:20px;font-family:monospace;font-size:12px;color:#6b7280;">Preview available for HTML files only.</div>' );
				return;
			}
			var content = ADSD.injectBaseTag( ADSD.editor.getValue() );
			ADSD._setPreviewContent( content );
		},

		// Load preview via a blob: URL instead of srcdoc so the iframe has a
		// real origin (blob:) — this eliminates the browser warning:
		// "An iframe which has both allow-scripts and allow-same-origin can escape
		// its sandboxing." A blob: URL avoids the about:srcdoc origin entirely.
		_setPreviewContent: function ( html ) {
			var frame = document.getElementById( 'adsd-preview-frame' );
			if ( ! frame ) { return; }
			// Revoke previous blob URL to avoid memory leaks.
			if ( ADSD._previewBlobUrl ) {
				try { URL.revokeObjectURL( ADSD._previewBlobUrl ); } catch(e){}
				ADSD._previewBlobUrl = null;
			}
			try {
				var blob = new Blob( [ html ], { type: 'text/html' } );
				ADSD._previewBlobUrl = URL.createObjectURL( blob );
				frame.src = ADSD._previewBlobUrl;
			} catch ( e ) {
				// Blob API unavailable (very old browsers) — fall back to srcdoc.
				frame.removeAttribute( 'src' );
				frame.setAttribute( 'srcdoc', html );
			}
		},

		// Inject a <base> tag so relative asset URLs (css/js/images) resolve
		// against the ZIP's extracted folder instead of about:srcdoc (404s).
		// Also injects an XHR/fetch interceptor that silently blocks requests that
		// would fail with CORS errors from the srcdoc origin, eliminating the
		// "Cross origin requests … about:srcdoc" console errors.
		injectBaseTag: function ( html ) {
			var injections = '';

			// 1. Base tag for relative asset resolution (needs currentBaseUrl).
			if ( ADSD.currentBaseUrl ) {
				injections += '<base href="' + ADSD.currentBaseUrl + '">';
			}

			// 2. Suppress XHR/fetch CORS errors from scripts running inside srcdoc.
			//    jQuery / Swiper / other libs may fire $.ajax or fetch() calls that
			//    fail with "Cross origin requests only supported for http/https" because
			//    the srcdoc document has origin "about:srcdoc". We wrap XMLHttpRequest
			//    and fetch so those calls are silently swallowed in the preview.
			injections += '<script>' +
				'(function(){' +
					// Patch XMLHttpRequest — intercept open() calls to non-http(s) URLs
					// or same-page anchor calls that will CORS-fail in srcdoc context.
					'var _XHROpen = XMLHttpRequest.prototype.open;' +
					'XMLHttpRequest.prototype.open = function(m,u){' +
						'try{' +
							'var abs = new URL(u, location.href).href;' +
							'if(!/^https?:/.test(abs)){' +
								'this._adsd_blocked=true; return;' +
							'}' +
						'}catch(e){this._adsd_blocked=true;return;}' +
						'return _XHROpen.apply(this, arguments);' +
					'};' +
					'var _XHRSend = XMLHttpRequest.prototype.send;' +
					'XMLHttpRequest.prototype.send = function(){' +
						'if(this._adsd_blocked){return;}' +
						'return _XHRSend.apply(this, arguments);' +
					'};' +
					// Patch fetch — return empty rejected-then-swallowed promise for
					// requests that would fail from srcdoc.
					'var _fetch=window.fetch;' +
					'if(_fetch){' +
						'window.fetch=function(u,o){' +
							'try{' +
								'var abs=new URL(typeof u==="string"?u:u.url,location.href).href;' +
								'if(!/^https?:/.test(abs))return new Promise(function(){});' +
							'}catch(e){return new Promise(function(){});}' +
							'return _fetch.apply(this,arguments);' +
						'};' +
					'}' +
				'})();' +
			'<\/script>';

			// 3. Fix ADSD shortcode fetch in preview iframe.
			//    blob: URLs have origin "null" so window.location.origin returns "null",
			//    causing fetch("/adsd-sc/...") to fail silently. We inject the real WP
			//    site URL so ADSD shortcode divs load correctly in the live preview.
			var wpOrigin = ( adsdData && adsdData.siteUrl ) ? adsdData.siteUrl : '';
			if ( wpOrigin ) {
				injections += '<script>' +
					'(function(){' +
					// Wait for DOM ready then fix any already-present adsd-sc divs by
					// patching the adsdLoad function to use real WP origin.
					'window.__adsd_wp_origin=' + JSON.stringify( wpOrigin ) + ';' +
					// Override fetch inside iframe: rewrite /adsd-sc/... to absolute WP URL.
					'var _f=window.fetch;' +
					'window.fetch=function(u,o){' +
						'if(typeof u==="string"&&u.indexOf("/adsd-sc/")===0){' +
							'u=window.__adsd_wp_origin+u;' +
						'}' +
						'return _f.apply(this,arguments);' +
					'};' +
					// Also patch adsdLoad URL builder for scripts already on page.
					'window.__adsd_origin_patched=true;' +
					'})();' +
				'<\/script>';
			}

			if ( /<head[^>]*>/i.test( html ) ) {
				return html.replace( /<head[^>]*>/i, function ( m ) { return m + injections; } );
			}
			if ( /<html[^>]*>/i.test( html ) ) {
				return html.replace( /<html[^>]*>/i, function ( m ) { return m + '<head>' + injections + '</head>'; } );
			}
			return '<head>' + injections + '</head>' + html;
		},

		saveFile: function () {
			if ( ! ADSD.editor ) { return; }
			var content = ADSD.editor.getValue();
			$( '#adsd-editor-save' ).prop( 'disabled', true ).html( '<span class="dashicons dashicons-update adsd-spin"></span> ' + adsdData.i18n.saving );
			ADSD.ajax( 'adsd_save_file_content', { zip_id: ADSD.currentZipId, file_path: ADSD.currentFilePath, content: content }, function ( res ) {
				$( '#adsd-editor-save' ).prop( 'disabled', false ).html( '<span class="dashicons dashicons-saved"></span> Save' );
				ADSD.showEditorMsg( res.success ? 'success' : 'error', res.data ? res.data.message : '' );
				if ( res.success ) { ADSD.originalContent = content; }
			} );
		},

		/**
		 * Lightweight, dependency-free static checks for the current file.
		 * Returns an array of human-readable issue strings (empty = clean).
		 */
		runCodeCheck: function ( content, ext ) {
			var issues = [];

			var countOccurrences = function ( str, re ) {
				var m = str.match( re );
				return m ? m.length : 0;
			};

			if ( ext === 'html' || ext === 'htm' ) {
				var voidTags = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ];
				var openTags = [];
				var tagRe = /<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*?(\/?)>/g;
				var match;
				while ( ( match = tagRe.exec( content ) ) !== null ) {
					var tag    = match[ 1 ].toLowerCase();
					var closing = match[ 0 ].charAt( 1 ) === '/';
					var selfClosed = match[ 2 ] === '/' || voidTags.indexOf( tag ) !== -1;
					if ( selfClosed ) { continue; }
					if ( closing ) {
						var lastIdx = openTags.lastIndexOf( tag );
						if ( lastIdx === -1 ) {
							issues.push( 'Unexpected closing tag </' + tag + '>' );
						} else {
							openTags.splice( lastIdx, 1 );
						}
					} else {
						openTags.push( tag );
					}
				}
				if ( openTags.length ) {
					issues.push( 'Unclosed tag(s): <' + openTags.join( '>, <' ) + '>' );
				}
			}

			if ( ext === 'css' || ext === 'js' || ext === 'json' ) {
				var pairs = { '{': '}', '(': ')', '[': ']' };
				var stack = [];
				for ( var i = 0; i < content.length; i++ ) {
					var ch = content[ i ];
					if ( pairs[ ch ] ) {
						stack.push( pairs[ ch ] );
					} else if ( ch === '}' || ch === ')' || ch === ']' ) {
						if ( stack.pop() !== ch ) {
							issues.push( 'Mismatched "' + ch + '" near position ' + i );
							break;
						}
					}
				}
				if ( stack.length ) {
					issues.push( 'Missing closing ' + stack.join( ', ' ) );
				}
			}

			if ( ext === 'json' ) {
				try {
					JSON.parse( content );
				} catch ( e ) {
					issues.push( 'Invalid JSON: ' + e.message );
				}
			}

			// Generic: flag obviously broken inline event handlers / script tags left open.
			if ( countOccurrences( content, /<script\b/gi ) !== countOccurrences( content, /<\/script>/gi ) && ( ext === 'html' || ext === 'htm' ) ) {
				issues.push( 'Mismatched <script> / </script> tags' );
			}

			return issues;
		},

		showEditorMsg: function ( type, msg ) {
			var el = $( '#adsd-editor-msg' );
			el.removeClass( 'adsd-editor-msg--success adsd-editor-msg--error' ).hide();
			if ( type && msg ) {
				el.addClass( 'adsd-editor-msg--' + type ).text( msg ).show();
				setTimeout( function () { el.fadeOut(); }, 4000 );
			}
		},

		openVersionHistory: function ( zipId, filePath ) {
			$( '#adsd-version-modal' ).show();
			$( '#adsd-version-body' ).html( '<div class="adsd-spinner-wrap"><span class="adsd-spinner"></span></div>' );
			ADSD.ajax( 'adsd_get_version_history', { zip_id: zipId, file_path: filePath }, function ( res ) {
				if ( ! res.success || ! res.data.versions.length ) {
					$( '#adsd-version-body' ).html( '<p>No version history found for this file.</p>' );
					return;
				}
				var html = '<ul class="adsd-version-list">';
				res.data.versions.forEach( function ( v ) {
					html += '<li class="adsd-version-item">';
					html += '<div><div class="adsd-version-date">' + ADSD.esc( v.date ) + '</div><div class="adsd-version-size">' + ADSD.esc( v.size ) + '</div></div>';
					html += '<button class="adsd-btn adsd-btn--ghost adsd-btn--sm adsd-restore-version" data-ts="' + v.timestamp + '">Restore</button>';
					html += '</li>';
				} );
				html += '</ul>';
				$( '#adsd-version-body' ).html( html );
			} );
		},

		/* ── SEO ──────────────────────────────────────── */
		openSeo: function ( zipId, filePath ) {
			ADSD.currentZipId    = zipId;
			ADSD.currentFilePath = filePath;
			ADSD.showFmPanel( 'seo' );
			$( '.adsd-file-item[data-path="' + filePath + '"]' ).addClass( 'adsd-file-item--active' );
			$( '#adsd-seo-filename' ).text( filePath );

			ADSD.ajax( 'adsd_get_seo', { zip_id: zipId, file_path: filePath }, function ( res ) {
				if ( ! res.success ) { return; }
				var d = res.data || res;
				$( '#adsd-seo-seo_title'    ).val( d.seo_title     || '' );
				$( '#adsd-seo-meta_desc'    ).val( d.meta_desc      || '' );
				$( '#adsd-seo-meta_keywords').val( d.meta_keywords  || '' );
				$( '#adsd-seo-og_title'     ).val( d.og_title       || '' );
				$( '#adsd-seo-og_desc'      ).val( d.og_desc        || '' );
				$( '#adsd-seo-og_image'     ).val( d.og_image       || '' );
				$( '#adsd-seo-canonical'    ).val( d.canonical      || '' );
				$( '#adsd-seo-robots'       ).val( d.robots         || 'index, follow' );
				$( '#adsd-seo-schema_type'  ).val( d.schema_type    || 'WebPage' );
				ADSD.updateSeoScore( d.seo_score || 0 );
				[ 'seo_title', 'meta_desc', 'meta_keywords', 'og_title', 'og_desc' ].forEach( function ( f ) { ADSD.updateSeoCharCount( f ); } );
			} );
		},

		saveSeo: function () {
			var data = {};
			$( '.adsd-seo-input' ).each( function () { data[ $( this ).attr( 'name' ) ] = $( this ).val(); } );
			$( '#adsd-seo-save' ).prop( 'disabled', true ).text( adsdData.i18n.saving );
			ADSD.ajax( 'adsd_save_seo', { zip_id: ADSD.currentZipId, file_path: ADSD.currentFilePath, seo_data: data }, function ( res ) {
				$( '#adsd-seo-save' ).prop( 'disabled', false ).html( '<span class="dashicons dashicons-saved"></span> Save SEO Settings' );
				var el = $( '#adsd-seo-msg' );
				el.removeClass( 'adsd-editor-msg--success adsd-editor-msg--error' );
				if ( res.success ) {
					el.addClass( 'adsd-editor-msg--success' ).text( res.data.message ).show();
					ADSD.updateSeoScore( res.data.seo_score );
				} else {
					el.addClass( 'adsd-editor-msg--error' ).text( res.data ? res.data.message : 'Save failed.' ).show();
				}
				setTimeout( function () { el.fadeOut(); }, 4000 );
			} );
		},

		updateSeoCharCount: function ( field ) {
			var limits = { seo_title: [10,60], meta_desc: [50,155], og_title: [5,95], og_desc: [5,200], meta_keywords: [5,200] };
			var val = $( '#adsd-seo-' + field ).val() || '';
			var len = val.length;
			var el  = $( '#adsd-char-' + field );
			if ( ! el.length ) { return; }
			var lim = limits[ field ];
			var color = '#9ca3af';
			if ( lim ) {
				if ( len < lim[0] ) { color = '#d97706'; }
				else if ( len > lim[1] ) { color = '#dc2626'; }
				else { color = '#16a34a'; }
				el.text( len + ' / ' + lim[1] + ' chars' ).css( 'color', color );
			} else {
				el.text( len + ' chars' );
			}
		},

		updateSeoScorePreview: function () {
			var score = 0, total = 0;
			var fields = [ 'seo_title', 'meta_desc', 'meta_keywords', 'og_title', 'og_desc', 'og_image', 'canonical' ];
			fields.forEach( function ( f ) {
				var val = ( $( '#adsd-seo-' + f ).val() || '' ).trim();
				total += 14;
				if ( val.length > 5 ) { score += 14; }
			} );
			ADSD.updateSeoScore( Math.min( 100, Math.round( ( score / total ) * 100 ) ) );
		},

		updateSeoScore: function ( score ) {
			score = parseInt( score, 10 ) || 0;
			$( '#adsd-score-num' ).text( score );
			var circumference = 213.6;
			var offset = circumference - ( score / 100 ) * circumference;
			$( '#adsd-score-circle' ).attr( 'stroke-dasharray', ( circumference - offset ) + ' ' + circumference );
			var color = score < 40 ? '#dc2626' : ( score < 70 ? '#d97706' : '#16a34a' );
			$( '#adsd-score-circle' ).attr( 'stroke', color );
			$( '#adsd-score-num' ).css( 'color', color );
		},

		/* ════════════════════════════════════════════════
		   MAPPING
		   ════════════════════════════════════════════════ */
		bindMapping: function () {
			$( document ).on( 'change', '#adsd-mapping-zip', function () {
				var id = $( this ).val();
				if ( ! id ) { $( '#adsd-mapping-step2, #adsd-mapping-step3' ).hide(); return; }
				ADSD.loadMappingFiles( id );
			} );

			$( document ).on( 'change', '#adsd-mapping-home-file', function () {
				var val = $( this ).val();
				$( '#adsd-mapping-step3' ).toggle( !! val );
			} );

			$( document ).on( 'click', '#adsd-mapping-go-live', function () {
				if ( ! confirm( adsdData.i18n.confirmLive ) ) { return; }
				var zipId    = $( '#adsd-mapping-zip' ).val();
				var homeFile = $( '#adsd-mapping-home-file' ).val();
				$( this ).prop( 'disabled', true );
				ADSD.ajax( 'adsd_go_live', { zip_id: zipId, home_file: homeFile }, function ( res ) {
					$( '#adsd-mapping-go-live' ).prop( 'disabled', false );
					var el = $( '#adsd-mapping-msg' );
					el.removeClass( 'adsd-editor-msg--success adsd-editor-msg--error' );
					if ( res.success ) {
						el.addClass( 'adsd-editor-msg--success' ).text( res.data.message ).show();
						ADSD.loadLiveBadge();
						ADSD.loadMappingLiveStatus();
					} else {
						el.addClass( 'adsd-editor-msg--error' ).text( res.data ? res.data.message : 'Error.' ).show();
					}
				} );
			} );

			$( document ).on( 'click', '#adsd-mapping-stop-live', function () {
				if ( ! confirm( adsdData.i18n.confirmStopLive ) ) { return; }
				ADSD.ajax( 'adsd_stop_live', {}, function ( res ) {
					ADSD.loadLiveBadge();
					ADSD.loadMappingLiveStatus();
					ADSD.showMsg( '#adsd-mapping-msg', res.success ? 'success' : 'error', res.data ? res.data.message : '' );
				} );
			} );

			// zip_id URL param pre-selection handled in init().
		},

		loadMappingZipSelector: function ( preselectId ) {
			ADSD.ajax( 'adsd_get_zip_files', {}, function ( res ) {
				var sel = $( '#adsd-mapping-zip' );
				sel.find( 'option:not(:first)' ).remove();
				if ( res.success && res.data.zips.length ) {
					res.data.zips.forEach( function ( z ) {
						sel.append( $( '<option>' ).val( z.id ).text( z.file_name + ' (' + z.uploaded_at + ')' ) );
					} );
					if ( preselectId ) { sel.val( preselectId ).trigger( 'change' ); }
				}
			} );
		},

		loadMappingFiles: function ( zipId ) {
			var sel = $( '#adsd-mapping-home-file' );
			sel.find( 'option:not(:first)' ).remove();
			$.post( adsdData.ajaxUrl, {
				action   : 'adsd_get_file_content',
				nonce    : adsdData.nonce,
				zip_id   : zipId,
				file_path: '__ADSD_LIST__'
			}, function ( res ) {
				// FIX: handle both new wp_send_json_success envelope and legacy bare format.
				var files = ( res && res.success && res.data && res.data.files )
					? res.data.files
					: ( res && res.files ? res.files : [] );
				if ( files.length ) {
					files.forEach( function ( f ) {
						if ( f.ext === 'html' || f.ext === 'htm' ) {
							sel.append( $( '<option>' ).val( f.path ).text( f.path ) );
						}
					} );
				}
				$( '#adsd-mapping-step2' ).show();
				$( '#adsd-mapping-step3' ).hide();
			} );
		},

		loadMappingLiveStatus: function () {
			ADSD.ajax( 'adsd_get_live_info', {}, function ( res ) {
				var banner = $( '#adsd-mapping-live-banner' );
				if ( res.success && res.data.is_live ) {
					$( '#adsd-live-banner-detail' ).text( res.data.zip_name + ' → ' + res.data.home_file );
					banner.show();
				} else {
					banner.hide();
				}
			} );
		},

		/* ════════════════════════════════════════════════
		   SHORTCODE BRIDGE
		   ════════════════════════════════════════════════ */
		bindBridge: function () {

			/* ── Code History Helpers ──────────────────── */
			var ADSD_HISTORY_KEY = 'adsd_generated_codes_history';
			var ADSD_MAX_HISTORY = 20;

			function adsdGetHistory() {
				try {
					return JSON.parse( localStorage.getItem( ADSD_HISTORY_KEY ) || '[]' );
				} catch(e) { return []; }
			}

			function adsdSaveHistory( entries ) {
				try { localStorage.setItem( ADSD_HISTORY_KEY, JSON.stringify( entries ) ); } catch(e) {}
			}

			function adsdAddToHistory( type, label, code ) {
				var entries = adsdGetHistory();
				// Remove duplicate if same code already exists.
				entries = entries.filter( function(e) { return e.code !== code; } );
				entries.unshift( {
					id    : Date.now(),
					type  : type,
					label : label,
					code  : code,
					date  : new Date().toLocaleString()
				} );
				if ( entries.length > ADSD_MAX_HISTORY ) {
					entries = entries.slice( 0, ADSD_MAX_HISTORY );
				}
				adsdSaveHistory( entries );
				adsdRenderHistory();
			}

			function adsdRenderHistory() {
				var entries = adsdGetHistory();
				var $list   = $( '#adsd-history-list' );
				var $empty  = $( '#adsd-history-empty' );
				if ( ! $list.length ) { return; }

				// Remove old cards (not the empty notice).
				$list.find( '.adsd-history-card' ).remove();

				if ( entries.length === 0 ) {
					$empty.show();
					return;
				}
				$empty.hide();

				$.each( entries, function( i, entry ) {
					var typeLabel = entry.type === 'shortcode' ? '&#91;SC&#93;' : '&#91;Filter&#93;';
					var typeCls   = entry.type === 'shortcode' ? 'adsd-history-tag--sc' : 'adsd-history-tag--filter';
					var card = $( '<div class="adsd-history-card">' +
						'<div class="adsd-history-card-top">' +
							'<span class="adsd-history-tag ' + typeCls + '">' + typeLabel + '</span>' +
							'<span class="adsd-history-label">' + $('<span>').text(entry.label).html() + '</span>' +
							'<span class="adsd-history-date">' + $('<span>').text(entry.date).html() + '</span>' +
						'</div>' +
						'<pre class="adsd-history-preview">' + $('<span>').text(entry.code.substring(0,200)).html() + (entry.code.length>200?'…':'') + '</pre>' +
						'<div class="adsd-history-actions">' +
							'<button type="button" class="adsd-btn adsd-btn--primary adsd-btn--sm adsd-history-use-btn" data-idx="' + i + '"><span class="dashicons dashicons-controls-repeat"></span> Use Again</button>' +
							'<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm adsd-history-copy-btn" data-idx="' + i + '"><span class="dashicons dashicons-clipboard"></span> Copy</button>' +
							'<button type="button" class="adsd-btn adsd-btn--danger adsd-btn--sm adsd-history-delete-btn" data-idx="' + i + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>' +
						'</div>' +
					'</div>' );
					$list.append( card );
				} );
			}

			// Init history on load.
			adsdRenderHistory();

			// Use Again button.
			$( document ).on( 'click', '.adsd-history-use-btn', function () {
				var idx     = parseInt( $( this ).data( 'idx' ), 10 );
				var entries = adsdGetHistory();
				var entry   = entries[ idx ];
				if ( ! entry ) { return; }
				if ( entry.type === 'shortcode' ) {
					$( '#adsd-sc-code-block' ).text( entry.code );
					$( '#adsd-sc-output' ).show();
					$( '#adsd-sc-output' )[0].scrollIntoView( { behavior: 'smooth', block: 'start' } );
				} else {
					$( '#adsd-filter-code-block' ).text( entry.code );
					$( '#adsd-filter-output' ).show();
					$( '#adsd-filter-output' )[0].scrollIntoView( { behavior: 'smooth', block: 'start' } );
				}
			} );

			// Copy from history.
			$( document ).on( 'click', '.adsd-history-copy-btn', function () {
				var idx     = parseInt( $( this ).data( 'idx' ), 10 );
				var entries = adsdGetHistory();
				var entry   = entries[ idx ];
				if ( ! entry ) { return; }
				var self = this;
				var text = entry.code;

				function onHistCopy() {
					$( self ).text( 'Copied!' );
					setTimeout( function () { $( self ).html( '<span class="dashicons dashicons-clipboard"></span> Copy' ); }, 2000 );
				}
				function histFallback( str ) {
					var tmp = document.createElement( 'textarea' );
					tmp.value = str;
					tmp.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
					document.body.appendChild( tmp );
					tmp.focus(); tmp.select();
					try { document.execCommand( 'copy' ); onHistCopy(); } catch(e) {}
					document.body.removeChild( tmp );
				}

				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( text ).then( onHistCopy ).catch( function() { histFallback( text ); } );
				} else {
					histFallback( text );
				}
			} );

			// Delete single history entry.
			$( document ).on( 'click', '.adsd-history-delete-btn', function () {
				var idx     = parseInt( $( this ).data( 'idx' ), 10 );
				var entries = adsdGetHistory();
				entries.splice( idx, 1 );
				adsdSaveHistory( entries );
				adsdRenderHistory();
			} );

			// Clear all history.
			$( document ).on( 'click', '#adsd-clear-history-btn', function () {
				if ( ! confirm( 'Clear all generated code history? This cannot be undone.' ) ) { return; }
				adsdSaveHistory( [] );
				adsdRenderHistory();
			} );

			/* ── Shortcode Generator ───────────────────── */
			$( document ).on( 'click', '#adsd-gen-shortcode-btn', function () {
				var sc = $( '#adsd-shortcode-input' ).val().trim();
				if ( ! sc ) { alert( 'Please enter a shortcode.' ); return; }
				$( this ).prop( 'disabled', true ).text( adsdData.i18n.generating );
				var self = this;
				ADSD.ajax( 'adsd_gen_shortcode_div', { shortcode: sc }, function ( res ) {
					$( self ).prop( 'disabled', false ).html( '<span class="dashicons dashicons-controls-play"></span> Generate HTML Code' );
					if ( res.success ) {
						$( '#adsd-sc-code-block' ).text( res.data.code );
						$( '#adsd-sc-output' ).show();
						// Save to history.
						adsdAddToHistory( 'shortcode', sc, res.data.code );
					} else {
						alert( res.data ? res.data.message : 'Error.' );
					}
				} );
			} );

			$( document ).on( 'change', '#adsd-filter-post-type', function () {
				$( '#adsd-woo-filters' ).toggle( $( this ).val() === 'product' );
				ADSD.loadFilterTermSuggestions( $( this ).val() );
			} );

			$( document ).on( 'click', '#adsd-gen-filter-btn', function () {
				var filters = {
					post_type    : $( '#adsd-filter-post-type' ).val(),
					layout_id    : $( '#adsd-filter-layout' ).val(),
					count        : $( '#adsd-filter-count' ).val(),
					columns      : parseInt( $( '#adsd-filter-columns' ).val() || 3, 10 ),
					category     : $( '#adsd-filter-category' ).val(),
					tag          : $( '#adsd-filter-tag' ).val(),
					orderby      : $( '#adsd-filter-orderby' ).val(),
					order        : $( '#adsd-filter-order' ).val(),
					only_featured: $( '#adsd-filter-featured' ).is( ':checked' ) ? 1 : 0,
					only_sale    : $( '#adsd-filter-sale' ).is( ':checked' ) ? 1 : 0,
					min_rating   : parseInt( $( '#adsd-filter-min-rating' ).val() || 0, 10 )
				};
				$( this ).prop( 'disabled', true ).text( adsdData.i18n.generating );
				var self = this;
				ADSD.ajax( 'adsd_gen_filter_div', { filters: filters }, function ( res ) {
					$( self ).prop( 'disabled', false ).html( '<span class="dashicons dashicons-editor-code"></span> Generate Filter Code' );
					if ( res.success ) {
						$( '#adsd-filter-code-block' ).text( res.data.code );
						$( '#adsd-filter-output' ).show();
						// Save to history.
						var label = 'Type: ' + filters.post_type + ', Count: ' + filters.count;
						adsdAddToHistory( 'filter', label, res.data.code );
					}
				} );
			} );

			// Copy buttons.
			$( document ).on( 'click', '.adsd-copy-btn', function () {
				var target  = $( this ).data( 'target' );
				var text    = $( '#' + target ).text();
				var self    = this;

				function onSuccess() {
					$( self ).text( adsdData.i18n.copied );
					setTimeout( function () { $( self ).html( '<span class="dashicons dashicons-clipboard"></span> Copy Code' ); }, 2000 );
				}

				function fallbackCopy( str ) {
					var tmp = document.createElement( 'textarea' );
					tmp.value = str;
					tmp.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
					document.body.appendChild( tmp );
					tmp.focus();
					tmp.select();
					try {
						var ok = document.execCommand( 'copy' );
						document.body.removeChild( tmp );
						if ( ok ) { onSuccess(); } else { alert( adsdData.i18n.copyFailed ); }
					} catch(e) {
						document.body.removeChild( tmp );
						alert( adsdData.i18n.copyFailed );
					}
				}

				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( text ).then( onSuccess ).catch( function() {
						fallbackCopy( text );
					} );
				} else {
					fallbackCopy( text );
				}
			} );

			// Layout CRUD.
			var _layoutEditor = null; // ADSDEditor instance for layout modal.
			var _livePreviewTimer = null;
			var _livePreviewBlobUrl = null;

			/**
			 * Build a full standalone HTML preview document from a layout
			 * template, substituting placeholders with sample data. Shared by
			 * the "Preview" button on saved layout cards and the live preview
			 * inside the Create/Edit Layout modal.
			 */
			function buildLayoutPreviewHtml( template ) {
				var preview = ( template || '' )
					.replace( /\{\{post_title\}\}|\{\{product_name\}\}/g, 'Sample Product Name' )
					.replace( /\{\{post_excerpt\}\}|\{\{product_short_desc\}\}/g, 'This is a short product description.' )
					.replace( /\{\{post_url\}\}|\{\{product_url\}\}/g, '#' )
					.replace( /\{\{post_thumbnail\}\}|\{\{product_image\}\}/g, 'https://via.placeholder.com/300x200?text=Image' )
					.replace( /\{\{post_category\}\}/g, 'Category' )
					.replace( /\{\{product_price\}\}/g, '<strong>$29.99</strong>' )
					.replace( /\{\{product_rating\}\}/g, '4.5/5' )
					.replace( /\{\{product_sku\}\}/g, 'SKU-001' );
				// Wrap in the same grid container the live frontend uses (2 sample
				// cards) so grid + position:relative issues are visible here too,
				// not just after publishing.
				var itemWrap = '<div style="position:relative;">' + preview + '</div>';
				return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' +
					'body{font-family:sans-serif;padding:20px;background:#f9f9f9;margin:0;}' +
					'img{max-width:100%;height:auto;}' +
					'.adsd-lyt-preview-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;}' +
					'</style></head><body><div class="adsd-lyt-preview-grid">' + itemWrap + itemWrap + '</div></body></html>';
			}

			function renderLivePreview() {
				var template = _layoutEditor ? _layoutEditor.getValue() : $( '#adsd-layout-template' ).val();
				var fullHtml = buildLayoutPreviewHtml( template );
				var blob = new Blob( [ fullHtml ], { type: 'text/html' } );
				var url  = URL.createObjectURL( blob );
				var old  = _livePreviewBlobUrl;
				$( '#adsd-layout-live-preview-frame' ).attr( 'src', url );
				_livePreviewBlobUrl = url;
				if ( old ) { URL.revokeObjectURL( old ); }
			}

			function openLayoutModal( id, name, plugin, template, title ) {
				$( '#adsd-layout-id' ).val( id || '' );
				$( '#adsd-layout-name' ).val( name || '' );
				$( '#adsd-layout-plugin' ).val( plugin || 'generic' );
				$( '#adsd-layout-template' ).val( template || '' );
				$( '#adsd-layout-modal-title' ).text( title || 'Create Custom Layout' );
				$( '#adsd-layout-msg' ).hide().text( '' );
				$( '#adsd-lyt-edt-findbar, #adsd-lyt-edt-gotobar' ).hide();
				$( '#adsd-layout-modal' ).show();
				// Init or reuse ADSDEditor for layout template field. Gets its own
				// 'adsd-edt--layout' class so we can tune its CSS (see
				// adsd-editor.css) without touching the File Manager editor.
				var container = document.getElementById( 'adsd-layout-editor-wrap' );
				if ( container ) {
					if ( ! _layoutEditor ) {
						_layoutEditor = ADSDEditor.create( container, { value: template || '', language: 'html', theme: 'dark' } );
						container.classList.add( 'adsd-edt--layout' );
						_layoutEditor.onChange( function () {
							clearTimeout( _livePreviewTimer );
							_livePreviewTimer = setTimeout( renderLivePreview, 400 );
						} );
					} else {
						_layoutEditor.setValue( template || '' );
					}
				}
				renderLivePreview();
			}

			$( document ).on( 'click', '#adsd-layout-modal .adsd-modal-close, #adsd-layout-modal .adsd-modal-overlay', function () {
				if ( _livePreviewBlobUrl ) { URL.revokeObjectURL( _livePreviewBlobUrl ); _livePreviewBlobUrl = null; }
				$( '#adsd-layout-live-preview-frame' ).attr( 'src', 'about:blank' );
			} );

			$( document ).on( 'click', '#adsd-new-layout-btn', function () {
				openLayoutModal( '', '', 'generic', '', 'Create Custom Layout' );
			} );

			$( document ).on( 'click', '.adsd-layout-preview-btn', function () {
				var card     = $( this ).closest( '.adsd-layout-card' );
				var template = card.data( 'template' );
				var name     = card.data( 'name' );
				var fullHtml = buildLayoutPreviewHtml( template );
				$( '#adsd-layout-preview-modal-title' ).text( 'Preview: ' + name );
				var blob = new Blob( [ fullHtml ], { type: 'text/html' } );
				var url  = URL.createObjectURL( blob );
				$( '#adsd-layout-preview-frame' ).attr( 'src', url );
				$( '#adsd-layout-preview-modal' ).show();
			} );

			$( document ).on( 'click', '#adsd-layout-preview-modal .adsd-modal-close, #adsd-layout-preview-modal .adsd-modal-overlay', function () {
				$( '#adsd-layout-preview-modal' ).hide();
				$( '#adsd-layout-preview-frame' ).attr( 'src', 'about:blank' );
			} );

			$( document ).on( 'click', '.adsd-layout-edit-btn', function () {
				var card = $( this ).closest( '.adsd-layout-card' );
				openLayoutModal(
					card.data( 'id' ),
					card.data( 'name' ),
					card.data( 'plugin' ),
					card.data( 'template' ),
					'Edit Layout'
				);
			} );

			$( document ).on( 'click', '#adsd-layout-save', function () {
				// Read template from ADSDEditor if available, else fallback to textarea.
				var templateVal = _layoutEditor ? _layoutEditor.getValue() : $( '#adsd-layout-template' ).val();
				var data = {
					id         : $( '#adsd-layout-id' ).val(),
					layout_name: $( '#adsd-layout-name' ).val(),
					plugin_type: $( '#adsd-layout-plugin' ).val(),
					template   : templateVal
				};
				$( this ).prop( 'disabled', true ).text( adsdData.i18n.saving );
				var self = this;
				ADSD.ajax( 'adsd_save_layout', { layout: data }, function ( res ) {
					$( self ).prop( 'disabled', false ).html( '<span class="dashicons dashicons-saved"></span> Save Layout' );
					var el = $( '#adsd-layout-msg' );
					if ( res.success ) {
						el.addClass( 'adsd-editor-msg--success' ).text( res.data.message ).show();
						setTimeout( function () { $( '#adsd-layout-modal' ).hide(); ADSD.loadLayouts(); ADSD.loadBridgeLayoutSelector(); }, 1200 );
					} else {
						el.addClass( 'adsd-editor-msg--error' ).text( res.data ? res.data.message : 'Save failed.' ).show();
					}
				} );
			} );

			$( document ).on( 'click', '.adsd-layout-delete-btn', function () {
				if ( ! confirm( adsdData.i18n.confirmDelete ) ) { return; }
				var id = $( this ).closest( '.adsd-layout-card' ).data( 'id' );
				ADSD.ajax( 'adsd_delete_layout', { layout_id: id }, function ( res ) {
					if ( res.success ) { ADSD.loadLayouts(); ADSD.loadBridgeLayoutSelector(); }
					else { alert( res.data ? res.data.message : 'Delete failed.' ); }
				} );
			} );

			$( document ).on( 'click', '.adsd-layout-reset-btn', function () {
				var name = $( this ).closest( '.adsd-layout-card' ).data( 'layout-name' );
				if ( ! name ) { alert( 'Cannot identify layout.' ); return; }
				if ( ! confirm( 'Reset "' + name + '" to its original template?' ) ) { return; }
				var $btn = $( this ).prop( 'disabled', true ).text( 'Resetting…' );
				ADSD.ajax( 'adsd_reset_layout', { layout_name: name }, function ( res ) {
					$btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-image-rotate"></span>Reset' );
					if ( res.success ) {
						ADSD.loadLayouts();
					} else {
						alert( res.data ? res.data.message : 'Reset failed.' );
					}
				} );
			} );

			// Placeholder chip insertion.
			$( document ).on( 'click', '.adsd-suggestion-chip', function () {
				var ph  = $( this ).data( 'placeholder' );
				if ( _layoutEditor ) {
					// Insert at the ADSDEditor's caret when it's the active editor.
					var ta  = _layoutEditor.textarea;
					var pos = ta.selectionStart;
					var val = ta.value;
					_layoutEditor.setValue( val.substring( 0, pos ) + ph + val.substring( pos ) );
					ta.selectionStart = ta.selectionEnd = pos + ph.length;
					_layoutEditor.focus();
					_layoutEditor.changeCbs.forEach( function ( cb ) { cb(); } );
				} else {
					var $ta  = $( '#adsd-layout-template' );
					var $pos = $ta[0].selectionStart;
					var $val = $ta.val();
					$ta.val( $val.substring( 0, $pos ) + ph + $val.substring( $pos ) );
					$ta[0].selectionStart = $ta[0].selectionEnd = $pos + ph.length;
					$ta.focus();
				}
			} );

			/* ── Layout editor toolbar: Find & Replace ── */
			$( document ).on( 'click', '#adsd-lyt-edt-find', function () {
				$( '#adsd-lyt-edt-gotobar' ).hide();
				var $bar = $( '#adsd-lyt-edt-findbar' );
				$bar.toggle();
				if ( $bar.is( ':visible' ) ) {
					$( '#adsd-lyt-edt-find-result' ).text( '' );
					$( '#adsd-lyt-edt-find-input' ).trigger( 'focus' );
				}
			} );
			$( document ).on( 'click', '#adsd-lyt-edt-find-close', function () { $( '#adsd-lyt-edt-findbar' ).hide(); if ( _layoutEditor ) { _layoutEditor.focus(); } } );
			$( document ).on( 'click', '#adsd-lyt-edt-find-next', function () {
				if ( ! _layoutEditor ) { return; }
				var res = _layoutEditor.findReplace( { find: $( '#adsd-lyt-edt-find-input' ).val() } );
				$( '#adsd-lyt-edt-find-result' ).text( res.found ? '' : adsdData.i18n.notFound );
			} );
			$( document ).on( 'click', '#adsd-lyt-edt-replace-one', function () {
				if ( ! _layoutEditor ) { return; }
				var res = _layoutEditor.findReplace( { find: $( '#adsd-lyt-edt-find-input' ).val(), replace: $( '#adsd-lyt-edt-replace-input' ).val() } );
				$( '#adsd-lyt-edt-find-result' ).text( res.found ? '' : adsdData.i18n.notFound );
			} );
			$( document ).on( 'click', '#adsd-lyt-edt-replace-all', function () {
				if ( ! _layoutEditor ) { return; }
				var res = _layoutEditor.findReplace( { find: $( '#adsd-lyt-edt-find-input' ).val(), replace: $( '#adsd-lyt-edt-replace-input' ).val(), mode: 'all' } );
				$( '#adsd-lyt-edt-find-result' ).text( res.count + ' ' + adsdData.i18n.replaced );
			} );
			$( document ).on( 'keydown', '#adsd-lyt-edt-find-input, #adsd-lyt-edt-replace-input', function ( e ) {
				if ( e.key === 'Enter' ) { $( '#adsd-lyt-edt-find-next' ).trigger( 'click' ); }
			} );

			/* ── Layout editor toolbar: Go to Line ── */
			$( document ).on( 'click', '#adsd-lyt-edt-goto', function () {
				$( '#adsd-lyt-edt-findbar' ).hide();
				var $bar = $( '#adsd-lyt-edt-gotobar' );
				$bar.toggle();
				if ( $bar.is( ':visible' ) ) { $( '#adsd-lyt-edt-goto-input' ).trigger( 'focus' ).trigger( 'select' ); }
			} );
			$( document ).on( 'click', '#adsd-lyt-edt-goto-close', function () { $( '#adsd-lyt-edt-gotobar' ).hide(); if ( _layoutEditor ) { _layoutEditor.focus(); } } );
			$( document ).on( 'click', '#adsd-lyt-edt-goto-go', function () {
				if ( ! _layoutEditor ) { return; }
				var ln = parseInt( $( '#adsd-lyt-edt-goto-input' ).val(), 10 );
				if ( ln > 0 ) { _layoutEditor.goToLine( ln ); }
			} );
			$( document ).on( 'keydown', '#adsd-lyt-edt-goto-input', function ( e ) {
				if ( e.key === 'Enter' ) { $( '#adsd-lyt-edt-goto-go' ).trigger( 'click' ); }
			} );

			/* ── Layout editor toolbar: Beautify ── */
			$( document ).on( 'click', '#adsd-lyt-edt-beautify', function () {
				if ( ! _layoutEditor ) { return; }
				_layoutEditor.beautify();
				renderLivePreview();
			} );
		},

		loadLayouts: function () {
			$( '#adsd-layout-grid' ).html( '<div class="adsd-spinner-wrap"><span class="adsd-spinner"></span></div>' );
			ADSD.ajax( 'adsd_get_layouts', {}, function ( res ) {
				if ( ! res.success || ! res.data.layouts.length ) {
					$( '#adsd-layout-grid' ).html( '<p>No layouts found.</p>' );
					return;
				}
				var html = '';
				res.data.layouts.forEach( function ( l ) {
					var isPreset   = l.layout_type === 'preset';
					var creatorBadge = isPreset
						? '<span class="adsd-badge adsd-badge--blue">Pre-built</span>'
						: '<span class="adsd-badge adsd-badge--purple">Custom (ID: ' + l.id + ')</span>';
					var pluginBadge = '<span class="adsd-badge adsd-badge--gray">' + ADSD.esc( l.plugin_type ) + '</span>';
					html += '<div class="adsd-layout-card" data-id="' + l.id + '" data-name="' + ADSD.esc( l.layout_name ) + '" data-layout-name="' + ADSD.esc( l.layout_name ) + '" data-plugin="' + ADSD.esc( l.plugin_type ) + '" data-template="' + ADSD.esc( l.template ) + '">';
					html += '<div class="adsd-layout-card-header"><div><div class="adsd-layout-card-name">' + ADSD.esc( l.layout_name ) + '</div>';
					html += '<div class="adsd-layout-card-meta">' + creatorBadge + ' ' + pluginBadge + '</div></div></div>';
					html += '<div class="adsd-layout-card-body">';
					html += '<div class="adsd-layout-card-preview">' + ADSD.esc( l.template.substring( 0, 80 ) ) + '...</div>';
					html += '<div class="adsd-layout-card-actions">';
					if ( ! isPreset ) {
						html += '<button class="adsd-btn adsd-btn--ghost adsd-btn--sm adsd-layout-preview-btn"><span class="dashicons dashicons-visibility"></span>Preview</button>';
						html += '<button class="adsd-btn adsd-btn--ghost adsd-btn--sm adsd-layout-edit-btn"><span class="dashicons dashicons-edit"></span>Edit</button>';
						html += '<button class="adsd-btn adsd-btn--danger adsd-btn--sm adsd-layout-delete-btn"><span class="dashicons dashicons-trash"></span>Delete</button>';
					} else {
						html += '<button class="adsd-btn adsd-btn--ghost adsd-btn--sm adsd-layout-preview-btn"><span class="dashicons dashicons-visibility"></span>Preview</button>';
						html += '<button class="adsd-btn adsd-btn--ghost adsd-btn--sm adsd-layout-edit-btn"><span class="dashicons dashicons-edit"></span>Edit</button>';
						html += '<button class="adsd-btn adsd-btn--ghost adsd-btn--sm adsd-layout-reset-btn"><span class="dashicons dashicons-image-rotate"></span>Reset</button>';
					}
					html += '</div></div></div>';
				} );
				$( '#adsd-layout-grid' ).html( html );
			} );
		},

		loadBridgeLayoutSelector: function () {
			ADSD.ajax( 'adsd_get_layouts', {}, function ( res ) {
				var sel = $( '#adsd-filter-layout' );
				sel.find( 'option:not(:first)' ).remove();
				if ( res.success ) {
					res.data.layouts.forEach( function ( l ) {
						sel.append( $( '<option>' ).val( l.id ).text( l.layout_name ) );
					} );
				}
			} );
		},

		/**
		 * Populate the Category/Tag <datalist> suggestions for the Filter
		 * Block builder, based on the currently selected post type — works
		 * for built-in post types AND custom post types (ACF/CPT UI etc.).
		 */
		loadFilterTermSuggestions: function ( postType ) {
			if ( ! postType ) { return; }
			ADSD.ajax( 'adsd_get_terms', { post_type: postType }, function ( res ) {
				var $catList = $( '#adsd-filter-category-list' );
				var $tagList = $( '#adsd-filter-tag-list' );
				$catList.empty();
				$tagList.empty();
				if ( ! res.success ) { return; }
				( res.data.categories || [] ).forEach( function ( t ) {
					$catList.append( $( '<option>' ).attr( 'value', t.slug ).attr( 'label', t.name + ' (' + t.slug + ')' ) );
				} );
				( res.data.tags || [] ).forEach( function ( t ) {
					$tagList.append( $( '<option>' ).attr( 'value', t.slug ).attr( 'label', t.name + ' (' + t.slug + ')' ) );
				} );
			} );
		},

		/* ════════════════════════════════════════════════
		   SETTINGS
		   ════════════════════════════════════════════════ */
		bindSettings: function () {
			// Post template toggle — show/hide options.
			$( document ).on( 'change', '#adsd_post_template_enabled', function () {
				$( '#adsd-post-tpl-options' ).toggle( this.checked );
			} );
			$( document ).on( 'change', '#adsd_post_show_related', function () {
				$( '#adsd-related-count-row' ).toggle( this.checked );
			} );
			// Init state on page load.
			if ( $( '#adsd_post_template_enabled' ).length ) {
				var ptEnabled = $( '#adsd_post_template_enabled' ).is( ':checked' );
				$( '#adsd-post-tpl-options' ).toggle( ptEnabled );
				var relEnabled = $( '#adsd_post_show_related' ).is( ':checked' );
				$( '#adsd-related-count-row' ).toggle( relEnabled );
			}

			// Container width live preview.
			$( document ).on( 'input change', '#adsd_container_desktop, #adsd_container_tablet, #adsd_container_mobile, #adsd_container_padding, #adsd_container_margin_top', function () {
				ADSD.containerPreview();
			} );
			$( document ).on( 'change', '#adsd_container_enabled', function () {
				$( '#adsd-container-devices' ).toggle( this.checked );
				$( '#adsd-container-preview' ).toggle( this.checked );
				if ( this.checked ) { ADSD.containerPreview(); }
			} );
			// Init container preview state on tab load.
			if ( $( '#adsd_container_enabled' ).length ) {
				var cEnabled = $( '#adsd_container_enabled' ).is( ':checked' );
				$( '#adsd-container-devices' ).toggle( cEnabled );
				$( '#adsd-container-preview' ).toggle( cEnabled );
				if ( cEnabled ) { ADSD.containerPreview(); }
			}

			$( document ).on( 'click', '#adsd-save-settings', function () {
				var data = {
					max_zip_size_mb   : $( '#adsd-setting-max-size' ).val(),
					manager_can_layout: $( '#adsd-setting-manager-layout' ).is( ':checked' ) ? 1 : 0,
					allowed_shortcodes: $( '#adsd-setting-allowed-sc' ).val()
				};
				$( this ).prop( 'disabled', true ).text( adsdData.i18n.saving );
				var self = this;
				ADSD.ajax( 'adsd_save_settings', data, function ( res ) {
					$( self ).prop( 'disabled', false ).html( '<span class="dashicons dashicons-saved"></span> Save Settings' );
					ADSD.showMsg( '#adsd-settings-msg', res.success ? 'success' : 'error', res.data ? res.data.message : '' );
				} );
			} );

			$( document ).on( 'click', '#adsd-refresh-logs', function () { ADSD.loadLogs(); } );

			// Error log handlers.
			$( document ).on( 'click', '#adsd-refresh-errors', function () { ADSD.loadErrorLog(); } );
			$( document ).on( 'click', '#adsd-clear-errors', function () {
				if ( ! confirm( 'Are you sure you want to clear the entire debug log?' ) ) { return; }
				ADSD.ajax( 'adsd_clear_error_log', {}, function ( res ) {
					if ( res.success ) {
						$( '#adsd-error-log-wrap' ).html( '<span style="color:#888;">Log cleared.</span>' );
					} else {
						$( '#adsd-error-log-wrap' ).html( '<span style="color:#e53e3e;">' + ADSD.esc( res.data ? res.data.message : 'Could not clear log.' ) + '</span>' );
					}
				} );
			} );
		},

		loadLogs: function () {
			$( '#adsd-log-table-wrap' ).html( '<div class="adsd-spinner-wrap"><span class="adsd-spinner"></span></div>' );
			ADSD.ajax( 'adsd_get_logs', {}, function ( res ) {
				if ( ! res.success || ! res.data.logs.length ) {
					$( '#adsd-log-table-wrap' ).html( '<p style="padding:16px;color:#6b7280;">No activity logs found.</p>' );
					return;
				}
				var html = '<table class="adsd-log-table"><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>';
				res.data.logs.forEach( function ( l ) {
					html += '<tr>';
					html += '<td>' + ADSD.esc( l.created_at ) + '</td>';
					html += '<td>' + ADSD.esc( l.display_name || 'System' ) + '</td>';
					html += '<td><span class="adsd-log-action">' + ADSD.esc( l.action ) + '</span></td>';
					html += '<td>' + ADSD.esc( l.details ) + '</td>';
					html += '</tr>';
				} );
				html += '</tbody></table>';
				$( '#adsd-log-table-wrap' ).html( html );
			} );
		},

		loadErrorLog: function () {
			var $wrap = $( '#adsd-error-log-wrap' );
			$wrap.html( '<span style="color:#888;">Loading…</span>' );
			ADSD.ajax( 'adsd_get_error_log', {}, function ( res ) {
				if ( ! res.success ) {
					$wrap.html( '<span style="color:#e53e3e;">' + ADSD.esc( res.data ? res.data.message : 'Error loading log.' ) + '</span>' );
					return;
				}
				var entries  = res.data.entries || [];
				var msg      = res.data.message || '';
				var debugOff = res.data.debug_off || false;

				var html = '';

				// Show notice if WP_DEBUG_LOG is off.
				if ( debugOff ) {
					html += '<div style="color:#fbbf24;border-bottom:1px solid #2d2d2d;padding:4px 0 6px;margin-bottom:4px;">' +
						'&#9888; ' + ADSD.esc( msg ) + '</div>';
				}

				if ( ! entries.length ) {
					html += '<span style="color:#4ade80;">✓ ' + ADSD.esc( debugOff ? 'No plugin activity recorded yet.' : ( msg || 'No errors found.' ) ) + '</span>';
					$wrap.html( html );
					return;
				}

				entries.forEach( function ( line ) {
					var color = '#d4d4d4';
					var lc    = line.toLowerCase();
					if ( lc.indexOf( 'fatal' ) !== -1 || lc.indexOf( 'error' ) !== -1 ) { color = '#f87171'; }
					else if ( lc.indexOf( 'warning' ) !== -1 ) { color = '#fbbf24'; }
					else if ( lc.indexOf( 'cron' ) !== -1 || lc.indexOf( 'action_scheduler' ) !== -1 ) { color = '#60a5fa'; }

					html += '<div style="color:' + color + ';border-bottom:1px solid #2d2d2d;padding:3px 0;">' + ADSD.esc( line );

					// Inline explanation for known cron error.
					if ( lc.indexOf( 'could_not_set' ) !== -1 && lc.indexOf( 'cron event list' ) !== -1 ) {
						html += '<div style="color:#94a3b8;font-size:11px;margin-top:2px;padding-left:8px;">' +
							'&#8627; This means WP Cron could not save its schedule — usually caused by DISABLE_WP_CRON=true in wp-config.php or a database lock. ' +
							'Check the <strong>Cron / Scheduler Status</strong> section above for details and the fix.' +
							'</div>';
					}

					html += '</div>';
				} );
				$wrap.html( html );
				$wrap.scrollTop( $wrap[0].scrollHeight );
			} );
		},

		/* ════════════════════════════════════════════════
		   MODALS & VERSION RESTORE
		   ════════════════════════════════════════════════ */
		bindModals: function () {
			$( document ).on( 'click', '.adsd-modal-overlay, .adsd-modal-close', function () {
				$( this ).closest( '.adsd-modal' ).hide();
			} );
			$( document ).on( 'click', '.adsd-restore-version', function () {
				var ts = $( this ).data( 'ts' );
				if ( ! confirm( 'Restore this version? Current file will be backed up first.' ) ) { return; }
				ADSD.ajax( 'adsd_restore_version', { zip_id: ADSD.currentZipId, file_path: ADSD.currentFilePath, timestamp: ts }, function ( res ) {
					if ( res.success ) {
						$( '#adsd-version-modal' ).hide();
						ADSD.openEditor( ADSD.currentZipId, ADSD.currentFilePath );
					} else {
						alert( res.data ? res.data.message : 'Restore failed.' );
					}
				} );
			} );
		},

		/* ── Utility ──────────────────────────────────── */
		esc: function ( str ) {
			if ( ! str ) { return ''; }
			return String( str ).replace( /&/g,'&amp;' ).replace( /</g,'&lt;' ).replace( />/g,'&gt;' ).replace( /"/g,'&quot;' );
		},

		showMsg: function ( selector, type, msg ) {
			var el = $( selector );
			el.removeClass( 'adsd-editor-msg--success adsd-editor-msg--error' );
			if ( msg ) {
				el.addClass( 'adsd-editor-msg--' + type ).text( msg ).show();
				setTimeout( function () { el.fadeOut(); }, 4000 );
			}
		}
	};

	$( document ).ready( function () { ADSD.init(); } );

}( jQuery ) );

// =============================================================================
// WP PAGE INJECTOR TAB
// =============================================================================
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {

		if ( ! $( '#adsd-injector-form' ).length ) {
			return;
		}

		// ── Disable all header/footer toggle ──────────────────────────
		$( '#adsd_wp_disable_all_hf' ).on( 'change', function () {
			var $wrap    = $( this ).closest( '.adsd-injector-theme-toggle' );
			var $warning = $wrap.find( '.adsd-injector-theme-warning' );
			if ( this.checked ) {
				$wrap.addClass( 'adsd-injector-theme-toggle--on' );
				$warning.addClass( 'adsd-injector-theme-warning--visible' );
			} else {
				$wrap.removeClass( 'adsd-injector-theme-toggle--on' );
				$warning.removeClass( 'adsd-injector-theme-warning--visible' );
			}
		} );

		// ── Clear button ───────────────────────────────────────────────
		$( document ).on( 'click', '.adsd-injector-clear', function () {
			var target = $( this ).data( 'target' );
			if ( target ) {
				$( '#' + target ).val( '' ).trigger( 'focus' );
			}
		} );

		// ── Save form ──────────────────────────────────────────────────
		$( '#adsd-injector-form' ).on( 'submit', function ( e ) {
			e.preventDefault();

			var $btn    = $( '#adsd-injector-save' );
			var $status = $( '#adsd-injector-status' );
			$btn.prop( 'disabled', true ).text( 'Saving...' );
			$status.removeClass( 'success error' ).text( '' );

			$.post(
				adsdData.ajaxUrl,
				{
					action                    : 'adsd_save_injector',
					nonce                     : adsdData.nonce,
					adsd_wp_disable_all_hf    : $( '#adsd_wp_disable_all_hf' ).is( ':checked' ) ? 1 : 0,
					adsd_wp_injector_enabled  : $( '#adsd_wp_injector_enabled' ).is( ':checked' ) ? 1 : 0,
					adsd_container_enabled    : $( '#adsd_container_enabled' ).is( ':checked' ) ? 1 : 0,
					adsd_container_desktop    : $( '#adsd_container_desktop' ).val(),
					adsd_container_tablet     : $( '#adsd_container_tablet' ).val(),
					adsd_container_mobile     : $( '#adsd_container_mobile' ).val(),
					adsd_container_padding    : $( '#adsd_container_padding' ).val(),
					adsd_container_margin_top      : $( '#adsd_container_margin_top' ).val(),
					adsd_post_template_enabled     : $( '#adsd_post_template_enabled' ).is( ':checked' ) ? 1 : 0,
					adsd_post_meta_author          : $( '#adsd_post_meta_author' ).is( ':checked' ) ? 1 : 0,
					adsd_post_meta_date            : $( '#adsd_post_meta_date' ).is( ':checked' ) ? 1 : 0,
					adsd_post_meta_category        : $( '#adsd_post_meta_category' ).is( ':checked' ) ? 1 : 0,
					adsd_post_meta_tags            : $( '#adsd_post_meta_tags' ).is( ':checked' ) ? 1 : 0,
					adsd_post_meta_read_time       : $( '#adsd_post_meta_read_time' ).is( ':checked' ) ? 1 : 0,
					adsd_post_meta_views           : $( '#adsd_post_meta_views' ).is( ':checked' ) ? 1 : 0,
					adsd_post_show_excerpt         : $( '#adsd_post_show_excerpt' ).is( ':checked' ) ? 1 : 0,
					adsd_post_show_related         : $( '#adsd_post_show_related' ).is( ':checked' ) ? 1 : 0,
					adsd_post_related_count        : $( '#adsd_post_related_count' ).val(),
					adsd_wp_head_code         : $( '#adsd_wp_head_code' ).val(),
					adsd_wp_header_html       : $( '#adsd_wp_header_html' ).val(),
					adsd_wp_footer_html       : $( '#adsd_wp_footer_html' ).val(),
					adsd_wp_script_html       : $( '#adsd_wp_script_html' ).val(),
					adsd_wp_custom_css        : $( '#adsd_wp_custom_css' ).val(),
					adsd_wp_custom_js         : $( '#adsd_wp_custom_js' ).val(),
					adsd_wp_404_html          : $( '#adsd_wp_404_html' ).val(),
				},
				function ( res ) {
					$btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-saved"></span> Save All Settings' );
					if ( res.success ) {
						$status.addClass( 'success' ).text( res.data.message || 'Saved!' );
					} else {
						$status.addClass( 'error' ).text( ( res.data && res.data.message ) || 'Error saving.' );
					}
					setTimeout( function () { $status.text( '' ).removeClass( 'success error' ); }, 4000 );
				}
			).fail( function () {
				$btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-saved"></span> Save All Settings' );
				$status.addClass( 'error' ).text( 'AJAX request failed.' );
			} );
		} );

	} );

}( jQuery ) );

// Copy base URL button on injector tab
( function( $ ) {
	$( document ).on( 'click', '#adsd-copy-page-url', function() {
		var code = $( '#adsd-page-url-example' ).text();
		// Remove the italic placeholder text
		var base = code.replace( /YOUR-PAGE-URL$/, '' );
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( base ).then( function() {
				$( '#adsd-copy-page-url' ).text( 'Copied!' );
				setTimeout( function() {
					$( '#adsd-copy-page-url' ).html( '<span class="dashicons dashicons-clipboard"></span> Copy Base URL' );
				}, 2000 );
			} );
		}
	} );
}( jQuery ) );
