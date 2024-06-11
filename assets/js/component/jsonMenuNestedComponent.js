import Sortable from "sortablejs";
import ajaxModal from "../helper/ajaxModal";

export default class JsonMenuNestedComponent {
    id;
    #tree;
    element;
    #pathPrefix;
    #loadParentIds = [];
    #sortableLists = {};
    modalSize = 'md';

    constructor (element) {
        this.id = element.id;
        this.element = element;
        this.#tree = element.querySelector('.jmn-tree');
        this.#pathPrefix = `/component/json-menu-nested/${element.dataset.hash}`;
        this._addClickListeners();
        this._addClickLongPressListeners();
        this.load({
            activeItemId: ('activeItemId' in element.dataset ? element.dataset.activeItemId : null)
        });
    }

    load({ activeItemId = null, loadChildrenId: loadChildrenId = null} = {}) {
        this._post('/render', {
            active_item_id: activeItemId,
            load_parent_ids: this.#loadParentIds,
            load_children_id: loadChildrenId
        }).then((json) => {
            if (!json.hasOwnProperty('tree') || !json.hasOwnProperty('load_parent_ids')) return;

            this.#loadParentIds = json.load_parent_ids;
            this.#tree.innerHTML = json.tree;

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
    itemDelete(nodeId) {
        this._post(`/item/${nodeId}/delete`).then((json) => {
            let eventCanceled = this._dispatchEvent('jmn-delete', { data: json, nodeId: nodeId  });
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

        return types.includes(dragged.dataset.type);
    }
    _onMoveEnd(event) {
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
