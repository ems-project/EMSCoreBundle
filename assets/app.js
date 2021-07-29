'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';


import EmsListeners from './js/EmsListeners';
import RevisionTask from "./js/module/revisionTask";

window.EmsListeners = EmsListeners;

new EmsListeners(document);
new RevisionTask();

