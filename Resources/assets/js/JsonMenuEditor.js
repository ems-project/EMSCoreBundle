import jquery from 'jquery';
import EmsListeners from './EmsListeners';
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
            }
        });

        this.addListeners(target);
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
            jTarget.find('.json_menu_nested_add_item_button').on('click', function() {
                self.nestedModal.data('target', $(this)).modal('show');
            });
            jTarget.find('.json_menu_nested_edit_item_button').on('click', function() {
                self.nestedModal.data('target', $(this)).modal('show');
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
            item.find('.json_menu_nested_edit_item_button').data('node', data.node);
        }

        let list = $target.closest('.input-group').closest('li');
        if (list.length === 0) {
            list = this.parent.find('.json_menu_editor_fieldtype_widget');
        }

        if (list.children('ol').length > 0) {
            list.children('ol').append(item);
        } else {
            list.append($("<ol></ol>").append(item));
        }

        const element = jquery('#'+uuid);
        this.addListeners(element);
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

    initNestedModal() {
        const self = this;
        let nestedModal = $('#json-menu-nested-modal-'+this.name);
        nestedModal.find('form, .btn-action').hide();
        nestedModal.find('input, select, textarea').each(function (){
            $(this).addClass('ignore-ems-update');
        });
        nestedModal
            .on('show.bs.modal', function () {
                let target =  $(this).data('target');
                let action = target.data('action'); //add or edit
                let node = target.data('node');
                let form = $(this).find(`form[name=${node.formName}]`);
                let nodeIcon = node.icon ? `<i class="${node.icon}"></i>` : '';

                if (action === 'add') {
                    form.find('.objectpicker').each(function (){ $(this).val('').trigger('change'); });
                    form.find('.ckeditor_ems').each(function (){ CKEDITOR.instances[$(this).attr('id')].setData(null); });
                } else if (action === 'edit') {
                    let object = target.closest('li').data('object');
                    let label = target.closest('li').data('label');

                    form.find(`[name='${node.formName}[label]']`).val(label);
                    self.setNestedFormData(form, object);
                }

                $(this).find(`.btn-${action}`).show();
                form.show();
                $(this).find('.modal-title').html(`${nodeIcon} <span>${action} ${node.label}</span>`);
            })
            .on('hide.bs.modal', function () {
                $(this).find('input[type="text"], textarea, select').val(''); //clear form data
                $(this).find('form, .btn-action').hide();
            });

        nestedModal.find('.btn-add').on('click', function (event) { save(event, 'add'); });
        nestedModal.find('.btn-edit').on('click', function (event) { save(event, 'edit'); });

        function save(event, action)
        {
            event.preventDefault();

            let target = nestedModal.data('target');
            let node = target.data('node');
            let form = nestedModal.find(`form[name=${node.formName}]`);
            let label = form.find(`[name='${node.formName}[label]']`).val();
            let object = self.getNestedFormData(form);

            if (action === 'add') {
                self.addItem(target, 'prototype-nested', {
                    'icon': node.icon,
                    'label': label,
                    'type': node.name,
                    'object': object,
                    'node': node
                });
            } else if (action === 'edit') {
                target.closest('li').data('label', label).data('object', object);
                target.closest('li').find('.itemLabel:first').val(label);
                self.relocate();
            }

            nestedModal.modal('hide');
        }

        this.nestedModal = nestedModal;
    }

    setNestedFormData(form, data)
    {
        function recursiveSetData(data, structure) {
            for (let s in structure) {
                if (typeof structure[s] === 'object' && data[s] !== null) {
                    recursiveSetData(data[s], structure[s]);
                } else {
                    let element = form.find(`[name='${structure[s]}']`);
                    let value = data && data.hasOwnProperty(s) ? data[s] : null;

                    if (element.hasClass('ckeditor_ems')) {
                        CKEDITOR.instances[element.attr('id')].setData(value);
                    } else if (element.hasClass('objectpicker')) {
                        element.val(value).trigger('change');
                    } else {
                        element.val(value);
                    }
                }
            }
        }

        let formStructure = $.extend(true,{},form.data('structure'));
        recursiveSetData(data, formStructure);
    }
    getNestedFormData(form) {
        function recursiveGetData(structure) {
            for (let k in structure) {
                if (typeof structure[k] === 'object' && structure[k] !== null) {
                    let o = recursiveGetData(structure[k]);

                    if (Object.keys(o).length === 0) {
                        delete structure[k];
                    }
                } else if (structure.hasOwnProperty(k)) {
                    let element = form.find(`[name='${structure[k]}']`);
                    let fieldValue = false;

                    if (element.hasClass('ckeditor_ems')) {
                        fieldValue = CKEDITOR.instances[element.attr('id')].getData();
                    } else {
                        fieldValue = element.val();
                    }

                    if (fieldValue) {
                        structure[k] = fieldValue;
                    } else {
                        delete structure[k];
                    }
                }
            }
            return structure;
        }

        let formStructure = $.extend(true,{},form.data('structure'));
        return recursiveGetData(formStructure);
    }
}
