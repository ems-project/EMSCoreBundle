import ajaxModal from "./../helper/ajaxModal";
import {ajaxJsonPost} from "./../helper/ajax";
import collapse from "../helper/collapse";

require('./../nestedSortable');

const uuidv4 = require('uuid/v4');

export default class JsonMenuNested {
    copyName = 'json_menu_nested_copy';
    nodes = {};
    urls = {};
    itemActions = [];
    selectItemId = false;

    constructor(target) {
        const self = this;
        this.target = target;

        if (this.target.classList.contains('json-menu-sortable')) {
            this.nestedSortable = $(this.target).find('ol.json-menu-root').nestedSortable({
                handle: 'a.btn-json-menu-move',
                items: 'li.json-menu-sortable',
                isTree: true,
                expression: /.*/,
                toleranceElement: '> div',
                stop: function () {
                    self.relocate();
                },
                isAllowed: function(placeholder, parent, current) {
                    const item = $(current).data();
                    const parentData = parent ?  $(parent).data() : $(current).closest('div.core-json-menu').data();

                    if (parentData.hasOwnProperty('node') && parentData.node.hasOwnProperty('deny')) {
                        const deny = parentData.node.deny;
                        return deny.indexOf(item.node.name) === -1;
                    }

                    return true;
                }
            });
        }

        this.parseAttributes();
        this.eventListeners(this.target);

        window.addEventListener('focus', () => {
            this.refreshPasteButtons();
        });

        window.addEventListener('DOMContentLoaded', () => {
            if (this.selectItemId) {
                this.selectItem(this.selectItemId, true);
            }
            this.loading(false);
        });
    }

    parseAttributes() {
        if (this.target.hasAttribute('data-hidden-field-id')) {
            this.hiddenField = document.getElementById(this.target.dataset.hiddenFieldId);
        }

        this.nodes = this.target.hasAttribute('data-nodes') ? JSON.parse(this.target.dataset.nodes) : {};
        this.urls = this.target.hasAttribute('data-urls') ? JSON.parse(this.target.dataset.urls) : {};
        this.itemActions = this.target.hasAttribute('data-item-actions') ? JSON.parse(this.target.dataset.itemActions) : [];
        this.selectItemId = this.target.hasAttribute('data-select-item-id') ? this.target.dataset.selectItemId : false;
    }
    eventListeners(element) {
        this.relocate();
        this.buttonItemAdd(element);
        this.buttonItemEdit(element);
        this.buttonItemDelete(element);
        this.buttonItemPreview(element);
        this.buttonItemCopy(element);
        this.buttonItemCopyAll(element);
        this.buttonItemPaste(element);
        this.refreshPasteButtons();
    }
    relocate() {
        this.target.querySelectorAll('li.json-menu-nested-item').forEach((li) => {
            li.classList.remove('json-menu-nested-item-selected');
        });

        this.target.querySelectorAll('ol').forEach((ol) => {
            if (!ol.hasAttribute('data-list')) {
                ol.setAttribute('data-list', ol.parentElement.dataset.item);
            }
            if (!ol.classList.contains('json-menu-list')) {
                ol.classList.add('json-menu-list');
            }
            if (!ol.classList.contains('collapse') && !ol.classList.contains('json-menu-root')) {
                ol.classList.add('collapse');
            }
        });

        collapse();

        let structureJson = this.getStructureJson();
        if (structureJson && this.hasOwnProperty('hiddenField') && this.hiddenField !== null) {
            this.hiddenField.value = structureJson;
            $(this.hiddenField).trigger('input').trigger('change');
        }
    }
    getStructureJson() {
        if (!this.nestedSortable) {
            return null;
        }

        const recursiveMapHierarchy = (obj, results = []) => {
            const r = results;
            Object.keys(obj).forEach(key => {
                const value = obj[key];
                const result = value.item;
                if (value.hasOwnProperty('children')) {
                    result.children = recursiveMapHierarchy(value.children);
                }
                r.push(result);
            });
            return r;
        };

        const toHierarchy = this.nestedSortable.nestedSortable('toHierarchy', {startDepthCount: 0});

        return JSON.stringify(recursiveMapHierarchy(toHierarchy));
    }

    getElementItem(itemId) {
        return this.target.parentElement.querySelector(`[data-item-id="${itemId}"]`);
    }
    getElementItemList(itemId) {
        return this.target.querySelector(`[data-list="${itemId}"]`);
    }
    selectItem(itemId, scroll = false) {
        let item = this.getElementItem(itemId);
        if (null === item) {
            return;
        }

        this.target.querySelectorAll('li.json-menu-nested-item').forEach((li) => {
           li.classList.remove('json-menu-nested-item-selected');
        });
        item.classList.add('json-menu-nested-item-selected');

        let parentNode = item.parentNode;
        if (parentNode === null) {
            return;
        }

        while(parentNode) {
            if (parentNode.classList.contains('json-menu-root')) { break; }
            if (parentNode.classList.contains('json-menu-nested-item')) {
                let btnCollapse = parentNode.querySelector('.btn-collapse');
                if (btnCollapse) { btnCollapse.dispatchEvent(new CustomEvent('show')); }
            }

            parentNode = parentNode.parentNode;
        }

        if (scroll) {
            setTimeout(() => { item.scrollIntoView(); }, 1000);
        }
    }

    getCopy() {
        if (localStorage.hasOwnProperty(this.copyName)) {
            const loopJson = (json, callback, result = {}) => {
                for (const [key, value] of Object.entries(json)) {
                    if (key === 'children') {
                        result[key] = value.map(e => loopJson(e, callback));
                    } else {
                        result[key] = callback(key, value);
                    }
                }
                return result;
            }

            let json = JSON.parse(localStorage.getItem(this.copyName));

            return loopJson(json, (key, value) => key === 'id' && value !== 'root' ? uuidv4() : value);
        }

        return false;
    }
    setCopy(value) {
        localStorage.setItem(this.copyName, JSON.stringify(value));
        this.refreshPasteButtons();
    }

    buttonItemAdd(element) {
        element.querySelectorAll('.btn-json-menu-nested-add').forEach((btnAdd) => {
            btnAdd.onclick = (e) => {
                e.preventDefault();

                let itemId = btnAdd.dataset.itemId;
                let nodeId = btnAdd.dataset.nodeId;
                let level = btnAdd.dataset.level;

                if (!this.nodes.hasOwnProperty(nodeId)) {
                    return;
                }

                let node = this.nodes[nodeId];

                let callback = (json, request) => {
                    if (json.hasOwnProperty('success') && json.success === true) {
                        this.appendHtml(itemId, json.html);
                        this.selectItem(json.itemId);
                    }
                };

                let params = this.makeParams();
                params.append('level', level);

                ajaxModal.load({
                    url: node.urlAdd + '?' + params.toString(),
                    title: (node.icon ? `<i class="${node.icon}"></i> ` : '') + `Add: ${node.label}`,
                    size: 'lg'
                }, callback);
            }
        });
    }
    buttonItemEdit(element) {
        element.querySelectorAll('.btn-json-menu-nested-edit').forEach((btnEdit) => {
            btnEdit.onclick = (e) => {
                e.preventDefault();

                let itemId = btnEdit.dataset.itemId;
                let item = JSON.parse(this.getElementItem(itemId).dataset.item);
                let nodeId = btnEdit.dataset.nodeId;
                let level = btnEdit.dataset.level;

                if (!this.nodes.hasOwnProperty(nodeId)) {
                    return;
                }

                let node = this.nodes[nodeId];

                let callback = (json, request) => {
                    if (!json.hasOwnProperty('success') || json.success === false) {
                        return;
                    }

                    let ol = this.getElementItemList(itemId);
                    this.getElementItem(itemId).outerHTML = json.html;

                    if (ol) {
                        this.getElementItem(itemId).insertAdjacentHTML('beforeend', ol.outerHTML);
                    }

                    this.eventListeners(this.getElementItem(itemId).parentElement);
                };

                let params = this.makeParams();
                params.append('itemId', itemId);
                params.append('level', level);

                ajaxModal.load({
                    url: node.urlEdit  + `?` + params.toString(),
                    title: (node.icon ? `<i class="${node.icon}"></i> ` : '') + `Edit: ${node.label}`,
                    data: JSON.stringify(item.object),
                    size: 'lg'
                }, callback);
            }
        });
    }

    buttonItemDelete(element) {
        element.querySelectorAll('.btn-json-menu-delete').forEach((btnDelete) => {
            btnDelete.onclick = (e) => {
                e.preventDefault();
                let itemId = btnDelete.dataset.itemId;
                let li = this.getElementItem(itemId);
                li.parentNode.removeChild(li);
                this.relocate();
            }
        });
    }
    buttonItemCopyAll(element) {
        let btnCopyAll = element.querySelector('.btn-json-menu-nested-copy-all');
        if (null === btnCopyAll) {
            return;
        }

        btnCopyAll.onclick = (e) => {
            e.preventDefault();
            this.setCopy({
                id: 'root',
                label: 'root',
                type: 'root',
                children: JSON.parse(this.getStructureJson())
            });
        }
    }
    buttonItemCopy(element) {
        element.querySelectorAll('.btn-json-menu-nested-copy').forEach((btnCopy) => {
            let buttonLi = btnCopy.parentElement;

            btnCopy.onclick = (e) => {
                e.preventDefault();
                let itemId = btnCopy.dataset.itemId;
                let li = this.getElementItem(itemId);

                let liToObject = (li) =>  {
                    let item = JSON.parse(li.dataset.item);

                    let children = [];
                    let childList = this.getElementItemList(li.dataset.itemId);
                    if (childList) {
                        childList.querySelectorAll(':scope > li').forEach((childLi) => {
                            children.push(liToObject(childLi));
                        });
                    }

                    return {
                        id: uuidv4(),
                        label: item.label,
                        type: item.type,
                        object: item.object,
                        children: children,
                    };
                }

                let value = liToObject(li);
                this.setCopy(value);
            }
        });
    }
    buttonItemPaste(element) {
        element.querySelectorAll('.btn-json-menu-nested-paste').forEach((btnPaste) => {
            btnPaste.onclick = (e) => {
                e.preventDefault();

                let copied = this.getCopy(true);
                if (false === copied) {
                    return;
                }

                this.loading(true);

                let itemId = btnPaste.dataset.itemId;
                let li = this.getElementItem(itemId);

                let params = this.makeParams();

                ajaxJsonPost(this.urls.paste + '?' + params.toString(), JSON.stringify({'copied': copied}), (json) => {
                    this.appendHtml(itemId, json.html);
                    this.loading(false);
                });
            }
        });
    }
    buttonItemPreview(element) {
        element.querySelectorAll('.btn-json-menu-nested-preview').forEach((btnPreview) => {
            btnPreview.onclick = (e) => {
                e.preventDefault();
                let itemId = btnPreview.dataset.itemId;
                let li = this.getElementItem(itemId);
                let item = JSON.parse(li.dataset.item);

                ajaxModal.load({
                    url: this.urls.preview,
                    title: btnPreview.dataset.title,
                    size: 'lg',
                    data: JSON.stringify({ type: item.type, object: item.object})
                });
            }
        });
    }

    loading(flag) {
        let loading = this.target.querySelector('.json-menu-nested-loading');
        loading.style.display = (flag ? 'flex' : 'none');
    }
    makeParams() {
        let params = new URLSearchParams();
        this.itemActions.forEach((itemAction) => { params.append('a[]', itemAction); });
        return params;
    }

    appendHtml(itemId, html) {
        let ol = this.getElementItemList(itemId);
        if (ol) {
            ol.insertAdjacentHTML('beforeend', html);
        } else {
            let itemList = `<ol data-list="${itemId}" class="collapse">${html}</ol>`;
            this.getElementItem(itemId).insertAdjacentHTML('beforeend', itemList );
        }

        this.eventListeners(this.getElementItem(itemId).parentElement);
    }

    refreshPasteButtons() {
        let copy = this.getCopy();

        document.querySelectorAll('.btn-json-menu-nested-paste').forEach((btnPaste) => {
            let buttonLi = btnPaste.parentElement;
            if (null === copy) {
                buttonLi.style.display = 'none';
                return;
            }

            let nodeId = btnPaste.dataset.nodeId;
            let node = this.nodes[nodeId];

            let copyType = copy.type;
            let deny = node.hasOwnProperty('deny') ? node.deny : [];

            if (deny.includes(copyType)) {
                buttonLi.style.display = 'none';
            } else {
                buttonLi.style.display = 'list-item';
            }
        });
    }
}