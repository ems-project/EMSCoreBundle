var CkEditorFilesBrowser = {};

CkEditorFilesBrowser.folders = [];
CkEditorFilesBrowser.files = {}; //folder => list of files
CkEditorFilesBrowser.ckFunctionNum = null;

CkEditorFilesBrowser.$folderSwitcher = null;
CkEditorFilesBrowser.$filesContainer = null;

CkEditorFilesBrowser.init = function () {
	CkEditorFilesBrowser.$folderSwitcher = $('#js-folder-switcher');
	CkEditorFilesBrowser.$filesContainer = $('#js-files-container');

	var baseHref = CkEditorFilesBrowser.getQueryStringParam("baseHref");
	if (baseHref) {
		var h = (document.head || document.getElementsByTagName("head")[0]),
			el = h.getElementsByTagName("link")[0];
		el.href = location.href.replace(/\/[^\/]*$/,"/browser.css");
		(h.getElementsByTagName("base")[0]).href = baseHref;
	}

	CkEditorFilesBrowser.ckFunctionNum = CkEditorFilesBrowser.getQueryStringParam('CKEditorFuncNum');

	CkEditorFilesBrowser.initEventHandlers();

	CkEditorFilesBrowser.loadData(CkEditorFilesBrowser.getQueryStringParam('listUrl'), function () {
		CkEditorFilesBrowser.initFolderSwitcher();
	});
};

CkEditorFilesBrowser.loadData = function (url, onLoaded) {
	CkEditorFilesBrowser.folders = [];
	CkEditorFilesBrowser.files = {};

	$.getJSON(url, function (list) {
		$.each(list, function (_idx, item) {
			if (typeof(item.folder) === 'undefined') {
				item.folder = 'Files';
			}

			if (typeof(item.thumb) === 'undefined') {
				item.thumb = item.file;
			}

			CkEditorFilesBrowser.addFile(item.folder, item.file, item.thumb, item.type, item.name);
		});

		onLoaded();
	}).error(function(jqXHR, textStatus, errorThrown) {
		var errorMessage;
		if (jqXHR.status < 200 || jqXHR.status >= 400) {
			errorMessage = 'HTTP Status: ' + jqXHR.status + '/' + jqXHR.statusText + ': "<strong style="color: red;">' + url + '</strong>"';
		} else if (textStatus === 'parsererror') {
			errorMessage = textStatus + ': invalid JSON file: "<strong style="color: red;">' + url + '</strong>": ' + errorThrown.message;
		} else {
			errorMessage = textStatus + ' / ' + jqXHR.statusText + ' / ' + errorThrown.message;
		}
		CkEditorFilesBrowser.$filesContainer.html(errorMessage);
    });
};

CkEditorFilesBrowser.addFile = function (folderName, fileUrl, thumbUrl, type, fileName) {
	if (typeof(CkEditorFilesBrowser.files[folderName]) === 'undefined') {
		CkEditorFilesBrowser.folders.push(folderName);
		CkEditorFilesBrowser.files[folderName] = [];
	}
	
    CkEditorFilesBrowser.files[folderName].push({
        "fileName": fileName,
        "fileUrl": fileUrl,
        "type": type
    });
};

CkEditorFilesBrowser.initFolderSwitcher = function () {
	var $switcher = CkEditorFilesBrowser.$folderSwitcher;

	$switcher.find('li').remove();

	$.each(CkEditorFilesBrowser.folders, function (idx, folderName) {
		var $option = $('<li></li>').data('idx', idx).text(folderName);
		$option.appendTo($switcher);
	});


	if (CkEditorFilesBrowser.folders.length === 0) {
		$switcher.remove();
		CkEditorFilesBrowser.$filesContainer.text('No Files.');
	} else {
		if (CkEditorFilesBrowser.folders.length === 1) {
			$switcher.hide();
		}

		$switcher.find('li:first').click();
	}
};

CkEditorFilesBrowser.renderfilesForFolder = function (folderName) {
	var files = CkEditorFilesBrowser.files[folderName],
		temlateHtml = $('#js-template').html();

	CkEditorFilesBrowser.$filesContainer.html('');
	var table = $('<table id="tableFiles"/>');

	$.each(files, function (_idx, fileData) {
	    let html = temlateHtml;
        html = html.replace('%fileUrl%', fileData.fileUrl);
        html = html.replace('%fileName%', fileData.fileName);
        html = html.replace('%mimeType%', fileData.type);
	    
        if (fileData.type !== "undefined" && fileData.type.match("^image/") !== null) {
            html = html.replace('%type%', 'image');
	    } else if (fileData.type !== "undefined" && fileData.type.match("/pdf") !== null) {
            html = html.replace('%type%', 'pdf');
        } else if (fileData.type !== "undefined" && fileData.type.match("zip") !== null) {
            html = html.replace('%type%', 'zip');
        } else if (fileData.type !== "undefined" && fileData.type.match("msword") !== null || fileData.type.match("wordprocessingml") !== null) {
            html = html.replace('%type%', 'doc');
        } else if (fileData.type !== "undefined" && fileData.type.match("powerpoint") !== null || fileData.type.match("presentationml") !== null) {
            html = html.replace('%type%', 'ppt');
        } else if (fileData.type !== "undefined" && fileData.type.match("ms-excel") !== null || fileData.type.match("spreadsheetml") !== null) {
            html = html.replace('%type%', 'xls');
        } else {
            html = html.replace('%type%', 'txt');
        }
		var $item = $($.parseHTML(html));
		
		table.append($item);
	});
	
	CkEditorFilesBrowser.$filesContainer.append(table);
};

CkEditorFilesBrowser.initEventHandlers = function () {
	$(document).on('click', '#js-folder-switcher li', function () {
		var idx = parseInt($(this).data('idx'), 10),
			folderName = CkEditorFilesBrowser.folders[idx];

		$(this).siblings('li').removeClass('active');
		$(this).addClass('active');

		CkEditorFilesBrowser.renderfilesForFolder(folderName);
	});

	$(document).on('click', '.js-link', function () {
	    let fileUrl = $(this).data('url');
	    let data = $(this).text();
		window.opener.CKEDITOR.tools.callFunction(CkEditorFilesBrowser.ckFunctionNum, fileUrl, function() {
            // Get the reference to a dialog window.
            var dialog = this.getDialog();
            // Check if this is the Link Properties dialog window.
            if ( dialog.getName() == 'link' ) {
                dialog.getContentElement( 'info', 'fileLink' ).setValue(data);
                dialog.getContentElement( 'info', 'fileLink' ).getInputElement().$.setAttribute('data-link', fileUrl);
            }
        } );
		window.close();
	});
};

CkEditorFilesBrowser.getQueryStringParam = function (name) {
	var regex = new RegExp('[?&]' + name + '=([^&]*)'),
		result = window.location.search.match(regex);

	return (result && result.length > 1 ? decodeURIComponent(result[1]) : null);
};
