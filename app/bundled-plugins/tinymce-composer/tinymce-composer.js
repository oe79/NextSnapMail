/**
 * TinyMCE Composer for SnappyMail / NextSnapMail
 * Author: LibertyTalk FM, Inc.
 * Registers TinyMCE as a WYSIWYG editor through rl.registerWYSIWYG().
 * Modes: wysiwyg (TinyMCE), plain (textarea), source (HTML in textarea).
 */
(rl => {
	if (!rl) {
		return;
	}

	const
		path = rl.settings.app('webVersionPath') + 'static/tinymce-composer/tinymce/',
		htmlToPlain = html => rl.Utils.htmlToPlain(html).trim(),
		plainToHtml = text => rl.Utils.plainToHtml(text),
		prefersDark = () => window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

	let loading = null;
	const loadTinyMCE = () => {
		if (window.tinymce) {
			return Promise.resolve();
		}
		if (!loading) {
			loading = new Promise((resolve, reject) => {
				const script = document.createElement('script');
				script.src = path + 'tinymce.min.js';
				script.onload = resolve;
				script.onerror = () => reject(new Error('TinyMCE failed to load from ' + script.src));
				document.head.append(script);
			});
		}
		return loading;
	};

	class TinyMCEComposer
	{
		constructor(owner, container, onReady) {
			this.owner = owner;
			this.mode = 'wysiwyg';
			this.editor = null;
			this.handlers = {};
			this._prev_txt_sig = null;

			container.classList.add('tinymce-composer');

			this.host = document.createElement('div');
			this.host.className = 'tinymce-composer-host';
			this.area = document.createElement('textarea');
			this.host.append(this.area);

			this.plain = document.createElement('textarea');
			this.plain.className = 'tinymce-composer-plain';
			this.plain.hidden = true;
			this.plain.addEventListener('focus', () => this.trigger('focus'));
			this.plain.addEventListener('blur', () => this.trigger('blur'));

			this.bar = document.createElement('div');
			this.bar.className = 'tinymce-composer-bar';
			this.sourceButton = document.createElement('button');
			this.sourceButton.type = 'button';
			this.sourceButton.textContent = 'Source';
			this.sourceButton.addEventListener('click', () => this.setMode('source' === this.mode ? 'wysiwyg' : 'source'));
			this.bar.append(this.sourceButton);

			container.append(this.host, this.plain, this.bar);
			this.updateView();

			loadTinyMCE()
				.then(() => this.init(onReady))
				.catch(e => {
					console.error(e);
					// TinyMCE unavailable: fall back to plain text so the composer still works
					this.mode = 'plain';
					this.updateView();
					onReady(this);
				});
		}

		init(onReady) {
			window.tinymce.init({
				target: this.area,
				license_key: 'gpl',
				base_url: path,
				suffix: '.min',
				promotion: false,
				branding: false,
				menubar: false,
				statusbar: false,
				resize: false,
				height: '100%',
				skin: prefersDark() ? 'oxide-dark' : 'oxide',
				content_css: 'default',
				plugins: 'advlist autolink lists link image table',
				toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | removeformat',
				toolbar_mode: 'wrap',
				block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Quote=blockquote; Code=pre',

				// Clean markup: only these elements, attributes, styles and classes survive
				valid_elements: 'p,br,h1,h2,h3,h4,h5,h6,strong/b,em/i,u,s/strike/del,sub,sup,code,pre,hr,'
					+ 'a[href|title|target],ul,ol[start],li,blockquote,'
					+ 'table[border|style],thead,tbody,tfoot,tr,td[colspan|rowspan|style],th[colspan|rowspan|style],'
					+ 'img[src|alt|width|height|style],div[class|style],span[style]',
				valid_styles: {
					'*': 'color,background-color,text-align,font-family,font-size,text-decoration,width,height,border,border-collapse,padding,margin'
				},
				valid_classes: { 'div': 'rl-signature' },
				forced_root_block: 'p',
				entity_encoding: 'raw',
				convert_fonts_to_spans: true,
				remove_trailing_brs: true,
				indent: true,
				convert_urls: false,

				// Paste: scrubbed through the same rules; images embedded, never uploaded anywhere
				paste_as_text: false,
				paste_data_images: true,
				automatic_uploads: false,
				images_file_types: 'jpeg,jpg,png,gif,webp',

				setup: editor => {
					this.editor = editor;
					editor.on('init', () => onReady(this));
					editor.on('focus', () => this.trigger('focus'));
					editor.on('blur', () => this.trigger('blur'));
				}
			});
		}

		on(type, fn) {
			(this.handlers[type] || (this.handlers[type] = [])).push(fn);
		}

		trigger(type) {
			(this.handlers[type] || []).forEach(fn => fn());
		}

		updateView() {
			const wysiwyg = 'wysiwyg' === this.mode;
			this.host.hidden = !wysiwyg;
			this.host.style.display = wysiwyg ? '' : 'none';
			this.plain.hidden = wysiwyg;
			this.plain.style.display = wysiwyg ? 'none' : '';
			this.bar.hidden = 'plain' === this.mode;
			this.bar.style.display = 'plain' === this.mode ? 'none' : '';
			this.sourceButton.textContent = 'source' === this.mode ? 'Visual' : 'Source';
		}

		setMode(mode) {
			if (mode === this.mode) {
				return;
			}
			const prev = this.mode;
			try {
				if ('plain' === mode) {
					this.plain.value = htmlToPlain('source' === prev ? this.plain.value : this.editorHtml());
				} else if ('source' === mode) {
					this.plain.value = 'plain' === prev ? plainToHtml(this.plain.value) : this.editorHtml();
				} else {
					this.setEditorHtml('plain' === prev ? plainToHtml(this.plain.value) : this.plain.value);
				}
			} catch (e) {
				console.error(e);
			}
			this.mode = mode;
			this.updateView();
			this.trigger('mode');
		}

		editorHtml() {
			return this.editor ? this.editor.getContent() : '';
		}

		setEditorHtml(html) {
			if (this.editor) {
				this.editor.setContent(html || '');
				this.editor.selection.setCursorLocation(this.editor.getBody(), 0);
			}
		}

		execCommand(cmd, cfg) {
			if ('insertSignature' == cmd) {
				cfg = Object.assign({
					clearCache: false,
					isHtml: false,
					insertBefore: false,
					signature: ''
				}, cfg);

				if (cfg.clearCache) {
					this._prev_txt_sig = null;
				} else try {
					const signature = cfg.isHtml ? htmlToPlain(cfg.signature) : cfg.signature;
					if ('plain' === this.mode) {
						let
							text = this.plain.value,
							prevSignature = this._prev_txt_sig;
						if (prevSignature) {
							text = text.replace(prevSignature, '').trim();
						}
						this.plain.value = cfg.insertBefore ? '\n\n' + signature + '\n\n' + text : text + '\n\n' + signature;
					} else if (this.editor) {
						if ('source' === this.mode) {
							this.editor.setContent(this.plain.value || '');
						}
						const
							root = this.editor.getBody(),
							doc = root.ownerDocument,
							div = doc.createElement('div');
						div.className = 'rl-signature';
						div.innerHTML = cfg.isHtml ? cfg.signature : plainToHtml(cfg.signature);
						root.querySelectorAll('div.rl-signature').forEach(node => node.remove());
						cfg.insertBefore ? root.prepend(div) : root.append(div);
						// Cursor stays above the signature
						div.before(doc.createElement('br'));
						div.before(doc.createElement('br'));
						if ('source' === this.mode) {
							this.plain.value = this.editor.getContent();
						}
					}
					this._prev_txt_sig = signature;
				} catch (e) {
					console.error(e);
				}
			}
		}

		getData() {
			return 'source' === this.mode ? this.plain.value : this.editorHtml();
		}

		setData(html) {
			if ('source' === this.mode) {
				this.plain.value = html || '';
			} else {
				this.setEditorHtml(html);
			}
		}

		getPlainData() {
			return this.plain.value;
		}

		setPlainData(text) {
			return this.plain.value = text || '';
		}

		focus() {
			if ('wysiwyg' === this.mode) {
				this.editor && this.editor.focus();
			} else {
				this.plain.focus();
			}
		}

		blur() {
			const active = document.activeElement;
			if (active && active.blur) {
				active.blur();
			}
		}
	}

	rl.registerWYSIWYG('TinyMCE', (owner, container, onReady) => new TinyMCEComposer(owner, container, onReady));

})(window.rl);
