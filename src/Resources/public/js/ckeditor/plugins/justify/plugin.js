!function(){function t(t,e){var s;if(e)s=t.getComputedStyle("text-align");else{for(;(!t.hasAttribute||!t.hasAttribute("align")&&!t.getStyle("text-align"))&&(s=t.getParent());)t=s;s=t.getStyle("text-align")||t.getAttribute("align")||""}return s&&(s=s.replace(/(?:-(?:moz|webkit)-)?(?:start|auto)/i,"")),!s&&e&&(s="rtl"==t.getComputedStyle("direction")?"right":"left"),s}function e(t,e,s){this.editor=t,this.name=e,this.value=s,this.context="p",e=t.config.justifyClasses;var a=t.config.enterMode==CKEDITOR.ENTER_P?"p":"div";if(e){switch(s){case"left":this.cssClassName=e[0];break;case"center":this.cssClassName=e[1];break;case"right":this.cssClassName=e[2];break;case"justify":this.cssClassName=e[3]}this.cssClassRegex=new RegExp("(?:^|\\s+)(?:"+e.join("|")+")(?=$|\\s)"),this.requiredContent=a+"("+this.cssClassName+")"}else this.requiredContent=a+"{text-align}";this.allowedContent={"caption div h1 h2 h3 h4 h5 h6 p pre td th li":{propertiesOnly:!0,styles:this.cssClassName?null:"text-align",classes:this.cssClassName||null}},t.config.enterMode==CKEDITOR.ENTER_BR&&(this.allowedContent.div=!0)}function s(t){var e=t.editor,s=e.createRange();s.setStartBefore(t.data.node),s.setEndAfter(t.data.node);for(var a,i=new CKEDITOR.dom.walker(s);a=i.next();)if(a.type==CKEDITOR.NODE_ELEMENT)if(!a.equals(t.data.node)&&a.getDirection())s.setStartAfter(a),i=new CKEDITOR.dom.walker(s);else{var l=e.config.justifyClasses;l&&(a.hasClass(l[0])?(a.removeClass(l[0]),a.addClass(l[2])):a.hasClass(l[2])&&(a.removeClass(l[2]),a.addClass(l[0]))),"left"==(l=a.getStyle("text-align"))?a.setStyle("text-align","right"):"right"==l&&a.setStyle("text-align","left")}}e.prototype={exec:function(e){var s=e.getSelection(),a=e.config.enterMode;if(s){for(var i,l,n=s.createBookmarks(),o=s.getRanges(),r=this.cssClassName,c=e.config.useComputedState,d=o.length-1;0<=d;d--)for((i=o[d].createIterator()).enlargeBr=a!=CKEDITOR.ENTER_BR;l=i.getNextParagraph(a==CKEDITOR.ENTER_P?"p":"div");)if(!l.isReadOnly()){var f,u=l.getName();if(f=e.activeFilter.check(u+"{text-align}"),(u=e.activeFilter.check(u+"("+r+")"))||f){l.removeAttribute("align"),l.removeStyle("text-align");var g=r&&(l.$.className=CKEDITOR.tools.ltrim(l.$.className.replace(this.cssClassRegex,""))),h=this.state==CKEDITOR.TRISTATE_OFF&&(!c||t(l,!0)!=this.value);r&&u?h?l.addClass(r):g||l.removeAttribute("class"):h&&f&&l.setStyle("text-align",this.value)}}e.focus(),e.forceNextSelectionCheck(),s.selectBookmarks(n)}},refresh:function(e,s){var a=s.block||s.blockLimit,i=a.getName(),l=a.equals(e.editable());i=this.cssClassName?e.activeFilter.check(i+"("+this.cssClassName+")"):e.activeFilter.check(i+"{text-align}");l&&!CKEDITOR.dtd.$list[s.lastElement.getName()]?this.setState(CKEDITOR.TRISTATE_OFF):!l&&i?this.setState(t(a,this.editor.config.useComputedState)==this.value?CKEDITOR.TRISTATE_ON:CKEDITOR.TRISTATE_OFF):this.setState(CKEDITOR.TRISTATE_DISABLED)}},CKEDITOR.plugins.add("justify",{icons:"justifyblock,justifycenter,justifyleft,justifyright",hidpi:!0,init:function(t){if(!t.blockless){var a=new e(t,"justifyleft","left"),i=new e(t,"justifycenter","center"),l=new e(t,"justifyright","right"),n=new e(t,"justifyblock","justify");t.addCommand("justifyleft",a),t.addCommand("justifycenter",i),t.addCommand("justifyright",l),t.addCommand("justifyblock",n),t.ui.addButton&&(t.ui.addButton("JustifyLeft",{label:t.lang.common.alignLeft,command:"justifyleft",toolbar:"align,10"}),t.ui.addButton("JustifyCenter",{label:t.lang.common.center,command:"justifycenter",toolbar:"align,20"}),t.ui.addButton("JustifyRight",{label:t.lang.common.alignRight,command:"justifyright",toolbar:"align,30"}),t.ui.addButton("JustifyBlock",{label:t.lang.common.justify,command:"justifyblock",toolbar:"align,40"})),t.on("dirChanged",s)}}})}();