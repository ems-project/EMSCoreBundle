'use strict';

window.getAssocArray = function(input) {
    let output = [];
    for (let item in input){
        if(input.hasOwnProperty(item)) {
            output.push({
                ouuid: input[item].id,
                children: getAssocArray(input[item].children),
            });
        }
    }
    return output;
};

// color can be a hx string or an array of RGB values 0-255
window.luma = function(color) {
    const rgb = (typeof color === 'string') ? hexToRGBArray(color) : color;
    return (0.2126 * rgb[0]) + (0.7152 * rgb[1]) + (0.0722 * rgb[2]); // SMPTE C, Rec. 709 weightings
};

window.hexToRGBArray = function(color) {
    if (color.length === 3)
        color = color.charAt(0) + color.charAt(0) + color.charAt(1) + color.charAt(1) + color.charAt(2) + color.charAt(2);
    else if (color.length !== 6)
        throw('Invalid hex color: ' + color);
    let rgb = [];
    for (let i = 0; i <= 2; i++)
        rgb[i] = parseInt(color.substr(i * 2, 2), 16);
    return rgb;
};


window.formatRepo = function(repo) {
    if (repo.loading) return repo.text;

    return "<div class='select2-result-repository clearfix'>" +
        repo.text + "</div>";
};

window.formatRepoSelection = function(repo) {
    let tooltip;
    if (repo.hasOwnProperty('element') && repo.element instanceof HTMLElement) {
        tooltip = repo.element.dataset.tooltip ?? null;
    } else {
        tooltip = repo.tooltip ?? null;
    }

    if (tooltip !== null) {
        let item = $('<span data-toggle="tooltip" title="'+ tooltip +'">'+ repo.text +'</span>');
        item.tooltip();
        return item;
    }
    return repo.text;
};

window.objectPickerListeners = function(objectPicker, maximumSelectionLength){
    const type = objectPicker.data('type');
    const dynamicLoading = objectPicker.data('dynamic-loading');
    const searchId = objectPicker.data('search-id');
    const querySearch = objectPicker.data('query-search');

    let params = {
        escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
        templateResult: formatRepo, // omitted for brevity, see the source of this page
        templateSelection: formatRepoSelection // omitted for brevity, see the source of this page
    };

    if(maximumSelectionLength) {
        params.maximumSelectionLength = maximumSelectionLength;
    }
    else if(objectPicker.attr('multiple')) {
        params.allowClear = true;
        params.closeOnSelect = false;
    }

    if (dynamicLoading) {
        //params.minimumInputLength = 1,
        params.ajax = {
            url: object_search_url,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term, // search term
                    page: params.page,
                    type: type,
                    searchId: searchId,
                    querySearch: querySearch
                };
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
        };
    }

    objectPicker.select2(params);
};

window.requestNotification = function(element, tId, envName, ctId, id){
    const data = { templateId : tId, environmentName : envName, contentTypeId : ctId, ouuid : id};
    window.ajaxRequest.post(element.getAttribute("data-url") , data, 'modal-notifications');
};
