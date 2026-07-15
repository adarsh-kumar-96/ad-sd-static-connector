/**
 * AD-SD WP Static Connector — Built-in Code Editor
 *
 * A lightweight, self-contained "Notepad++ style" text editor with
 * line numbers, current-line highlight, find & replace, go-to-line,
 * and dark/light themes. No external CDN dependency — loads instantly.
 *
 * Usage:
 *   var editor = ADSDEditor.create( containerEl, { value: '...', language: 'html', theme: 'dark' } );
 *   editor.getValue(); editor.setValue( 'x' );
 *   editor.onChange( function () { ... } );
 *   editor.goToLine( 12 );
 *   editor.findReplace( { find: 'foo', replace: 'bar', mode: 'next'|'all' } );
 *   editor.setTheme( 'light' );
 *   editor.destroy();
 */
( function ( window ) {
	'use strict';

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	// Map specific HTML tag names to dedicated colour classes (per user spec).
	var TAG_COLOR_MAP = {
		// Block structural
		div:     'tok-tag-div',
		section: 'tok-tag-section',
		article: 'tok-tag-section',
		aside:   'tok-tag-section',
		header:  'tok-tag-section',
		footer:  'tok-tag-section',
		main:    'tok-tag-section',
		nav:     'tok-tag-section',
		// Headings
		h1: 'tok-tag-h1', h4: 'tok-tag-h1',
		h2: 'tok-tag-h2', h5: 'tok-tag-h2',
		h3: 'tok-tag-h2', h6: 'tok-tag-h2',
		// Inline
		span: 'tok-tag-span',
		a:    'tok-tag-span',
		strong: 'tok-tag-span',
		em:   'tok-tag-span',
		// Lists
		ul: 'tok-tag-list', ol: 'tok-tag-list', li: 'tok-tag-list',
		dl: 'tok-tag-list', dt: 'tok-tag-list', dd: 'tok-tag-list'
	};

	function highlightCode( code, lang, tabSize ) {
		tabSize = tabSize || 2;

		// ── Step 0: Pre-compute guide-line markup from RAW code BEFORE any
		// highlight spans are injected.  Running the guide pass on already-
		// highlighted HTML is broken because multi-line comment/string spans
		// split across \n boundaries — wrapping their continuation lines in
		// tok-guide spans produces invalid nested HTML and breaks rendering.
		// Solution: record per-line indent levels from the raw source, escape
		// the code, run syntax highlighting, then stitch guide spans onto the
		// start of each highlighted line using the pre-recorded levels.
		var guideLevels = code.split( '\n' ).map( function ( line ) {
			var m = line.match( /^(\t| {1,})/ );
			if ( ! m ) { return 0; }
			// Convert tabs to tabSize spaces for counting.
			var indent = m[ 0 ].replace( /\t/g, new Array( tabSize + 1 ).join( ' ' ) );
			return Math.floor( indent.length / tabSize );
		} );

		var html = escapeHtml( code );

		if ( lang === 'html' || lang === 'xml' ) {
			// Protect HTML comments so later passes don't re-process them.
			var _htmlComments = [];
			html = html.replace( /(&lt;!--[\s\S]*?--&gt;)/g, function ( m ) {
				var i = _htmlComments.length;
				_htmlComments.push( '<span class="tok-comment">' + m + '</span>' );
				return '\u0000C' + i + '\u0000';
			} );
			// Colour attribute name="value" pairs (must run before tag-name pass).
			html = html.replace( /([a-zA-Z_:]([\w:-]*))(=)("[^"]*"|'[^']*')/g,
				'<span class="tok-attr">$1</span>$3<span class="tok-string">$4</span>' );
			// Colour tag names.
			html = html.replace( /(&lt;\/?)([a-zA-Z][\w:-]*)/g, function ( m, bracket, name ) {
				var cls = TAG_COLOR_MAP[ name.toLowerCase() ] || 'tok-tag';
				return bracket + '<span class="' + cls + '">' + name + '</span>';
			} );
			// Restore protected comments.
			html = html.replace( /\u0000C(\d+)\u0000/g, function ( m, i ) { return _htmlComments[ +i ]; } );

		} else if ( lang === 'css' ) {
			// Protect comments first.
			var _cssComments = [];
			html = html.replace( /(\/\*[\s\S]*?\*\/)/g, function ( m ) {
				var i = _cssComments.length;
				_cssComments.push( '<span class="tok-comment">' + m + '</span>' );
				return '\u0000D' + i + '\u0000';
			} );
			html = html.replace( /("[^"]*"|'[^']*')/g, '<span class="tok-string">$1</span>' );
			// Selector names.
			html = html.replace( /^([\s\S]*?)([.#]?[a-zA-Z][\w-]*(?::[\w-]+)?)(\s*\{)/gm,
				function ( m, pre, sel, brace ) { return pre + '<span class="tok-tag">' + sel + '</span>' + brace; } );
			// Property names: word before a colon that is NOT a selector pseudo-class.
			html = html.replace( /^([ \t]*)([a-z][a-z-]+)(\s*:)/gm,
				'$1<span class="tok-attr">$2</span>$3' );
			// Numeric values with units.
			html = html.replace( /(:\s*)(-?[\d.]+(?:px|em|rem|%|vh|vw|s|ms|deg|fr|ch|ex|vmin|vmax)?\b)/g,
				'$1<span class="tok-number">$2</span>' );
			// Restore comments.
			html = html.replace( /\u0000D(\d+)\u0000/g, function ( m, i ) { return _cssComments[ +i ]; } );

		} else if ( lang === 'javascript' || lang === 'json' ) {
			// Protect strings and comments so keywords inside them stay uncoloured.
			var _jsTokens = [];
			html = html.replace( /(\/\/[^\n]*|\/\*[\s\S]*?\*\/|"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'|`(?:[^`\\]|\\.)*`)/g,
				function ( m ) {
					var i = _jsTokens.length;
					var cls = ( m[0] === '/' ) ? 'tok-comment' : 'tok-string';
					_jsTokens.push( '<span class="' + cls + '">' + m + '</span>' );
					return '\u0000J' + i + '\u0000';
				} );
			html = html.replace(
				/\b(var|let|const|function|return|if|else|for|while|new|class|extends|import|export|from|default|typeof|instanceof|try|catch|finally|throw|async|await|of|in|null|undefined|true|false|this|switch|case|break|continue|do)\b/g,
				'<span class="tok-keyword">$1</span>' );
			html = html.replace( /\b(\d+(?:\.\d+)?)\b/g, '<span class="tok-number">$1</span>' );
			// Restore protected tokens.
			html = html.replace( /\u0000J(\d+)\u0000/g, function ( m, i ) { return _jsTokens[ +i ]; } );
		}

		// ── Step N: Apply guide lines per-line using pre-recorded indent levels.
		// We strip leading whitespace from each highlighted line and prefix it
		// with tok-guide spans (one per indent level) so guide lines always
		// appear at the correct columns regardless of highlight span nesting.
		var pad = new Array( tabSize + 1 ).join( ' ' );
		html = html.split( '\n' ).map( function ( line, idx ) {
			var level = guideLevels[ idx ] || 0;
			if ( level === 0 ) { return line; }
			// Strip the raw leading whitespace that is already in the escaped html.
			var stripped = line.replace( /^[ \t]+/, '' );
			var guides   = '';
			for ( var g = 0; g < level; g++ ) {
				guides += '<span class="tok-guide">' + pad + '</span>';
			}
			return guides + stripped;
		} ).join( '\n' );

		return html + '\n';
	}

	function ADSDEditorInstance( container, opts ) {
		opts = opts || {};
		this.container  = container;
		this.language   = opts.language || 'plaintext';
		this.theme      = opts.theme || 'dark';
		this.tabSize    = opts.tabSize || 2;
		this.changeCbs  = [];
		this._lastLineCount = 0;  // FIX: cache line count to avoid full gutter rebuilds

		container.innerHTML = '';
		container.classList.add( 'adsd-edt' );
		container.classList.add( 'adsd-edt--' + this.theme );

		this.gutter = document.createElement( 'div' );
		this.gutter.className = 'adsd-edt-gutter';

		var codeWrap = document.createElement( 'div' );
		codeWrap.className = 'adsd-edt-code-wrap';

		this.highlightEl = document.createElement( 'pre' );
		this.highlightEl.className = 'adsd-edt-highlight';
		this.highlightCode = document.createElement( 'code' );
		this.highlightEl.appendChild( this.highlightCode );

		this.textarea = document.createElement( 'textarea' );
		this.textarea.className = 'adsd-edt-textarea';
		this.textarea.spellcheck = false;
		this.textarea.wrap = 'off';
		this.textarea.value = opts.value || '';

		codeWrap.appendChild( this.highlightEl );
		codeWrap.appendChild( this.textarea );

		var wrap = document.createElement( 'div' );
		wrap.className = 'adsd-edt-wrap';
		wrap.appendChild( this.gutter );
		wrap.appendChild( codeWrap );
		container.appendChild( wrap );

		var self = this;

		this.textarea.addEventListener( 'input', function () {
			self.updateGutter();
			self.renderHighlight();
			self.changeCbs.forEach( function ( cb ) { cb(); } );
		} );

		this.textarea.addEventListener( 'scroll', function () {
			self.syncHighlight(); // syncHighlight now also syncs gutter.scrollTop
			self.updateActiveLine();
		} );

		this.textarea.addEventListener( 'keydown', function ( e ) {
			// Tab key inserts spaces instead of moving focus.
			if ( e.key === 'Tab' ) {
				e.preventDefault();
				var start = self.textarea.selectionStart;
				var end   = self.textarea.selectionEnd;
				var pad   = new Array( self.tabSize + 1 ).join( ' ' );
				self.textarea.setRangeText( pad, start, end, 'end' );
				self.updateGutter();
				self.renderHighlight(); // renderHighlight calls syncHighlight
				self.changeCbs.forEach( function ( cb ) { cb(); } );
			}
		} );

		// FIX: Only update active line on click/keyup — do NOT call full updateGutter here.
		this.textarea.addEventListener( 'click', function () {
			self.syncHighlight();
			self.updateActiveLine();
		} );

		// mousedown: sync highlight AND update active line immediately on click.
		// No requestAnimationFrame — inline sync prevents one-frame cursor lag.
		this.textarea.addEventListener( 'mousedown', function () {
			self.syncHighlight();
			self.updateActiveLine();
		} );

		// mouseup: re-sync after drag-selection ends.
		this.textarea.addEventListener( 'mouseup', function () {
			self.syncHighlight();
			self.updateActiveLine();
		} );

		// FIX: selectionchange fires on any cursor move (arrow keys, mouse, shift+click).
		// This covers cases keyup misses (e.g. mouseup after drag-select).
		this._onSelectionChange = function () {
			if ( document.activeElement === self.textarea ) {
				self.updateActiveLine();
			}
		};
		document.addEventListener( 'selectionchange', this._onSelectionChange );

		// FIX: Debounce keyup active-line updates to avoid per-keystroke DOM thrashing.
		var _activeLineTimer = null;
		this.textarea.addEventListener( 'keyup', function () {
			if ( _activeLineTimer ) { clearTimeout( _activeLineTimer ); }
			_activeLineTimer = setTimeout( function () {
				self.syncHighlight();
				self.updateActiveLine();
			}, 0 ); // 0ms — run after current event completes for instant cursor response
		} );

		this.updateGutter();
		this.renderHighlight();
	}

	/**
	 * Sync highlight layer scroll to textarea scroll — direct property assignment,
	 * no CSS transform. Both elements have overflow:scroll so their scroll ranges
	 * are identical; setting scrollLeft/scrollTop on the highlight mirrors the
	 * textarea exactly with zero drift or frame delay.
	 */
	ADSDEditorInstance.prototype.syncHighlight = function () {
		this.highlightEl.scrollLeft = this.textarea.scrollLeft;
		this.highlightEl.scrollTop  = this.textarea.scrollTop;
		this.gutter.scrollTop       = this.textarea.scrollTop;
	};

	/** Re-render the syntax-highlight overlay layer from the current textarea value. */
	ADSDEditorInstance.prototype.renderHighlight = function () {
		// Replace tab characters with exactly tabSize spaces before highlighting.
		// This makes the highlight layer's character widths identical to the
		// textarea's rendered output (textarea uses CSS tab-size but <span> tags
		// inside <code> reset tab stops, causing cursor-to-text drift).
		var value = this.textarea.value.replace( /\t/g, new Array( this.tabSize + 1 ).join( ' ' ) );
		this.highlightCode.innerHTML = highlightCode( value, this.language, this.tabSize );
		this.highlightCode.className = 'language-' + this.language;
		this.syncHighlight();
	};

	ADSDEditorInstance.prototype.getValue = function () {
		return this.textarea.value;
	};

	ADSDEditorInstance.prototype.setValue = function ( value ) {
		this.textarea.value = value || '';
		this.textarea.scrollTop  = 0;
		this.textarea.scrollLeft = 0;
		this.textarea.setSelectionRange( 0, 0 );
		this._lastLineCount = 0;
		this.updateGutter();
		this.renderHighlight(); // renderHighlight already calls syncHighlight
	};

	ADSDEditorInstance.prototype.setLanguage = function ( lang ) {
		this.language = lang;
		this.renderHighlight();
	};

	ADSDEditorInstance.prototype.onChange = function ( cb ) {
		if ( typeof cb === 'function' ) { this.changeCbs.push( cb ); }
	};

	ADSDEditorInstance.prototype.focus = function () {
		this.textarea.focus();
	};

	ADSDEditorInstance.prototype.setTheme = function ( theme ) {
		this.container.classList.remove( 'adsd-edt--dark', 'adsd-edt--light' );
		this.container.classList.add( 'adsd-edt--' + theme );
		this.theme = theme;
	};

	/**
	 * FIX: updateGutter now only rebuilds DOM when line count actually changes.
	 * Previously it rebuilt all line-number <div>s on every keystroke,
	 * causing O(n) DOM thrash on large files and making the 2nd file load slow.
	 */
	ADSDEditorInstance.prototype.updateGutter = function () {
		var lineCount = this.textarea.value.split( '\n' ).length;

		if ( lineCount !== this._lastLineCount ) {
			var html = '';
			for ( var i = 1; i <= lineCount; i++ ) {
				html += '<div class="adsd-edt-line-num">' + i + '</div>';
			}
			this.gutter.innerHTML = html;
			this._lastLineCount = lineCount;
		}

		// syncHighlight handles both highlight transform AND gutter scroll sync.
		this.syncHighlight();
		this.updateActiveLine();
	};

	ADSDEditorInstance.prototype.getLineFromPos = function ( pos ) {
		return this.textarea.value.substring( 0, pos ).split( '\n' ).length;
	};

	ADSDEditorInstance.prototype.updateActiveLine = function () {
		var line = this.getLineFromPos( this.textarea.selectionStart );
		var nums = this.gutter.children;
		for ( var i = 0; i < nums.length; i++ ) {
			nums[ i ].classList.toggle( 'adsd-edt-line-num--active', ( i + 1 ) === line );
		}
	};

	/**
	 * Scroll to and select a given line number.
	 */
	ADSDEditorInstance.prototype.goToLine = function ( lineNumber ) {
		var lines = this.textarea.value.split( '\n' );
		lineNumber = Math.max( 1, Math.min( lineNumber, lines.length ) );

		var pos = 0;
		for ( var i = 0; i < lineNumber - 1; i++ ) {
			pos += lines[ i ].length + 1; // +1 for the newline char.
		}
		var endPos = pos + lines[ lineNumber - 1 ].length;

		this.textarea.focus();
		this.textarea.setSelectionRange( pos, endPos );

		// Scroll the line into view (approximate using line height).
		var lineHeight = parseFloat( window.getComputedStyle( this.textarea ).lineHeight ) || 19.5;
		var visibleLines = Math.floor( this.textarea.clientHeight / lineHeight );
		var targetScroll = Math.max( 0, ( lineNumber - Math.floor( visibleLines / 2 ) ) * lineHeight );
		this.textarea.scrollTop = targetScroll;
		this.gutter.scrollTop = targetScroll;
		this.syncHighlight();
		this.updateActiveLine();
	};

	/**
	 * Find & replace.
	 *
	 * @param {Object} args
	 * @param {string} args.find    Search text.
	 * @param {string} args.replace Replacement text (optional).
	 * @param {string} args.mode    'next' | 'all'
	 * @param {boolean} [args.caseSensitive]
	 * @return {Object} { found, count }
	 */
	ADSDEditorInstance.prototype.findReplace = function ( args ) {
		args = args || {};
		var find = args.find || '';
		if ( ! find ) { return { found: false, count: 0 }; }

		var value = this.textarea.value;
		var hay   = args.caseSensitive ? value : value.toLowerCase();
		var needle = args.caseSensitive ? find : find.toLowerCase();

		if ( args.mode === 'all' ) {
			if ( typeof args.replace !== 'string' ) { return { found: false, count: 0 }; }
			var count = 0;
			var idx = hay.indexOf( needle );
			var out = '';
			var lastEnd = 0;
			while ( idx !== -1 ) {
				out += value.substring( lastEnd, idx ) + args.replace;
				lastEnd = idx + needle.length;
				count++;
				idx = hay.indexOf( needle, lastEnd );
			}
			out += value.substring( lastEnd );
			if ( count > 0 ) {
				this.setValue( out );
				this.changeCbs.forEach( function ( cb ) { cb(); } );
			}
			return { found: count > 0, count: count };
		}

		// 'next': find from current cursor, wrap around.
		var searchFrom = this.textarea.selectionEnd || 0;
		var foundIdx = hay.indexOf( needle, searchFrom );
		if ( foundIdx === -1 ) {
			foundIdx = hay.indexOf( needle, 0 );
		}
		if ( foundIdx === -1 ) { return { found: false, count: 0 }; }

		if ( typeof args.replace === 'string' ) {
			this.textarea.setRangeText( args.replace, foundIdx, foundIdx + needle.length, 'end' );
			this.changeCbs.forEach( function ( cb ) { cb(); } );
			this.updateGutter();
			this.renderHighlight(); // renderHighlight calls syncHighlight
			this.textarea.focus();
			return { found: true, count: 1 };
		}

		this.textarea.focus();
		this.textarea.setSelectionRange( foundIdx, foundIdx + needle.length );
		this.scrollSelectionIntoView();
		return { found: true, count: 1 };
	};

	ADSDEditorInstance.prototype.scrollSelectionIntoView = function () {
		var line = this.getLineFromPos( this.textarea.selectionStart );
		var lineHeight = parseFloat( window.getComputedStyle( this.textarea ).lineHeight ) || 19.5;
		var visibleLines = Math.floor( this.textarea.clientHeight / lineHeight );
		var top = this.textarea.scrollTop;
		var bottom = top + visibleLines * lineHeight;
		var lineTop = ( line - 1 ) * lineHeight;
		if ( lineTop < top || lineTop > bottom - lineHeight ) {
			var target = Math.max( 0, lineTop - lineHeight * Math.floor( visibleLines / 2 ) );
			this.textarea.scrollTop = target;
			this.gutter.scrollTop   = target;
			this.syncHighlight();
		}
	};

	/** Copy current selection (or full content if nothing selected) to clipboard. */
	ADSDEditorInstance.prototype.copy = function () {
		var sel = this.getSelectedOrAll();
		return this.writeClipboard( sel );
	};

	/** Cut current selection (or full content) to clipboard and remove it. */
	ADSDEditorInstance.prototype.cut = function () {
		var start = this.textarea.selectionStart;
		var end   = this.textarea.selectionEnd;
		var hasSelection = start !== end;
		var text = hasSelection ? this.textarea.value.substring( start, end ) : this.textarea.value;

		var self = this;
		return this.writeClipboard( text ).then( function () {
			if ( hasSelection ) {
				self.textarea.setRangeText( '', start, end, 'end' );
			} else {
				self.setValue( '' );
			}
			self.updateGutter();
			self.renderHighlight(); // renderHighlight calls syncHighlight
			self.changeCbs.forEach( function ( cb ) { cb(); } );
		} );
	};

	/** Paste clipboard content at the cursor, replacing any selection. */
	ADSDEditorInstance.prototype.paste = function () {
		var self = this;
		if ( ! navigator.clipboard || ! navigator.clipboard.readText ) {
			return Promise.reject( new Error( 'clipboard-unavailable' ) );
		}
		return navigator.clipboard.readText().then( function ( text ) {
			var start = self.textarea.selectionStart;
			var end   = self.textarea.selectionEnd;
			self.textarea.setRangeText( text, start, end, 'end' );
			self.updateGutter();
			self.renderHighlight(); // renderHighlight calls syncHighlight
			self.changeCbs.forEach( function ( cb ) { cb(); } );
		} );
	};

	ADSDEditorInstance.prototype.getSelectedOrAll = function () {
		var start = this.textarea.selectionStart;
		var end   = this.textarea.selectionEnd;
		return start !== end ? this.textarea.value.substring( start, end ) : this.textarea.value;
	};

	ADSDEditorInstance.prototype.writeClipboard = function ( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}
		// Fallback for older browsers / non-secure contexts.
		var tmp = document.createElement( 'textarea' );
		tmp.value = text;
		tmp.style.position = 'fixed';
		tmp.style.opacity  = '0';
		document.body.appendChild( tmp );
		tmp.select();
		try { document.execCommand( 'copy' ); } catch ( e ) { /* ignore */ }
		document.body.removeChild( tmp );
		return Promise.resolve();
	};

	ADSDEditorInstance.prototype.destroy = function () {
		if ( this._onSelectionChange ) {
			document.removeEventListener( 'selectionchange', this._onSelectionChange );
		}
		this.container.innerHTML = '';
		this.container.classList.remove( 'adsd-edt', 'adsd-edt--dark', 'adsd-edt--light' );
	};

	// Void / self-closing HTML elements that never get a matching </tag> and
	// therefore never increase indentation.
	var VOID_TAGS = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
		'link', 'meta', 'param', 'source', 'track', 'wbr' ];

	/** Re-indent HTML based on tag nesting depth. Content of <pre>/<script>/<style>/<textarea> is left untouched. */
	function beautifyHtml( code, tabSize ) {
		var pad = new Array( tabSize + 1 ).join( ' ' );
		// Split into tags, comments and text chunks.
		var parts = code.match( /<!--[\s\S]*?-->|<[^>]+>|[^<]+/g ) || [];
		var out = [];
		var depth = 0;
		var rawUntil = null; // tag name we're currently passing through verbatim (pre/script/style/textarea)

		for ( var i = 0; i < parts.length; i++ ) {
			var part = parts[ i ];

			if ( rawUntil ) {
				out.push( part );
				if ( new RegExp( '</' + rawUntil + '\\s*>', 'i' ).test( part ) ) { rawUntil = null; }
				continue;
			}

			if ( /^<!--/.test( part ) ) {
				out.push( new Array( depth + 1 ).join( pad ) + part.trim() );
				continue;
			}

			if ( /^<\//.test( part ) ) {
				depth = Math.max( 0, depth - 1 );
				out.push( new Array( depth + 1 ).join( pad ) + part.trim() );
				continue;
			}

			if ( /^</.test( part ) ) {
				var tagMatch  = part.match( /^<([a-zA-Z][\w:-]*)/ );
				var tagName   = tagMatch ? tagMatch[ 1 ].toLowerCase() : '';
				var selfClose = /\/>\s*$/.test( part ) || VOID_TAGS.indexOf( tagName ) !== -1;
				out.push( new Array( depth + 1 ).join( pad ) + part.trim() );
				if ( ! selfClose && ! /^<!doctype/i.test( part ) ) {
					if ( tagName === 'pre' || tagName === 'script' || tagName === 'style' || tagName === 'textarea' ) {
						rawUntil = tagName;
					}
					depth++;
				}
				continue;
			}

			// Plain text node — keep only non-empty lines, indented at current depth.
			var text = part.trim();
			if ( text ) {
				text.split( /\n+/ ).forEach( function ( line ) {
					line = line.trim();
					if ( line ) { out.push( new Array( depth + 1 ).join( pad ) + line ); }
				} );
			}
		}

		return out.join( '\n' );
	}

	/** Simple brace-based re-indent for CSS. */
	function beautifyCss( code, tabSize ) {
		var pad = new Array( tabSize + 1 ).join( ' ' );
		var depth = 0;
		var lines = [];
		// Normalise: ensure `{`, `}` and `;` each end a line.
		var normalized = code.replace( /\s*\{\s*/g, ' {\n' )
			.replace( /\s*\}\s*/g, '\n}\n' )
			.replace( /;\s*/g, ';\n' );

		normalized.split( '\n' ).forEach( function ( raw ) {
			var line = raw.trim();
			if ( ! line ) { return; }
			if ( line === '}' ) { depth = Math.max( 0, depth - 1 ); }
			lines.push( new Array( depth + 1 ).join( pad ) + line );
			if ( /\{$/.test( line ) ) { depth++; }
		} );

		return lines.join( '\n' );
	}

	/** Beautify (re-indent) the current code based on its language. Returns true if changed. */
	ADSDEditorInstance.prototype.beautify = function () {
		var value = this.textarea.value;
		var result;
		if ( this.language === 'html' || this.language === 'xml' ) {
			result = beautifyHtml( value, this.tabSize );
		} else if ( this.language === 'css' ) {
			result = beautifyCss( value, this.tabSize );
		} else {
			return false; // Beautify currently supports HTML/XML and CSS only.
		}
		this.setValue( result );
		this.changeCbs.forEach( function ( cb ) { cb(); } );
		return true;
	};

	window.ADSDEditor = {
		create: function ( container, opts ) {
			return new ADSDEditorInstance( container, opts );
		}
	};

}( window ) );
