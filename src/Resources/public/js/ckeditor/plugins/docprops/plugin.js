CKEDITOR.plugins.add("docprops",{requires:"wysiwygarea,dialog,colordialog",lang:"af,ar,az,bg,bn,bs,ca,cs,cy,da,de,de-ch,el,en,en-au,en-ca,en-gb,eo,es,es-mx,et,eu,fa,fi,fo,fr,fr-ca,gl,gu,he,hi,hr,hu,id,is,it,ja,ka,km,ko,ku,lt,lv,mk,mn,ms,nb,nl,no,oc,pl,pt,pt-br,ro,ru,si,sk,sl,sq,sr,sr-latn,sv,th,tr,tt,ug,uk,vi,zh,zh-cn",icons:"docprops,docprops-rtl",hidpi:!0,init:function(o){var a=new CKEDITOR.dialogCommand("docProps");a.modes={wysiwyg:o.config.fullPage},a.allowedContent={body:{styles:"*",attributes:"dir"},html:{attributes:"lang,xml:lang"}},a.requiredContent="body",o.addCommand("docProps",a),CKEDITOR.dialog.add("docProps",this.path+"dialogs/docprops.js"),o.ui.addButton&&o.ui.addButton("DocProps",{label:o.lang.docprops.label,command:"docProps",toolbar:"document,30"})}});