import jquery from 'jquery';
import EmsListeners from './EmsListeners';
require('./nestedSortable');

const uuidv4 = require('uuid/v4');

export default class JsonMenuEditor {
    constructor(target) {
        const self = this;
        this.parent = jquery(target);
        this.hiddenField = this.parent.find('input').first();


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

        this.addListerners(target);
    }

    addListerners(target) {
        const jTarget = jquery(target);
        const self = this;
        jTarget.find('.json_menu_sortable_remove_button')
            .on('click', function(event) {
                self.removeElement(this, event);
            });
        jTarget.find('.json_menu_sortable_add_item_button')
            .on('click', function(event) {
                self.addItem(this, event, 'prototype-item');
            });
        jTarget.find('.json_menu_sortable_add_node_button')
            .on('click', function(event) {
                self.addItem(this, event, 'prototype-node');
            });
        jTarget.find('input.itemLabel')
            .on('input', function(event) {
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

    addItem(target, event, prototypeTarget) {
        event.preventDefault();
        let prototype = this.parent.find('.json_menu_editor_fieldtype_widget').data(prototypeTarget);

        const uuid = uuidv4();
        const html = prototype.replace(/%uuid%/g, uuid).replace(/%label%/g, '').replace(/%icon%/g, jquery(target).data('icon')).replace(/%content-type%/g, jquery(target).data('content-type'));

        let list = jquery(target).closest('.input-group').closest('li');
        if (list.length == 0) {
            list = this.parent.find('.json_menu_editor_fieldtype_widget');
        }

        if (list.children('ol').length > 0) {
            list.children('ol').append(html);
        }
        else {
            list.append('<ol>'+html+'</ol>');
        }

        const element = jquery('#'+uuid);
        this.addListerners(element);
        new EmsListeners(element.get(0));
        this.relocate();
        this.setFocus(uuid);
    }

    setFocus(uuid) {
        jquery('#'+uuid).find('input').focus();
    }

    relocate() {
        const hierarchy = this.nestedSortable.nestedSortable('toHierarchy', {startDepthCount: 0});
        this.hiddenField.val(JSON.stringify(hierarchy)).trigger("input").trigger("change");
    }


}
