import Sortable from "sortablejs";
import ajaxModal from "../helper/ajaxModal";

export default class JsonMenuNestedComponent {
    id;
    #tree;
    #top;
    #header;
    #footer;
    element;
    #pathPrefix;
    #loadParentIds = [];
    #sortableLists = {};
    modalSize = 'md';
    #dragBlocked = false;

    constructor (element) {
        this.id = element.id;
        this.element = element;
        this.#tree = element.querySelector('.jmn-tree');
        this.#top = element.querySelector('.jmn-top');
        this.#header = element.querySelector('.jmn-header');
        this.#footer = element.querySelector('.jmn-footer');
        this.#pathPrefix = `/component/json-menu-nested/${element.dataset.hash}`;
        this._addClickListeners();
        this._addClickLongPressListeners();
        this.load({
            activeItemId: ('activeItemId' in element.dataset ? element.dataset.activeItemId : null)
        });
    }

    load({
        activeItemId = null,
        copyItemId = null,
        loadChildrenId = null
    } = {}) {
        this._post('/render', {
            active_item_id: activeItemId,
            copy_item_id: copyItemId,
            load_parent_ids: this.#loadParentIds,
            load_children_id: loadChildrenId
        }).then((json) => {
            const { tree, loadParentIds, top, header, footer } = json;

            if (top) this.#top.innerHTML = top;
            if (header) this.#header.innerHTML = header;
            if (footer) this.#footer.innerHTML = footer;

            if (!tree || !loadParentIds) return;
            this.#loadParentIds = loadParentIds;
            this.#tree.innerHTML = tree;

            let eventCanceled = this._dispatchEvent('jmn-load', { data: json, elements: this._sortables() });
            if (!eventCanceled) this.loading(false);
        });
    }
    itemGet(itemId) {
        return this._get(`/item/${itemId}`);
    }
    itemAdd(itemId, add, position = null) {
        return this._post(`/item/${itemId}/add`, { 'position': position, 'add': add });
    }
    itemDelete(itemId) {
        this._post(`/item/${itemId}/delete`).then((json) => {
            let eventCanceled = this._dispatchEvent('jmn-delete', { data: json, itemId: itemId  });
            if (!eventCanceled) this.load();
        });
    }
    loading(flag) {
        const element = this.element.querySelector('.jmn-node-loading');
        element.style.display = flag ? 'flex' : 'none';
    }

    _addClickListeners() {
        this.element.addEventListener('click', (event) => {
            const element = event.target;
            const node = element.parentElement.closest('.jmn-node');
            const itemId = node ? node.dataset.id : '_root';

            if (element.classList.contains('jmn-btn-add')) this._onClickButtonAdd(element, itemId);
            if (element.classList.contains('jmn-btn-edit')) this._onClickButtonEdit(element, itemId);
            if (element.classList.contains('jmn-btn-view')) this._onClickButtonView(element, itemId);
            if (element.classList.contains('jmn-btn-delete')) this._onClickButtonDelete(itemId);
            if (element.classList.contains('jmn-btn-copy')) this._onClickButtonCopy(itemId);
            if (element.classList.contains('jmn-btn-paste')) this._onClickButtonPaste(itemId);

            if (element.dataset.hasOwnProperty('jmnModalCustom')) this._onClickModalCustom(element, itemId);
        }, true);
    }
    _onClickButtonAdd(element, itemId) {
        this._ajaxModal(element, `/item/${itemId}/modal-add/${element.dataset.add}`, 'jmn-add');
    }
    _onClickButtonEdit(element, itemId) {
        this._ajaxModal(element, `/item/${itemId}/modal-edit`, 'jmn-edit');
    }
    _onClickButtonView(element, itemId) {
        this._ajaxModal(element, `/item/${itemId}/modal-view`, 'jmn-view');
    }
    _onClickButtonDelete(itemId) {
        this.itemDelete(itemId);
    }
    _onClickButtonCopy(itemId) {
        this._post(`/item/${itemId}/copy`).then((json) => {
            const {success, copyId} = json
            if (!success) return
            document.dispatchEvent(new CustomEvent('jmn.copy', {
                cancelable: true,
                detail: { copyId: copyId, originalId: itemId }
            }))
        })
    }
    _onClickButtonPaste(itemId) {
        this._post(`/item/${itemId}/paste`).then((json) => {
            const { success, pasteId } = json
            if (!success) return

            this.load({ activeItemId: json.pasteId })
        })
    }

    onCopy({ originalId } = event) {
        this.load({ activeItemId: originalId });
    }
    _onClickModalCustom(element, itemId) {
        const modalCustomName = element.dataset.jmnModalCustom;
        this._ajaxModal(element, `/item/${itemId}/modal-custom/${modalCustomName}`, 'jmn-modal-custom');
    }
    _onClickButtonCollapse(button, longPressed = false) {
        const expanded = button.getAttribute('aria-expanded');
        const node = event.target.parentElement.closest('.jmn-node');
        const nodeId = node.dataset.id;

        if ('true' === expanded) {
            button.setAttribute('aria-expanded', 'false');

            const childNodes = node.querySelectorAll(`.jmn-node`);
            const childIds = Array.from(childNodes).map((child) => child.dataset.id);
            childNodes.forEach((child) => child.remove());

            this.#loadParentIds = this.#loadParentIds.filter((id) => id !== nodeId && !childIds.includes(id));
            this.load();
        } else {
            button.setAttribute('aria-expanded', 'true');
            this.#loadParentIds.push(nodeId);
            this.load({ loadChildrenId: (longPressed ? nodeId : null) });
        }
    }
    _addClickLongPressListeners() {
        let delay;
        let longPressed = false;
        let longPressTime = 300;

        this.element.addEventListener('mousedown', (event) => {
            if (event.target.classList.contains('jmn-btn-collapse')) {
                delay = setTimeout(() => { longPressed = true}, longPressTime);
            }
        }, true);
        this.element.addEventListener('mouseup', (event) => {
            if (event.target.classList.contains('jmn-btn-collapse')) {
                this._onClickButtonCollapse(event.target, longPressed);
                clearTimeout(delay);
                longPressed = false;
            }
        });
    }
    _sortables() {
        const options = {
            group: 'shared',
            draggable: '.jmn-node',
            handle: '.jmn-btn-move',
            dragoverBubble: true,
            ghostClass: "jmn-move-ghost",
            chosenClass: "jmn-move-chosen",
            dragClass: "jmn-move-drag",
            animation: 10,
            fallbackOnBody: true,
            swapThreshold: 0.50,
            onMove: (event) => { return this._onMove(event) },
            onEnd: (event) => { return this._onMoveEnd(event) },
        }

        const sortables =  this.element.querySelectorAll('.jmn-sortable');
        sortables.forEach((element) => {
            this.#sortableLists[element.id] = Sortable.create(element, options);
        });
        return sortables;
    }
    _onMove(event) {
        const dragged = event.dragged;
        const targetList = event.to;

        if (!dragged.dataset.hasOwnProperty('type')
            || !targetList.dataset.hasOwnProperty('types')) return false;

        const types = JSON.parse(targetList.dataset.types);
        const allowedMove = types.includes(dragged.dataset.type);

        let eventCanceled = this._dispatchEvent('jmn-move', {
            dragged: dragged,
            from: event.from,
            to: event.to,
            allowed: allowedMove
        });

        if (eventCanceled) {
            this.#dragBlocked = true;
            return false;
        }

        return allowedMove;
    }
    _onMoveEnd(event) {
        if (this.#dragBlocked) {
            this.#dragBlocked = false;
            return;
        }

        const itemId = event.item.dataset.id;
        const targetComponent =  window.jsonMenuNestedComponents[event.to.closest('.json-menu-nested-component').id];
        const fromComponent =  window.jsonMenuNestedComponents[event.from.closest('.json-menu-nested-component').id];

        const position = event.newIndex;
        const toParentId = event.to.closest('.jmn-node').dataset.id;
        const fromParentId = event.from.closest('.jmn-node').dataset.id;

        if (targetComponent.id === fromComponent.id) {
            this._post(`/item/${itemId}/move`, {
                fromParentId: fromParentId,
                toParentId: toParentId,
                position: position
            }).finally(() => targetComponent.load({ activeItemId: itemId }));
        } else {
            fromComponent.itemGet(itemId)
                .then((json) => {
                    if (!json.hasOwnProperty('item')) throw new Error(JSON.stringify(json));
                    return targetComponent.itemAdd(toParentId, json.item, position)
                })
                .then((response) => {
                    if (!response.hasOwnProperty('success') || !response.success) throw new Error(JSON.stringify(response));
                    return fromComponent.itemDelete(itemId);
                })
                .catch(() => {})
                .finally(() => {
                    targetComponent.load({ activeItemId: itemId });
                    fromComponent.load();
                });
        }
    }
    _ajaxModal(element, path, eventType) {
        let activeItemId = null;
        const modalSize = element.dataset.modalSize ?? this.modalSize;

        let handlerClose = () => {
            this.load({ activeItemId: activeItemId });
            ajaxModal.modal.removeEventListener('ajax-modal-close', handlerClose);
        };

        ajaxModal.modal.addEventListener('ajax-modal-close', handlerClose);
        ajaxModal.load({ 'url': `${this.#pathPrefix}${path}`, 'size': modalSize }, (json) => {
            let eventCanceled = this._dispatchEvent(eventType, { data: json, ajaxModal: ajaxModal });
            if (eventCanceled) ajaxModal.modal.removeEventListener('ajax-modal-close', handlerClose);

            if (eventType === 'jmn-add' || eventType === 'jmn-edit') {
                if (!json.hasOwnProperty('success') || !json.success) return;
                if (json.hasOwnProperty('load')) this.#loadParentIds.push(json.load);
                if (json.hasOwnProperty('item') && json.item.hasOwnProperty('id')) activeItemId = json.item.id;

                ajaxModal.close();
            }
        });
    }
    _dispatchEvent(eventType, detail) {
        detail.jmn = this;
        return !this.element.dispatchEvent(new CustomEvent(eventType, {
            bubbles: true,
            cancelable: true,
            detail: detail,
        }));
    }
    async _get(path) {
        this.loading(true);
        const response = await fetch(`${this.#pathPrefix}${path}`, {
            method: "GET",
            headers: { 'Content-Type': 'application/json'},
        });
        return response.json();
    }
    async _post(path, data = {}) {
        this.loading(true);
        const response = await fetch(`${this.#pathPrefix}${path}`, {
            method: "POST",
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        return response.json();
    }
}
