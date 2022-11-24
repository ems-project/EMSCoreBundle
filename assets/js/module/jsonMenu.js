import jquery from 'jquery';
import EmsListeners from './../EmsListeners';
require('./../nestedSortable');

const uuidv4 = require('uuid/v4');

export default class JsonMenu {
    constructor(target) {
        const self = this;
        this.parent = $(target);
        this.hiddenField = this.parent.find('input').first();
        this.name = this.parent.data('name');
        this.blockPrefix = this.parent.data('block-prefix');

        this.nestedSortable = this.parent.find('ol.json_menu_sortable').nestedSortable({
            handle: 'a.json_menu_sortable_handle_button',
            items: 'li.nestedSortable',
            isTree: true,
            expression: /()(.+)/,
            toleranceElement: '> div',
            stop: function () {
                self.relocate();
            }
        });

        this.addListeners(target);
        this.updateCollapseButtons();
    }

    addListeners(target) {
        const jTarget = jquery(target);
        const self = this;

        jTarget.find('.json_menu_sortable_remove_button').on('click', function(event) {
            self.removeElement(this, event);
        });
        jTarget.find('.json_menu_sortable_add_item_button').on('click', function(event) {
            event.preventDefault();
            self.addItem($(this), 'prototype-item', $(this).data());
        });
        jTarget.find('.json_menu_sortable_add_node_button').on('click', function(event) {
            event.preventDefault();
            self.addItem($(this), 'prototype-node', $(this).data());
        });
        jTarget.find('.json_menu_sortable_paste_button').on('click', function(event) {
            event.preventDefault();
            self.paste($(this));
        });
        jTarget.find('input.itemLabel').on('input', function(event) {
            self.updateLabel(this, event);
        });
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
        const recursiveMapHierarchy = (obj, results = []) => {
           const r = results;
           Object.keys(obj).forEach(key => {
              const value = obj[key];
              const result = {'id': value.id, 'label': value.label, 'contentType': value.contentType, 'type': value.type, 'object': value.object};
              if (value.hasOwnProperty('children')) {
                result.children = recursiveMapHierarchy(value.children);
              }
              r.push(result);
           });
           return r;
        };

        this.updateCollapseButtons();
        const toHierarchy = this.nestedSortable.nestedSortable('toHierarchy', {startDepthCount: 0});
        const hierarchyValue = JSON.stringify(recursiveMapHierarchy(toHierarchy));

        this.hiddenField.val(hierarchyValue).trigger("input").trigger("change");
    }
}
