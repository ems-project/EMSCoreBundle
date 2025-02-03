/**
 * @license Copyright (c) 2003-2015, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */

'use strict';

( function() {
	CKEDITOR.dialog.add( 'link', function( editor ) {
		var plugin = CKEDITOR.plugins.link;
		let emsConfig = editor.config.hasOwnProperty('ems') ? editor.config.ems : {};
        if (undefined === emsConfig) {
            emsConfig = {}
        }

		// Handles the event when the "Target" selection box is changed.
		var targetChanged = function() {
				var dialog = this.getDialog(),
					popupFeatures = dialog.getContentElement( 'target', 'popupFeatures' ),
					targetName = dialog.getContentElement( 'target', 'linkTargetName' ),
					value = this.getValue();

				if ( !popupFeatures || !targetName )
					return;

				popupFeatures = popupFeatures.getElement();
				popupFeatures.hide();
				targetName.setValue( '' );

				switch ( value ) {
					case 'frame':
						targetName.setLabel( editor.lang.adv_link.targetFrameName );
						targetName.getElement().show();
						break;
					case 'popup':
						popupFeatures.show();
						targetName.setLabel( editor.lang.adv_link.targetPopupName );
						targetName.getElement().show();
						break;
					default:
						targetName.setValue( value );
						targetName.getElement().hide();
						break;
				}

			};

		// Handles the event when the "Type" selection box is changed.
		var linkTypeChanged = function() {
				var dialog = this.getDialog(),
					partIds = [ 'urlOptions', 'localPageOptions', 'fileLinkOptions', 'anchorOptions', 'emailOptions' ], // added by @simo - http://blog.xoundboy.com/?p=393
					typeValue = this.getValue(),
					uploadTab = dialog.definition.getContents( 'upload' ),
					uploadInitiallyHidden = uploadTab && uploadTab.hidden;

                dialog
                    .getContentElement('target', 'linkTargetType')
                    .setValue(emsConfig.hasOwnProperty('urlTargetDefaultBlank') && emsConfig.urlTargetDefaultBlank.includes(typeValue) ? '_blank' : 'notSet');

				if ( typeValue == 'url' || typeValue == 'localPage' || typeValue == 'fileLink' ) {
					if ( editor.config.linkShowTargetTab )
						dialog.showPage( 'target' );
					if ( !uploadInitiallyHidden )
						dialog.showPage( 'upload' );
				} else {
					dialog.hidePage( 'target' );
					if ( !uploadInitiallyHidden )
						dialog.hidePage( 'upload' );
				}

				for ( var i = 0; i < partIds.length; i++ ) {
					var element = dialog.getContentElement( 'info', partIds[ i ] );
					if ( !element )
						continue;

					element = element.getElement().getParent().getParent();
					if ( partIds[ i ] == typeValue + 'Options' )
						element.show();
					else
						element.hide();
				}

				dialog.layout();
			};

		var setupParams = function( page, data ) {
				if ( data[ page ] )
					this.setValue( data[ page ][ this.id ] || '' );
			};

		var setupPopupParams = function( data ) {
				return setupParams.call( this, 'target', data );
			};

		var setupAdvParams = function( data ) {
				return setupParams.call( this, 'advanced', data );
			};

		var commitParams = function( page, data ) {
				if ( !data[ page ] )
					data[ page ] = {};

				data[ page ][ this.id ] = this.getValue() || '';
			};

		var commitPopupParams = function( data ) {
				return commitParams.call( this, 'target', data );
			};

		var commitAdvParams = function( data ) {
				return commitParams.call( this, 'advanced', data );
			};

		var commonLang = editor.lang.common,
			linkLang = editor.lang.adv_link, // added by @simo - http://blog.xoundboy.com/?p=393
			anchors;


		let urlTypes = emsConfig.hasOwnProperty('urlTypes') ? emsConfig.urlTypes : ['url', 'anchor', 'localPage', 'fileLink', 'email'];
		let items = [];

		urlTypes.forEach((urlType) => {
			switch (urlType) {
				case 'url':
					items.push([linkLang.toUrl, 'url'])
					break;
				case 'anchor':
					items.push([linkLang.toAnchor, 'anchor'])
					break;
				case 'localPage':
					items.push([linkLang.localPages, 'localPage'])
					break;
				case 'fileLink':
					items.push([linkLang.file, 'fileLink'])
					break;
				case 'email':
					items.push([linkLang.toEmail, 'email'])
					break;
			}
		});

		let localContentTypes = ems_wysiwyg_type_filters;
		if (emsConfig.hasOwnProperty('urlAllContentTypes') && !emsConfig.urlAllContentTypes) {
			localContentTypes = localContentTypes.filter(values => !values.includes('All content types'));
		}

		var advLinkConfig = {
			title: linkLang.title,
			minWidth: 350,
			minHeight: 230,
			contents: [ {
				id: 'info',
				label: linkLang.info,
				title: linkLang.info,
				elements: [ {
					id: 'linkType',
					type: 'select',
					label: linkLang.type,
					'default': urlTypes[0],
					items: items,
					onChange: linkTypeChanged,
					setup: function( data ) {
						this.setValue( data.type || urlTypes[0]);
					},
					commit: function( data ) {
						data.type = this.getValue();
					}
				},
				{
					type : 'vbox',
					id : 'localPageOptions',
					children : [
					{
						type : 'select',
						label : linkLang.selectContentTypeLabel,
						id : 'contentTypeFilter',
						className : 'adv_link_type_filter',
						title : linkLang.selectContentTypeTitle,
						'default': localContentTypes[0][1],
						items : localContentTypes,
                        onChange: function () {
                            this.getDialog()
                                .getContentElement('target', 'linkTargetType')
                                .setValue(emsConfig.hasOwnProperty('urlTargetDefaultBlank') && emsConfig.urlTargetDefaultBlank.includes(this.getValue()) ? '_blank' : 'notSet');
                        },
					    setup: function( data ) {
					    	if(data.type == 'localPage' &&  data.filter){
					    		this.setValue( data.filter );
					    	}
						}
					},
					// added by @simo - http://blog.xoundboy.com/?p=393
					// see also : 	http://docs.ckeditor.com/source/dialogDefinition.html#CKEDITOR-dialog-definition-uiElement-property-type
					// 				http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
					{
						type : 'select',
						label : linkLang.selectPageLabel,
						id : 'localPage',
						className : 'select2',
						title : linkLang.selectPageTitle,
						items : [],
						onChange: function (event) {
							if (typeof event.data.value === 'object') {
								let id = event.data.value.id;
								let text = event.data.value.text;

								this.select2.val(null).trigger('change');
								if (this.select2.find("option[value='" + id + "']").length) {
									this.select2.val(id).trigger('change');
								} else {
									let newOption = new Option(text, id, true, true);
									this.select2.append(newOption).trigger('change');
								}
							}
						},
						onLoad : function(element) {

							var objectPicker = $('#'+this.domId);
							var typeFilter = objectPicker.parents('.cke_dialog_contents_body').find('select.adv_link_type_filter');

							this.select2 = objectPicker.find('select').select2({
								ajax: {
									url: object_search_url,
							    	dataType: 'json',
							    	delay: 250,
							    	data: function (params) {
										const data = {
											q: params.term, // search term
											page: params.page,
											type: typeFilter.val(),
											locale: editor.config.language
										}

										if (editor.config.referrerEmsId !== undefined) {
											data.referrerEmsId = editor.config.referrerEmsId; //for custom datalink views
										}

							    		return data;
								    },
									processResults: function (data, params) {
										// parse the results into the format expected by Select2
										// since we are using custom formatting functions we do not need to
										// alter the remote JSON data, except to indicate that infinite
										// scrolling can be used
										params.page = params.page || 1;

								      	return {
									        results: data.items,
									        pagination: {
									          more: (params.page * 30) < data.total_count
									        }
								      	};
							    	},
							    	cache: true
							  	},
							  	escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
							  	templateResult: formatRepo, // omitted for brevity, see the source of this page
							  	templateSelection: formatRepoSelection, // omitted for brevity, see the source of this page
							  	minimumInputLength: 1
							});
					    },
					    setup: function( data ) {
					    	if(data.type == 'localPage' &&  data.id){
                                this.setValue( data.id );
					    		var select2Select = $('#'+this.domId+' select.select2');
                                var itemLabel = linkLang.documentNotFound;
					    		$.ajax({
                                  url: object_search_url,
                                  data: {
                                    dataLink: data.id
                                  },
                                  dataType: 'json'
                                }).done(function(result) {
                                    if (result.items && result.items[0] && result.items[0].text) {
                                        itemLabel = result.items[0].text;
                                    }
                                }).always(function() {
                                    var newOption = new Option(itemLabel, data.id, false, false);
                                    select2Select.html(newOption).trigger('change');
                                });
					    	}
						},
						commit : function( data )
						{
							if (!data.localPage) {
								data.localPage = {};
							}
							data.localPage = 'ems://object:' + this.getValue();
							if (this.getInputElement().find('option:checked').count() > 0) {
							    data.pageLabel = this.getInputElement().find('option:checked').getItem(0).getText();
							} else {
							    data.pageLabel = '';
							}
						}
					}]
				},
				{
					type : 'vbox',
					id : 'fileLinkOptions',
					children : [
					{
						type : 'file',
						label : linkLang.selectFileLabel,
						id : 'file',
						className : 'upload-file',
						title : linkLang.selectFileTitle,
						items : []
					},{
                        type: 'button',
                        id: 'fileBrowse',
                        hidden: 'true',
                        filebrowser: 'info:fileTxt',
                        label: commonLang.browseServer
                    },{
                        type : 'text',
                        label : linkLang.selectFileFilenameLabel,
                        id : 'fileLink',
                        className : 'filename',
                        title : linkLang.selectFileFilenameTitle,
                        items : [],
                        onLoad : function(element) {
                        },
					    setup: function( data ) {
						    var body = $('body');
						    var hashAlgo = body.data('hash-algo');
						    var initUpload = body.data('init-upload');
                            self = this;
                            var fileUploadField = this.getDialog().getContentElement( 'info', 'file' )
                            var fileInfo = [];
                            var onFileChangeFunction = function(event){
								self.getDialog().getContentElement( 'info', 'fileLink' ).setValue('Upload starting...');
								for (var loop = 0; loop < event.target.files.length; loop++) {
									var fileUploader = new FileUploader({
										file: event.target.files[loop],
										algo: hashAlgo,
										initUrl: initUpload,
										emsListener: self,
										onHashAvailable: function(hash, type, name){
											fileInfo['hash'] = hash;
											fileInfo['type'] = type;
											fileInfo['name'] = name;
											self.getDialog().getContentElement( 'info', 'fileLink' ).setValue('File\'s hash: '+hash);
										},
										onProgress: function(status, progress, remaining){
											self.getDialog().getContentElement( 'info', 'fileLink' ).setValue('Upload in progress: '+remaining);
										},
										onUploaded: function(assetUrl, previewUrl){
											var link = 'ems://asset:' + fileInfo['hash'] + '?name=' + encodeURI(fileInfo['name']) + '&type=' + encodeURI(fileInfo['type']);
											self.getDialog().getContentElement( 'info', 'fileLink' ).setValue(fileInfo['name']);
											self.getDialog().getContentElement( 'info', 'fileLink' ).getInputElement().$.setAttribute('data-link', link);
										},
										onError: function(message, code){
											alert(message);
										},
									});
									break;
								}

							};

                            var node = fileUploadField.getInputElement().$;
                            if ('INPUT' !== node.nodeName) {
                            	var iframe = node.getElementsByTagName('iframe')[0];
								var iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
								iframe.onload = function(){
									iframeDocument.querySelector('input').onchange = onFileChangeFunction;
								};
							} else {
								node.onchange = onFileChangeFunction;
							}
						},
                        commit : function( data ) {
							if ( !data.filename ) {
								data.filename = {};
							}
							data.filename = this.getValue();

							if ( !data.fileLink ) {
								data.fileLink = {};
							}
							data.fileLink = self.getDialog().getContentElement( 'info', 'fileLink' ).getInputElement().$.getAttribute('data-link');
                        }
                    }]
				},
				{
					type: 'vbox',
					id: 'urlOptions',
					children: [ {
						type: 'hbox',
						widths: [ '25%', '75%' ],
						children: [ {
							id: 'protocol',
							type: 'select',
							label: commonLang.protocol,
							'default': 'http://',
							items: [
								// Force 'ltr' for protocol names in BIDI. (#5433)
								[ 'http://\u200E', 'http://' ],
								[ 'https://\u200E', 'https://' ],
								[ 'ftp://\u200E', 'ftp://' ],
								[ 'news://\u200E', 'news://' ],
								[ linkLang.other, '' ]
							],
							setup: function( data ) {
								if ( data.url )
									this.setValue( data.url.protocol || '' );
							},
							commit: function( data ) {
								if ( !data.url )
									data.url = {};

								data.url.protocol = this.getValue();
							}
						},
						{
							type: 'text',
							id: 'url',
							label: commonLang.url,
							required: true,
							onLoad: function() {
								this.allowOnChange = true;
							},
							onKeyUp: function() {
								this.allowOnChange = false;
								var protocolCmb = this.getDialog().getContentElement( 'info', 'protocol' ),
									url = this.getValue(),
									urlOnChangeProtocol = /^(http|https|ftp|news):\/\/(?=.)/i,
									urlOnChangeTestOther = /^((javascript:)|[#\/\.\?])/i;

								var protocol = urlOnChangeProtocol.exec( url );
								if ( protocol ) {
									this.setValue( url.substr( protocol[ 0 ].length ) );
									protocolCmb.setValue( protocol[ 0 ].toLowerCase() );
								} else if ( urlOnChangeTestOther.test( url ) ) {
									protocolCmb.setValue( '' );
								}

								this.allowOnChange = true;
							},
							onChange: function() {
								if ( this.allowOnChange ) // Dont't call on dialog load.
								this.onKeyUp();
							},
							validate: function() {
								var dialog = this.getDialog();

								if ( dialog.getContentElement( 'info', 'linkType' ) && dialog.getValueOf( 'info', 'linkType' ) != 'url' )
									return true;

								if ( !editor.config.linkJavaScriptLinksAllowed && ( /javascript\:/ ).test( this.getValue() ) ) {
									alert( commonLang.invalidValue ); // jshint ignore:line
									return false;
								}

								if ( this.getDialog().fakeObj ) // Edit Anchor.
								return true;

								var func = CKEDITOR.dialog.validate.notEmpty( linkLang.noUrl );
								return func.apply( this );
							},
							setup: function( data ) {
								this.allowOnChange = false;
								if ( data.url )
									this.setValue( data.url.url );
								this.allowOnChange = true;

							},
							commit: function( data ) {
								// IE will not trigger the onChange event if the mouse has been used
								// to carry all the operations #4724
								this.onChange();

								if ( !data.url )
									data.url = {};

								data.url.url = this.getValue();
								this.allowOnChange = false;
							}
						} ],
						setup: function() {
							if ( !this.getDialog().getContentElement( 'info', 'linkType' ) )
								this.getElement().show();
						}
					} ]
				},
				{
					type: 'vbox',
					id: 'anchorOptions',
					width: 260,
					align: 'center',
					padding: 0,
					children: [ {
						type: 'fieldset',
						id: 'selectAnchorText',
						label: linkLang.selectAnchor,
						setup: function() {
							anchors = plugin.getEditorAnchors( editor );

							this.getElement()[ anchors && anchors.length ? 'show' : 'hide' ]();
						},
						children: [ {
							type: 'hbox',
							id: 'selectAnchor',
							children: [ {
								type: 'select',
								id: 'anchorName',
								'default': '',
								label: linkLang.anchorName,
								style: 'width: 100%;',
								items: [
									[ '' ]
								],
								setup: function( data ) {
									this.clear();
									this.add( '' );

									if ( anchors ) {
										for ( var i = 0; i < anchors.length; i++ ) {
											if ( anchors[ i ].name )
												this.add( anchors[ i ].name );
										}
									}

									if ( data.anchor )
										this.setValue( data.anchor.name );

									var linkType = this.getDialog().getContentElement( 'info', 'linkType' );
									if ( linkType && linkType.getValue() == 'email' )
										this.focus();
								},
								commit: function( data ) {
									if ( !data.anchor )
										data.anchor = {};

									data.anchor.name = this.getValue();
								}
							},
							{
								type: 'select',
								id: 'anchorId',
								'default': '',
								label: linkLang.anchorId,
								style: 'width: 100%;',
								items: [
									[ '' ]
								],
								setup: function( data ) {
									this.clear();
									this.add( '' );

									if ( anchors ) {
										for ( var i = 0; i < anchors.length; i++ ) {
											if ( anchors[ i ].id )
												this.add( anchors[ i ].id );
										}
									}

									if ( data.anchor )
										this.setValue( data.anchor.id );
								},
								commit: function( data ) {
									if ( !data.anchor )
										data.anchor = {};

									data.anchor.id = this.getValue();
								}
							} ],
							setup: function() {
								this.getElement()[ anchors && anchors.length ? 'show' : 'hide' ]();
							}
						} ]
					},
					{
						type: 'html',
						id: 'noAnchors',
						style: 'text-align: center;',
						html: '<div role="note" tabIndex="-1">' + CKEDITOR.tools.htmlEncode( linkLang.noAnchors ) + '</div>',
						// Focus the first element defined in above html.
						focus: true,
						setup: function() {
							this.getElement()[ anchors && anchors.length ? 'hide' : 'show' ]();
						}
					} ],
					setup: function() {
						if ( !this.getDialog().getContentElement( 'info', 'linkType' ) )
							this.getElement().hide();
					}
				},
				{
					type: 'vbox',
					id: 'emailOptions',
					padding: 1,
					children: [ {
						type: 'text',
						id: 'emailAddress',
						label: linkLang.emailAddress,
						required: true,
						validate: function() {
							var dialog = this.getDialog();

							if ( !dialog.getContentElement( 'info', 'linkType' ) || dialog.getValueOf( 'info', 'linkType' ) != 'email' )
								return true;

							var func = CKEDITOR.dialog.validate.notEmpty( linkLang.noEmail );
							return func.apply( this );
						},
						setup: function( data ) {
							if ( data.email )
								this.setValue( data.email.address );

							var linkType = this.getDialog().getContentElement( 'info', 'linkType' );
							if ( linkType && linkType.getValue() == 'email' )
								this.select();
						},
						commit: function( data ) {
							if ( !data.email )
								data.email = {};

							data.email.address = this.getValue();
						}
					},
					{
						type: 'text',
						id: 'emailSubject',
						label: linkLang.emailSubject,
						setup: function( data ) {
							if ( data.email )
								this.setValue( data.email.subject );
						},
						commit: function( data ) {
							if ( !data.email )
								data.email = {};

							data.email.subject = this.getValue();
						}
					},
					{
						type: 'textarea',
						id: 'emailBody',
						label: linkLang.emailBody,
						rows: 3,
						'default': '',
						setup: function( data ) {
							if ( data.email )
								this.setValue( data.email.body );
						},
						commit: function( data ) {
							if ( !data.email )
								data.email = {};

							data.email.body = this.getValue();
						}
					} ],
					setup: function() {
						if ( !this.getDialog().getContentElement( 'info', 'linkType' ) )
							this.getElement().hide();
					}
				} ]
			},
			{
				id: 'target',
				requiredContent: 'a[target]', // This is not fully correct, because some target option requires JS.
				label: linkLang.target,
				title: linkLang.target,
				elements: [ {
					type: 'hbox',
					widths: [ '50%', '50%' ],
					children: [ {
						type: 'select',
						id: 'linkTargetType',
						label: commonLang.target,
						'default': 'notSet',
						style: 'width : 100%;',
						'items': [
							[ commonLang.notSet, 'notSet' ],
							[ linkLang.targetFrame, 'frame' ],
							[ linkLang.targetPopup, 'popup' ],
							[ commonLang.targetNew, '_blank' ],
							[ commonLang.targetTop, '_top' ],
							[ commonLang.targetSelf, '_self' ],
							[ commonLang.targetParent, '_parent' ]
						],
						onChange: targetChanged,
						setup: function( data ) {
							if ( data.target )
								this.setValue( data.target.type || 'notSet' );
							targetChanged.call( this );
						},
						commit: function( data ) {
							if ( !data.target )
								data.target = {};

							data.target.type = this.getValue();
						}
					},
					{
						type: 'text',
						id: 'linkTargetName',
						label: linkLang.targetFrameName,
						'default': '',
						setup: function( data ) {
							if ( data.target )
								this.setValue( data.target.name );
						},
						commit: function( data ) {
							if ( !data.target )
								data.target = {};

							data.target.name = this.getValue().replace( /\W/gi, '' );
						}
					} ]
				},
				{
					type: 'vbox',
					width: '100%',
					align: 'center',
					padding: 2,
					id: 'popupFeatures',
					children: [ {
						type: 'fieldset',
						label: linkLang.popupFeatures,
						children: [ {
							type: 'hbox',
							children: [ {
								type: 'checkbox',
								id: 'resizable',
								label: linkLang.popupResizable,
								setup: setupPopupParams,
								commit: commitPopupParams
							},
							{
								type: 'checkbox',
								id: 'status',
								label: linkLang.popupStatusBar,
								setup: setupPopupParams,
								commit: commitPopupParams

							} ]
						},
						{
							type: 'hbox',
							children: [ {
								type: 'checkbox',
								id: 'location',
								label: linkLang.popupLocationBar,
								setup: setupPopupParams,
								commit: commitPopupParams

							},
							{
								type: 'checkbox',
								id: 'toolbar',
								label: linkLang.popupToolbar,
								setup: setupPopupParams,
								commit: commitPopupParams

							} ]
						},
						{
							type: 'hbox',
							children: [ {
								type: 'checkbox',
								id: 'menubar',
								label: linkLang.popupMenuBar,
								setup: setupPopupParams,
								commit: commitPopupParams

							},
							{
								type: 'checkbox',
								id: 'fullscreen',
								label: linkLang.popupFullScreen,
								setup: setupPopupParams,
								commit: commitPopupParams

							} ]
						},
						{
							type: 'hbox',
							children: [ {
								type: 'checkbox',
								id: 'scrollbars',
								label: linkLang.popupScrollBars,
								setup: setupPopupParams,
								commit: commitPopupParams

							},
							{
								type: 'checkbox',
								id: 'dependent',
								label: linkLang.popupDependent,
								setup: setupPopupParams,
								commit: commitPopupParams

							} ]
						},
						{
							type: 'hbox',
							children: [ {
								type: 'text',
								widths: [ '50%', '50%' ],
								labelLayout: 'horizontal',
								label: commonLang.width,
								id: 'width',
								setup: setupPopupParams,
								commit: commitPopupParams

							},
							{
								type: 'text',
								labelLayout: 'horizontal',
								widths: [ '50%', '50%' ],
								label: linkLang.popupLeft,
								id: 'left',
								setup: setupPopupParams,
								commit: commitPopupParams

							} ]
						},
						{
							type: 'hbox',
							children: [ {
								type: 'text',
								labelLayout: 'horizontal',
								widths: [ '50%', '50%' ],
								label: commonLang.height,
								id: 'height',
								setup: setupPopupParams,
								commit: commitPopupParams

							},
							{
								type: 'text',
								labelLayout: 'horizontal',
								label: linkLang.popupTop,
								widths: [ '50%', '50%' ],
								id: 'top',
								setup: setupPopupParams,
								commit: commitPopupParams

							} ]
						} ]
					} ]
				} ]
			},
			{
				id: 'upload',
				label: linkLang.upload,
				title: linkLang.upload,
				hidden: true,
				filebrowser: 'uploadButton',
				elements: [ {
					type: 'file',
					id: 'upload',
					label: commonLang.upload,
					style: 'height:40px',
					size: 29
				},
				{
					type: 'fileButton',
					id: 'uploadButton',
					label: commonLang.uploadSubmit,
					filebrowser: 'info:url',
					'for': [ 'upload', 'upload' ]
				} ]
			},
			{
				id: 'advanced',
				label: linkLang.advanced,
				title: linkLang.advanced,
				elements: [ {
					type: 'vbox',
					padding: 1,
					children: [ {
						type: 'hbox',
						widths: [ '45%', '35%', '20%' ],
						children: [ {
							type: 'text',
							id: 'advId',
							requiredContent: 'a[id]',
							label: linkLang.id,
							setup: setupAdvParams,
							commit: commitAdvParams
						},
						{
							type: 'select',
							id: 'advLangDir',
							requiredContent: 'a[dir]',
							label: linkLang.langDir,
							'default': '',
							style: 'width:110px',
							items: [
								[ commonLang.notSet, '' ],
								[ linkLang.langDirLTR, 'ltr' ],
								[ linkLang.langDirRTL, 'rtl' ]
							],
							setup: setupAdvParams,
							commit: commitAdvParams
						},
						{
							type: 'text',
							id: 'advAccessKey',
							requiredContent: 'a[accesskey]',
							width: '80px',
							label: linkLang.acccessKey,
							maxLength: 1,
							setup: setupAdvParams,
							commit: commitAdvParams

						} ]
					},
					{
						type: 'hbox',
						widths: [ '45%', '35%', '20%' ],
						children: [ {
							type: 'text',
							label: linkLang.name,
							id: 'advName',
							requiredContent: 'a[name]',
							setup: setupAdvParams,
							commit: commitAdvParams

						},
						{
							type: 'text',
							label: linkLang.langCode,
							id: 'advLangCode',
							requiredContent: 'a[lang]',
							width: '110px',
							'default': '',
							setup: setupAdvParams,
							commit: commitAdvParams

						},
						{
							type: 'text',
							label: linkLang.tabIndex,
							id: 'advTabIndex',
							requiredContent: 'a[tabindex]',
							width: '80px',
							maxLength: 5,
							setup: setupAdvParams,
							commit: commitAdvParams

						} ]
					} ]
				},
				{
					type: 'vbox',
					padding: 1,
					children: [ {
						type: 'hbox',
						widths: [ '45%', '55%' ],
						children: [ {
							type: 'text',
							label: linkLang.advisoryTitle,
							requiredContent: 'a[title]',
							'default': '',
							id: 'advTitle',
							setup: setupAdvParams,
							commit: commitAdvParams

						},
						{
							type: 'text',
							label: linkLang.advisoryContentType,
							requiredContent: 'a[type]',
							'default': '',
							id: 'advContentType',
							setup: setupAdvParams,
							commit: commitAdvParams

						} ]
					},
					{
						type: 'hbox',
						widths: [ '45%', '55%' ],
						children: [ {
							type: 'text',
							label: linkLang.cssClasses,
							requiredContent: 'a(cke-xyz)', // Random text like 'xyz' will check if all are allowed.
							'default': '',
							id: 'advCSSClasses',
							setup: setupAdvParams,
							commit: commitAdvParams

						},
						{
							type: 'text',
							label: linkLang.charset,
							requiredContent: 'a[charset]',
							'default': '',
							id: 'advCharset',
							setup: setupAdvParams,
							commit: commitAdvParams

						} ]
					},
					{
						type: 'hbox',
						widths: [ '45%', '55%' ],
						children: [ {
							type: 'text',
							label: linkLang.rel,
							requiredContent: 'a[rel]',
							'default': '',
							id: 'advRel',
							setup: setupAdvParams,
							commit: commitAdvParams
						},
						{
							type: 'text',
							label: linkLang.styles,
							requiredContent: 'a{cke-xyz}', // Random text like 'xyz' will check if all are allowed.
							'default': '',
							id: 'advStyles',
							validate: CKEDITOR.dialog.validate.inlineStyle( editor.lang.common.invalidInlineStyle ),
							setup: setupAdvParams,
							commit: commitAdvParams
						} ]
					} ]
				} ]
			} ],
			onShow: function() {
				var editor = this.getParentEditor(),
					selection = editor.getSelection(),
					element = null;

				// Fill in all the relevant fields if there's already one link selected.
				if ( ( element = plugin.getSelectedLink( editor ) ) && element.hasAttribute( 'href' ) ) {
					// Don't change selection if some element is already selected.
					// For example - don't destroy fake selection.
					if ( !selection.getSelectedElement() )
						selection.selectElement( element );
				} else {
					element = null;
				}

				var data = plugin.parseLinkAttributes( editor, element );

				// Record down the selected element in the dialog.
				this._.selectedElement = element;

				this.setupContent( data );


			},
			onOk: function() {
				var data = {};

				// Collect data from fields.
				this.commitContent( data );

				var selection = editor.getSelection(),
					attributes = plugin.getLinkAttributes( editor, data );

				if ( !this._.selectedElement ) {
					var range = selection.getRanges()[ 0 ];

					// Use link URL as text with a collapsed cursor.
					if ( range.collapsed ) {
						if (data.type == 'fileLink') {
                            var text = new CKEDITOR.dom.text( data.filename, editor.document );
                        } else if (data.type == 'localPage') {
                            var text = new CKEDITOR.dom.text(data.pageLabel, editor.document );
						} else {
							// Short mailto link text view (#5736).
							var text = new CKEDITOR.dom.text( data.type == 'email' ?
							data.email.address : attributes.set[ 'data-cke-saved-href' ], editor.document );
						}
						range.insertNode( text );
						range.selectNodeContents( text );

					}

					// Apply style.
					var style = new CKEDITOR.style( {
						element: 'a',
						attributes: attributes.set
					} );

					style.type = CKEDITOR.STYLE_INLINE; // need to override... dunno why.
					style.applyToRange( range, editor );
					range.select();
				} else {
					// We're only editing an existing link, so just overwrite the attributes.
					var element = this._.selectedElement,
						href = element.data( 'cke-saved-href' ),
						textView = element.getHtml();

					element.setAttributes( attributes.set );
					element.removeAttributes( attributes.removed );

					// Update text view when user changes protocol (#4612).
					if ( href == textView || data.type == 'email' && textView.indexOf( '@' ) != -1 ) {
						// Short mailto link text view (#5736).
						element.setHtml( data.type == 'email' ?
							data.email.address : attributes.set[ 'data-cke-saved-href' ] );

						// We changed the content, so need to select it again.
						selection.selectElement( element );
					}

					delete this._.selectedElement;
				}

			},
			onLoad: function() {

				if ( !editor.config.linkShowAdvancedTab )
					this.hidePage( 'advanced' ); //Hide Advanded tab.

				if ( !editor.config.linkShowTargetTab )
					this.hidePage( 'target' ); //Hide Target tab.


			},
			// Inital focus on 'url' field if link is of type URL.
			onFocus: function() {

				var linkType = this.getContentElement( 'info', 'linkType' ),
					urlField;

				if ( linkType && linkType.getValue() == 'url' ) {
					urlField = this.getContentElement( 'info', 'url' );
					urlField.select();

				}

			}
		};

		if(editor.config.hideAssetLink) {
			advLinkConfig.contents["0"].elements.splice(2,1);
			advLinkConfig.contents["0"].elements["0"].items.splice(3,1);
		}

		return advLinkConfig;
	} );
} )();
// jscs:disable maximumLineLength
/**
 * The e-mail address anti-spam protection option. The protection will be
 * applied when creating or modifying e-mail links through the editor interface.
 *
 * Two methods of protection can be chosen:
 *
 * 1. The e-mail parts (name, domain, and any other query string) are
 *     assembled into a function call pattern. Such function must be
 *     provided by the developer in the pages that will use the contents.
 * 2. Only the e-mail address is obfuscated into a special string that
 *     has no meaning for humans or spam bots, but which is properly
 *     rendered and accepted by the browser.
 *
 * Both approaches require JavaScript to be enabled.
 *
 *		// href="mailto:tester@ckeditor.com?subject=subject&body=body"
 *		config.emailProtection = '';
 *
 *		// href="<a href=\"javascript:void(location.href=\'mailto:\'+String.fromCharCode(116,101,115,116,101,114,64,99,107,101,100,105,116,111,114,46,99,111,109)+\'?subject=subject&body=body\')\">e-mail</a>"
 *		config.emailProtection = 'encode';
 *
 *		// href="javascript:mt('tester','ckeditor.com','subject','body')"
 *		config.emailProtection = 'mt(NAME,DOMAIN,SUBJECT,BODY)';
 *
 * @since 3.1
 * @cfg {String} [emailProtection='' (empty string = disabled)]
 * @member CKEDITOR.config
 */
