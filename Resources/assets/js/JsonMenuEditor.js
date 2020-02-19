import jquery from 'jquery';
import EmsListeners from './EmsListeners';
require('./nestedSortable');

const uuidv4 = require('uuid/v4');

export default class JsonMenuEditor {
    constructor(target) {
        const self = this;
        this.parent = jquery(target);
        this.relocateInProgress = false;
        this.nestedSortable = this.parent.find('ol.json_menu_sortable').nestedSortable({
            handle: 'div.nestedSortable',
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
        jTarget.find('.json_menu_sortable_add_button')
            .on('click', function(event) {
                self.addElement(this, event);
            });
        jTarget.find('.json_menu_sortable_remove_button')
            .on('click', function(event) {
                self.removeElement(this, event);
            });
        jTarget.find('.json_menu_sortable_add_item_button')
            .on('click', function(event) {
                self.addItem(this, event, '.json_menu_sortable_main_add_item_button');
            });
        jTarget.find('.json_menu_sortable_add_node_button')
            .on('click', function(event) {
                self.addItem(this, event, '.json_menu_sortable_main_add_node_button');
            });
        jTarget.find('input.itemLabel')
            .on('input', function(event) {
                self.updateLabel(this, event);
            });
    }

    updateLabel(target, event) {
        jquery(target).closest('li').data('label', jquery(target).val());
        if (this.relocateInProgress === true) {
            return;
        }
        this.relocate();
    }

    removeElement(target, event) {
        event.preventDefault();
        jquery(target).closest('li').remove();
        this.relocate();
    }

    addItem(target, event, prototypeTarget) {
        event.preventDefault();

        const prototype = this.parent.find(prototypeTarget).data('prototype');
        const uuid = uuidv4();
        const html = prototype.replace(/%uuid%/g, uuid).replace(/%label%/g, '');


        if (jquery(target).closest('li').children('ol').length > 0) {
            jquery(target).closest('li').children('ol').append(html);
        }
        else {
            jquery(target).closest('li').append('<ol>'+html+'</ol>');
        }

        const element = jquery('#'+uuid);
        this.addListerners(element);
        new EmsListeners(element.get(0));
        this.relocate();
        this.setFocus(uuid);
    }

    addElement(target, event) {
        event.preventDefault();
        const prototype = jquery(target).data('prototype');
        const uuid = uuidv4();
        const html = prototype.replace(/%uuid%/g, uuid).replace(/%label%/g, '');
        this.parent.find('ol.json_menu_sortable').append(html);
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
        this.relocateInProgress = true;
        const hierarchy = this.nestedSortable.nestedSortable('toHierarchy', {startDepthCount: 0});
        this.parent.find('input').first().val(JSON.stringify(hierarchy)).trigger("change");
        this.relocateInProgress = false;
    }


}
