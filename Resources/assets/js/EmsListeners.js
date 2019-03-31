
import jquery from 'jquery';
require('icheck');


export default class EmsListeners {

    constructor(target) {
        if(target === undefined) {
            console.log('Impossible to add ems listeners as no target is defined');
            return;
        }

        this.target = target;
        this.addListeners();
    }

    addListeners() {
       this.addCheckBoxListeners();
        this.addSelect2Listeners();
        this.addCollapsibleCollectionListeners();
    }

    addCheckBoxListeners() {
        jquery(this.target).iCheck({
            checkboxClass: 'icheckbox_square-blue',
            radioClass: 'iradio_square-blue',
            increaseArea: '20%'
        });
    }


    addSelect2Listeners() {
        //Initialize Select2 Elements
        jquery(this.target).find(".select2").select2({
            escapeMarkup: function (markup) { return markup; }
        });
    }

    addCollapsibleCollectionListeners() {
        jquery(this.target).find('.collapsible-collection')
            .on('click', '.button-collapse', function() {
                const $isExpanded = ($(this).attr('aria-expanded') === 'true');
                $(this).parent().find('button').attr('aria-expanded', !$isExpanded);

                const panel = $(this).closest('.panel');
                panel.find('.collapse').first().collapse('toggle');
            })
            .on('click', '.button-collapse-all', function() {
                const $isExpanded = ($(this).attr('aria-expanded') === 'true');
                $(this).parent().find('button').attr('aria-expanded', !$isExpanded);

                const panel = $(this).closest('.panel');
                panel.find('.button-collapse').attr('aria-expanded', !$isExpanded);
                panel.find('.button-collapse-all').attr('aria-expanded', !$isExpanded);

                if (!$isExpanded) {
                    panel.find('.collapse').collapse('show');
                } else {
                    panel.find('.collapse').collapse('hide');
                }
            });
    }

}