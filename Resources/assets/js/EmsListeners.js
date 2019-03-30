
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
    }

    addCheckBoxListeners() {
        jquery(this.target).iCheck({
            checkboxClass: 'icheckbox_square-blue',
            radioClass: 'iradio_square-blue',
            increaseArea: '20%'
        });
    }
}