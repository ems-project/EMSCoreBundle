export default class ProgressBar {
    #id;
    #options;
    #divBar;
    #divProgress;
    #divStatus;

    #styles = ['progress-bar-success', 'progress-bar-info', 'progress-bar-warning', 'progress-bar-danger'];

    #defaultOptions = {
        value: 0,
        min: 0,
        max: 100,
        stripped: true,
        label: '',
        status: '',
        showPercentage: true
    }

    constructor (id, options) {
        this.#id = id;
        this.#options = Object.assign({}, this.#defaultOptions, options);
        this.#divBar = this._createDivBar();
        this.#divProgress = this._createDivProgress(this.#divBar);

        this.progress(this.#options.value);
    }

    element() {
        return this.#divProgress;
    }

    status(status) {
        this.#divStatus.textContent = status;

        return this;
    }

    style(style)
    {
        this.#styles.forEach((style) => this.#divBar.classList.remove(style));
        this.#divBar.classList.add('progress-bar-'+style);

        return this;
    }

    progress(value) {
        if (this.#options.showPercentage) {
            this.#divBar.textContent = String(value).includes('%') ? value : value + '%';
        }

        this.#divBar.style.width = String(value).includes('%') ? value : value + '%';

        return this;
    }

    _createDivProgress(divBar) {
        let divWrapper = document.createElement('div');
        divWrapper.classList.add('core-js-progress');

        let divProgress = document.createElement('div');
        divProgress.classList.add('progress');
        divProgress.appendChild(divBar)

        let label = document.createElement('label');
        label.textContent = this.#options.label;

        this.#divStatus = document.createElement('div');
        this.#divStatus.classList.add('status');
        this.#divStatus.textContent = this.#options.status;

        divWrapper.append(label, this.#divStatus, divProgress);

        return divWrapper;
    }

    _createDivBar()
    {
        let divBar = document.createElement('div');
        divBar.setAttribute('id', this.#id);
        divBar.setAttribute('role', 'progressbar');
        divBar.setAttribute('aria-valuenow', this.#options.value);
        divBar.setAttribute('aria-valuemin', this.#options.min);
        divBar.setAttribute('aria-valuemax', this.#options.max);

        divBar.classList.add('progress-bar');
        divBar.classList.add('active');
        if (this.#options.stripped) {
            divBar.classList.add('progress-bar-striped');
        }

        return divBar
    }
}