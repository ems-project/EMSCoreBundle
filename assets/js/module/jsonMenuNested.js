import ajaxModal from "./../helper/ajaxModal";
import {ajaxJsonPost} from "./../helper/ajax";
import collapse from "../helper/collapse";

require('./../nestedSortable');

const uuidv4 = require('uuid/v4');

export default class JsonMenuNested {
    copyName = 'json_menu_nested_copy';
    nodes = {};
    urls = {};
    selectItemId = false;
    config = null;

    getId() {
        return this.target.getAttribute('id');
    }
    getStructureJson(includeRoot = false) {
        const makeChildren = (element) => {
            let children = [];
            let childList = element.querySelector('ol.json-menu-nested-list');
            if (childList) {
                childList.querySelectorAll(':scope > li.json-menu-nested-item').forEach((li) => {
                    let childItem = JSON.parse(li.dataset.item);
                    childItem.children = makeChildren(li);
                    children.push(childItem);
                });
            }
            return children;
        }

        let children = makeChildren(this.target);

        if (!includeRoot) {
            return JSON.stringify(children);
        }

        let rootItem = JSON.parse(this.target.dataset.item);
        rootItem.children = makeChildren(this.target);

        return JSON.stringify(rootItem);
    }
    loading(flag) {
        let loading = this.target.querySelector('.json-menu-nested-loading');
        loading.style.display = (flag ? 'flex' : 'none');
    }
    selectItem(itemId, scroll = false) {
        let item = this._getElementItem(itemId);
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
            if (parentNode.classList.contains('json-menu-nested-root')) { break; }
            if (parentNode.classList.contains('json-menu-nested-item')) {
                let btnCollapse = parentNode.querySelector('.btn-collapse');
                if (btnCollapse) {
                    btnCollapse.dispatchEvent(new CustomEvent('show'));
                }
            }

            parentNode = parentNode.parentNode;
        }

        if (scroll) {
            setTimeout(() => { item.scrollIntoView(); }, 1000);
        }
    }

    constructor(target) {
        const self = this;
        this.target = target;

        this._parseAttributes();

        if (this.target.classList.contains('json-menu-nested-sortable')) {
            this.nestedSortable = $(this.target).find('ol.json-menu-nested-root').nestedSortable({
                handle: 'a.btn-json-menu-nested-move',
                items: 'li.json-menu-nested-sortable',
                isTree: true,
                expression: /.*/,
                toleranceElement: '> div',
                update: function () {
                    self._relocate();
                },
                isAllowed: function(placeholder, parent, current) {
                    let li = $(current).data();
                    let parentData = parent ?  $(parent).data() : $(self.target).data();

                    let draggingNode = self.nodes[li.nodeId];
                    let targetNode = self.nodes[parentData.nodeId];

                    return targetNode.addNodes.includes(draggingNode.name);
                }
            });
        }

        this._addEventListeners(this.target);
        window.addEventListener('focus', () => { this._refreshPasteButtons(); });
        this._initSilentPublish();

        if (this.selectItemId) {
            this.selectItem(this.selectItemId, true);
            this.loading(false);
        } else {
            this.loading(false);
        }
    }
    
    _parseAttributes() {
        if (this.target.hasAttribute('data-hidden-field-id')) {
            this.hiddenField = document.getElementById(this.target.dataset.hiddenFieldId);
        }

        this.config = this.target.dataset.config;
        this.nodes = JSON.parse(this.target.dataset.nodes);
        this.urls = JSON.parse(this.target.dataset.urls);
        this.selectItemId = this.target.hasAttribute('data-select-item-id') ? this.target.dataset.selectItemId : false;
    }
    _addEventListeners(element) {
        this._relocate();
        this._buttonItemAdd(element);
        this._buttonItemEdit(element);
        this._buttonItemDelete(element);
        this._buttonItemPreview(element);
        this._buttonItemCopy(element);
        this._buttonItemCopyAll(element);
        this._buttonItemPaste(element);
        this._refreshPasteButtons();
    }
    _relocate() {
        this.target.querySelectorAll('li.json-menu-nested-item').forEach((li) => {
            li.classList.remove('json-menu-nested-item-selected');
        });

        this.target.querySelectorAll('ol').forEach((ol) => {
            if (!ol.hasAttribute('data-list')) {
                ol.setAttribute('data-list', ol.parentElement.dataset.item);
            }
            if (!ol.classList.contains('json-menu-nested-list')) {
                ol.classList.add('json-menu-nested-list');
            }
            if (!ol.classList.contains('collapse') && !ol.classList.contains('json-menu-nested-root')) {
                ol.classList.add('collapse');
            }
        });

        collapse();

        if (this.hasOwnProperty('hiddenField') && this.hiddenField !== null) {
            if (this.hiddenField.classList.contains('json-menu-nested-silent-publish')) {
                this.hiddenField.value = this.getStructureJson(true);
                this.hiddenField.dispatchEvent(new CustomEvent('silentPublish'));
            } else {
                this.hiddenField.value = this.getStructureJson();
                $(this.hiddenField).trigger('input').trigger('change');
            }
        }
    }

    _getElementItem(itemId) {
        return this.target.parentElement.querySelector(`[data-item-id="${itemId}"]`);
    }
    _getElementItemList(itemId) {
        return this.target.querySelector(`[data-list="${itemId}"]`);
    }
    _getCopy() {
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
    _setCopy(value) {
        localStorage.setItem(this.copyName, JSON.stringify(value));
        this._refreshPasteButtons();
    }

    _buttonItemAdd(element) {
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
                let addItemId = uuidv4();

                const params = new URLSearchParams(window.location.search);

                ajaxModal.load({
                    url: node.urlAdd,
                    title: (node.icon ? `<i class="${node.icon}"></i> ` : '') + `Add: ${node.label}`,
                    data: JSON.stringify({ '_data': {
                        'level': level,
                        'item_id': addItemId,
                        'config': this.config,
                        'defaultData': params.get('defaultData')
                    } }),
                    size: 'lg'
                }, (json) => {
                    if (json.hasOwnProperty('success') && json.success === true) {
                        this._appendHtml(itemId, json.html);
                        this.selectItem(addItemId);
                    }
                });
            }
        });
    }
    _buttonItemEdit(element) {
        element.querySelectorAll('.btn-json-menu-nested-edit').forEach((btnEdit) => {
            btnEdit.onclick = (e) => {
                e.preventDefault();

                let itemId = btnEdit.dataset.itemId;
                let item = JSON.parse(this._getElementItem(itemId).dataset.item);
                let nodeId = btnEdit.dataset.nodeId;
                let level = btnEdit.dataset.level;

                if (!this.nodes.hasOwnProperty(nodeId)) {
                    return;
                }

                let node = this.nodes[nodeId];

                let callback = (json) => {
                    if (!json.hasOwnProperty('success') || json.success === false) {
                        return;
                    }

                    let ol = this._getElementItemList(itemId);
                    this._getElementItem(itemId).outerHTML = json.html;

                    if (ol) {
                        this._getElementItem(itemId).insertAdjacentHTML('beforeend', ol.outerHTML);
                    }

                    this._addEventListeners(this._getElementItem(itemId).parentNode);
                };

                ajaxModal.load({
                    url: node.urlEdit,
                    title: (node.icon ? `<i class="${node.icon}"></i> ` : '') + `Edit: ${node.label}`,
                    data: JSON.stringify({ '_data': {
                        'level': level,
                        'item_id': itemId,
                        'object' : item.object,
                        'config': this.config
                    }}),
                    size: 'lg'
                }, callback);
            }
        });
    }
    _buttonItemDelete(element) {
        element.querySelectorAll('.btn-json-menu-nested-delete').forEach((btnDelete) => {
            btnDelete.onclick = (e) => {
                e.preventDefault();
                let itemId = btnDelete.dataset.itemId;
                let li = this._getElementItem(itemId);
                li.parentNode.removeChild(li);
                this._relocate();
            }
        });
    }
    _buttonItemCopyAll(element) {
        let btnCopyAll = element.querySelector('.btn-json-menu-nested-copy-all');
        if (null === btnCopyAll) {
            return;
        }

        btnCopyAll.onclick = (e) => {
            e.preventDefault();
            this._setCopy({
                id: 'root',
                label: 'root',
                type: 'root',
                children: JSON.parse(this.getStructureJson())
            });
        }
    }
    _buttonItemCopy(element) {
        element.querySelectorAll('.btn-json-menu-nested-copy').forEach((btnCopy) => {
            let buttonLi = btnCopy.parentElement;

            btnCopy.onclick = (e) => {
                e.preventDefault();
                let itemId = btnCopy.dataset.itemId;
                let li = this._getElementItem(itemId);

                let liToObject = (li) =>  {
                    let item = JSON.parse(li.dataset.item);

                    let children = [];
                    let childList = this._getElementItemList(li.dataset.itemId);
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
                this._setCopy(value);
            }
        });
    }
    _buttonItemPaste(element) {
        element.querySelectorAll('.btn-json-menu-nested-paste').forEach((btnPaste) => {
            btnPaste.onclick = (e) => {
                e.preventDefault();

                let copied = this._getCopy(true);
                if (false === copied) {
                    return;
                }

                this.loading(true);

                let itemId = btnPaste.dataset.itemId;
                let li = this._getElementItem(itemId);
                let nodeId = btnPaste.dataset.nodeId;
                let node = this.nodes[nodeId];

                ajaxJsonPost(
                    this.urls.paste,
                    JSON.stringify({'_data': { 'copied': copied, 'config': this.config }}),
                    (json) => {
                        this._appendHtml(itemId, json.html);
                        this.loading(false);
                    }
                );
            }
        });
    }
    _buttonItemPreview(element) {
        element.querySelectorAll('.btn-json-menu-nested-preview').forEach((btnPreview) => {
            btnPreview.onclick = (e) => {
                e.preventDefault();
                let itemId = btnPreview.dataset.itemId;
                let li = this._getElementItem(itemId);
                let item = JSON.parse(li.dataset.item);

                ajaxModal.load({
                    url: this.urls.preview,
                    title: btnPreview.dataset.title,
                    size: 'lg',
                    data: JSON.stringify({ '_data': { 'type': item.type, 'object': item.object } })
                });
            }
        });
    }

    _appendHtml(itemId, html) {
        let ol = this._getElementItemList(itemId);
        if (ol) {
            ol.insertAdjacentHTML('beforeend', html);
        } else {
            let itemList = `<ol data-list="${itemId}" class="collapse">${html}</ol>`;
            this._getElementItem(itemId).insertAdjacentHTML('beforeend', itemList );
        }

        this._addEventListeners(this._getElementItem(itemId).parentElement);
    }
    _initSilentPublish() {
        if (null === this.hiddenField || !this.hiddenField.classList.contains('json-menu-nested-silent-publish')) {
            return;
        }

        this.hiddenField.addEventListener('silentPublish', (e) => {
            let value = this.hiddenField.value;
            this.loading(true);

            ajaxJsonPost(
                this.urls.silentPublish,
                JSON.stringify({'_data': { 'update': value, 'config': this.config }}),
                (json, response) => {
                    if (response.status === 200) {
                        if (json.hasOwnProperty('urls')) {
                            this.urls = json.urls
                            this.target.setAttribute('data-urls', JSON.stringify(this.urls));
                        };
                        if (json.hasOwnProperty('nodes')) {
                            this.nodes = json.nodes
                            this.target.setAttribute('data-nodes', JSON.stringify(this.nodes));
                        };
                        setTimeout(() => this.loading(false), 250);
                        return;
                    }

                    if (json.hasOwnProperty('alert')) {
                        document.getElementById(this.getId() + '-alerts').innerHTML = json.alert;
                    }
                });
        });
    }
    _refreshPasteButtons() {
        let copy = this._getCopy();

        document.querySelectorAll('.btn-json-menu-nested-paste').forEach((btnPaste) => {
            let buttonLi = btnPaste.parentElement;
            if (null === copy) {
                buttonLi.style.display = 'none';
                return;
            }

            let nodeId = btnPaste.dataset.nodeId;
            let node = this.nodes[nodeId];

            let copyType = copy.type;
            let allow = btnPaste.dataset.allow;

            if ((node !== undefined && node.addNodes.includes(copyType)) || allow === copyType) {
                buttonLi.style.display = 'list-item';
            } else {
                buttonLi.style.display = 'none';
            }
        });
    }
}