import jquery from 'jquery';
import EmsListeners from './EmsListeners';
import {addEventListeners as editRevisionAddEventListeners} from './../edit-revision';
require('./nestedSortable');

const uuidv4 = require('uuid/v4');

export default class JsonMenuEditor {
    constructor(target) {
        const self = this;
        this.parent = $(target);
        this.hiddenField = this.parent.find('input').first();
        this.name = this.parent.data('name');
        this.isNested = this.parent.hasClass('json_menu_nested_editor');
        if(this.isNested) {
            this.$nestedModal = $('#json-menu-nested-modal-'+this.name);
            this.initNestedModal();
        }

        this.nestedSortable = this.parent.find('ol.json_menu_sortable').nestedSortable({
            handle: 'a.json_menu_sortable_handle_button',
            items: 'li.nestedSortable',
            isTree: true,
            expression: /()(.+)/,
            toleranceElement: '> div',
            stop: function () {
                self.relocate();
            },
            isAllowed: function(placeholder, parent, current) {
                const item = $(current).data();
                const parentData = parent ?  $(parent).data() : $(current).closest('ol.json_menu_sortable').data();

                if (parentData.hasOwnProperty('node') && parentData.node.hasOwnProperty('deny')) {
                    const deny = parentData.node.deny;
                    return deny.indexOf(item.node.name) === -1;
                }

                return true;
            }
        });

        this.addListeners(target);
        this.updateCollapseButtons();
    }

    addListeners(target) {
        const jTarget = jquery(target);
        const self = this;
        jTarget.find('.json_menu_sortable_remove_button')
            .on('click', function(event) {
                self.removeElement(this, event);
            });
        jTarget.find('.json_menu_sortable_add_item_button')
            .on('click', function(event) {
                event.preventDefault();
                self.addItem($(this), 'prototype-item', $(this).data());
            });
        jTarget.find('.json_menu_sortable_add_node_button')
            .on('click', function(event) {
                event.preventDefault();
                self.addItem($(this), 'prototype-node', $(this).data());
            });
        jTarget.find('input.itemLabel')
            .on('input', function(event) {
                self.updateLabel(this, event);
            });

        if (this.isNested) {
            jTarget.find('.json-menu-nested-add').on('click', function() {
                self.$nestedModal.modal('show', $(this));
            });
            jTarget.find('.json-menu-nested-edit').on('click', function() {
                self.$nestedModal.modal('show', $(this));
            });
        }
    }

    updateLabel(target, event) {
        jquery(target).closest('li').data('label', jquery(target).val());
        this.relocate();
    }

    removeElement(target, event) {
        event.preventDefault();
        jquery(target).closest('li').remove();
        this.relocate();
    }

    addItem($target, prototypeTarget, data) {
        const uuid = uuidv4();
        let itemHtml = this.parent.find('.json_menu_editor_fieldtype_widget').data(prototypeTarget);
        itemHtml = itemHtml.replace(/%uuid%/g, uuid);
        for (const [key, value] of Object.entries(data)) {
            if (typeof value !== 'object') {
                itemHtml = itemHtml.replace(new RegExp('%' + key + '%', 'g'), value);
            }
        }

        let item = $(itemHtml);
        if (data.hasOwnProperty('object')) {
            item.data('object', data.object);
        }
        if (data.hasOwnProperty('node')) {
            item.data('node', data.node);
        }

        let list = $target.closest('.nestedSortable').closest('li');
        if (list.length === 0) {
            list = this.parent.find('.json_menu_editor_fieldtype_widget');
        }

        if (list.children('ol').length > 0) {
            list.children('ol').append(item);
        } else {
            list.append($("<ol></ol>").append(item));
        }
        list.find('.button-collapse:first').attr('aria-expanded', false);

        const element = jquery('#'+uuid);
        this.addListeners(element);
        new EmsListeners(element.get(0));
        this.relocate();
        this.setFocus(uuid);
    }

    setFocus(uuid) {
        jquery('#'+uuid).find('input').focus();
    }

    updateCollapseButtons() {
        this.parent.find('li.nestedSortable').each(function () {
            let $button = $(this).find('.button-collapse:first');

            if ($(this).find('ol:first li').length === 0) {
                $button.css('display', 'none');
            } else {
                $button.show();
            }
        });
    }

    relocate() {
        this.updateCollapseButtons();
        const hierarchy = this.nestedSortable.nestedSortable('toHierarchy', {startDepthCount: 0});
        this.hiddenField.val(JSON.stringify(hierarchy)).trigger("input").trigger("change");
    }

    initNestedModal()
    {
        const self = this;

        function modalStateActive($modal) {
            $modal.find('.modal-loading').hide();
            $modal.find('.ajax-content').show();
            $modal.find('form :input').prop('disabled', false);
            $modal.find('.btn-json-menu-nested-save').prop('disabled', false);
            $modal.find('.btn-json-menu-nested-save').show();
        }
        function modalStateloading($modal) {
            $modal.find('.modal-loading').show();
            $modal.find('.ckeditor_ems').each(function () {
                if (CKEDITOR.instances.hasOwnProperty($(this).attr('id'))) {
                    CKEDITOR.instances[$(this).attr('id')].destroy();
                }
            });
            $modal.find('.ajax-content').html('').hide();
            $modal.find('.btn-json-menu-nested-save').hide();
        }
        function modalStateSaving($modal) {
            $modal.find('.modal-loading').show();
            $modal.find('form :input').prop('disabled', true);
            $modal.find('.btn-json-menu-nested-save').prop('disabled', true);
        }

        $(document).on('hide.bs.modal', '.json-menu-nested-modal', function (event) {
            if (event.target.id === `json-menu-nested-modal-${self.name}`) {
                modalStateloading($(this));
            }
        });
        $(document).on('show.bs.modal', '.json-menu-nested-modal', function (event) {
            if (event.target.id !== `json-menu-nested-modal-${self.name}`) {
                return;
            }

            let $target =  $(event.relatedTarget);
            self.$nestedModal.data('target', $target);

            let action = $target.data('action'); //add or edit
            let node = action === 'edit' ? $target.closest('li').data('node') : $target.data('node');

            let nodeIcon = node.icon ? `<i class="${node.icon}"></i>` : '';
            let prefixTitle = action === 'edit' ? 'Edit: ' : 'Add: ';
            $(this).find('.modal-title').html(`${nodeIcon} <span>${prefixTitle} ${node.label}</span>`);

            let data = {};
            if ('edit' === action) {
                data = $target.closest('li').data('object');
                data.label = $target.closest('li').data('label');
            }
            self.ajaxNestedModal(node.url, JSON.stringify(data), 'application/json', function () {
                modalStateActive(self.$nestedModal);
            });
        });

        this.$nestedModal.on('click', '.btn-json-menu-nested-save', function () {
            let $target = self.$nestedModal.data('target');
            let action = $target.data('action');
            let node = action === 'edit' ? $target.closest('li').data('node') : $target.data('node');

            for (let i in CKEDITOR.instances) {
                if(CKEDITOR.instances.hasOwnProperty(i)) { CKEDITOR.instances[i].updateElement(); }
            }
            let form = self.$nestedModal.find(`form[name="json-menu-nested-form-${node.name}"]`);
            let formContent = form.serialize();

            modalStateSaving(self.$nestedModal);
            self.ajaxNestedModal(node.url, formContent, 'application/x-www-form-urlencoded', function(response) {
                if (!response.hasOwnProperty('object') || !response.hasOwnProperty('label')) {
                    modalStateActive(self.$nestedModal);
                    return;
                }

                if (action === 'add') {
                    self.addItem($target, 'prototype-nested', {
                        'icon': node.icon,
                        'label': response.label,
                        'type': node.name,
                        'object': response.object,
                        'node': node,
                        'buttons': response.buttons,
                    });
                } else if (action === 'edit') {
                    $target.closest('li').data('label', response.label).data('object', response.object);
                    $target.closest('li').find('.itemLabel:first').text(response.label);
                    self.relocate();
                }

                self.$nestedModal.modal('hide');
            });
        });
    }

    ajaxNestedModal(url, data, contentType, callback)
    {
        const self = this;

        let httpRequest = new XMLHttpRequest();
        httpRequest.open("POST", url, true);
        httpRequest.setRequestHeader('Content-Type', contentType);
        httpRequest.onreadystatechange = function() {
            if (httpRequest.readyState === XMLHttpRequest.DONE) {
                if (httpRequest.status === 200) {
                    let response = JSON.parse(httpRequest.responseText);
                    if (response.hasOwnProperty('html')) {
                        self.$nestedModal.find('.ajax-content').html(response.html);
                        self.$nestedModal.find(':input').each(function (){ $(this).addClass('ignore-ems-update'); });
                        editRevisionAddEventListeners(self.$nestedModal.find('form'));
                    }

                    if (typeof callback !== 'undefined') { callback(response); }
                } else {
                    console.error('There was a problem with the request.');
                }
            }
        };
        httpRequest.send(data);
    }
}
