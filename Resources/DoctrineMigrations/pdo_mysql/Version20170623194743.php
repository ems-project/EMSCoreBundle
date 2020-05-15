<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170623194743 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('UPDATE wysiwyg_profile SET config = \'{
"plugins": "adv_link,uploadimage,imagebrowser,a11yhelp,basicstyles,bidi,blockquote,clipboard,colorbutton,colordialog,contextmenu,dialogadvtab,div,elementspath,enterkey,entities,filebrowser,find,floatingspace,format,horizontalrule,htmlwriter,image2,indentlist,indentblock,justify,language,list,liststyle,magicline,maximize,newpage,pastefromword,pastetext,preview,removeformat,resize,save,scayt,selectall,showborders,sourcearea,specialchar,stylescombo,tab,table,tabletools,templates,toolbar,undo,wsc,wysiwygarea",
"pasteFromWordRemoveFontStyles": true,
"pasteFromWordRemoveStyles": true,
"language": "en",
"toolbarGroups": [{
"name": "clipboard",
"groups": ["clipboard", "undo"]
}, {
"name": "clipboard",
"groups": ["clipboard", "undo"]
}, {
"name": "editing",
"groups": ["spellchecker"]
}, {
"name": "links"
}, {
"name": "insert"
}, {
"name": "tools",
"groups": ["mode"]
}, "/", {
"name": "basicstyles",
"groups": ["basicstyles", "cleanup"]
}, {
"name": "paragraph",
"groups": ["list", "indent", "blocks", "align"]
}, {
"name": "styles"
}]
}\' where name = \'Standard\'');
        $this->addSql('UPDATE wysiwyg_profile SET config = \'{
"pasteFromWordRemoveFontStyles": true,
"pasteFromWordRemoveStyles": true,
"language": "en",
"toolbar": [
["Styles", "Format", "Font", "FontSize"],
["Bold", "Italic"],
["NumberedList", "BulletedList", "-", "JustifyLeft", "JustifyCenter", "JustifyRight", "JustifyBlock"],
["Image", "Table", "-", "Link"]
],
"removePlugins": "link,about,image",
"extraPlugins": "adv_link,adv_link,uploadimage,imagebrowser,image2"
}\' where name = \'Light\'');
        $this->addSql('UPDATE wysiwyg_profile SET config = \'{
"plugins": "adv_link,uploadimage,imagebrowser,a11yhelp,basicstyles,bidi,blockquote,clipboard,colorbutton,colordialog,contextmenu,dialogadvtab,div,elementspath,enterkey,entities,filebrowser,find,floatingspace,format,horizontalrule,htmlwriter,image2,indentlist,indentblock,justify,language,list,liststyle,magicline,maximize,newpage,pastefromword,pastetext,preview,removeformat,resize,save,scayt,selectall,showborders,sourcearea,specialchar,stylescombo,tab,table,tabletools,templates,toolbar,undo,wsc,wysiwygarea,font,showblocks,smiley,iframe,pagebreak,print",
"pasteFromWordRemoveFontStyles": true,
"pasteFromWordRemoveStyles": true,
"language": "en",
"toolbarGroups": [{
"name": "document",
"groups": ["mode", "document", "doctools"]
}, {
"name": "clipboard",
"groups": ["clipboard", "undo"]
}, {
"name": "editing",
"groups": ["find", "spellchecker"]
}, "/", {
"name": "basicstyles",
"groups": ["basicstyles", "cleanup"]
}, {
"name": "paragraph",
"groups": ["list", "indent", "blocks", "align", "bidi"]
}, {
"name": "links"
}, {
"name": "insert"
}, "/", {
"name": "styles"
}, {
"name": "colors"
}, {
"name": "tools"
}, {
"name": "others"
}, {
"name": "about"
}]
}\' where name = \'Full\'');
        $this->addSql('UPDATE wysiwyg_profile SET config = \'{
"uiColor": "#66AB16",
"plugins": "adv_link,uploadimage,imagebrowser,a11yhelp,basicstyles,bidi,blockquote,clipboard,colorbutton,colordialog,contextmenu,dialogadvtab,div,elementspath,enterkey,entities,filebrowser,find,flash,floatingspace,font,format,horizontalrule,htmlwriter,image2,iframe,indentlist,indentblock,justify,language,list,liststyle,magicline,maximize,newpage,pagebreak,pastefromword,pastetext,preview,print,removeformat,resize,save,scayt,selectall,showblocks,showborders,smiley,sourcearea,specialchar,stylescombo,tab,table,tabletools,templates,toolbar,undo,wsc,wysiwygarea",
"pasteFromWordRemoveFontStyles": true,
"pasteFromWordRemoveStyles": true,
"language": "en",
"toolbarGroups": [{
"name": "document",
"groups": ["mode", "document", "doctools"]
}, {
"name": "clipboard",
"groups": ["clipboard", "undo"]
}, {
"name": "editing",
"groups": ["find", "spellchecker"]
}, {
"name": "forms"
},
"/", {
"name": "basicstyles",
"groups": ["basicstyles", "cleanup"]
}, {
"name": "paragraph",
"groups": ["list", "indent", "blocks", "align", "bidi"]
}, {
"name": "links"
}, {
"name": "insert"
},
"/", {
"name": "styles"
}, {
"name": "colors"
}, {
"name": "tools"
}, {
"name": "others"
}, {
"name": "about"
}
]
}\' where name = \'Sample\'');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        
        $this->addSql('UPDATE wysiwyg_profile SET config = \'{
"plugins": "adv_link,uploadimage,imagebrowser,a11yhelp,basicstyles,bidi,blockquote,clipboard,colorbutton,colordialog,contextmenu,dialogadvtab,div,elementspath,enterkey,entities,filebrowser,find,floatingspace,format,horizontalrule,htmlwriter,image2,indentlist,indentblock,justify,language,list,liststyle,magicline,maximize,newpage,pastefromword,pastetext,preview,removeformat,resize,save,scayt,selectall,showborders,sourcearea,specialchar,stylescombo,tab,table,tabletools,templates,toolbar,undo,wsc,wysiwygarea",
"pasteFromWordRemoveFontStyles": true,
"pasteFromWordRemoveStyles": true,
"language": "en",
"toolbarGroups": [{
"name": "clipboard",
"groups": ["clipboard", "undo"]
}, {
"name": "clipboard",
"groups": ["clipboard", "undo"]
}, {
"name": "editing",
"groups": ["spellchecker"]
}, {
"name": "links"
}, {
"name": "insert"
}, {
"name": "tools",
"groups": ["mode"]
}, "/", {
"name": "basicstyles",
"groups": ["basicstyles", "cleanup"]
}, {
"name": "paragraph",
"groups": ["list", "indent", "blocks", "align"]
}, {
"name": "styles"
}]
}\' where name = \'Standard\'');
        $this->addSql('UPDATE wysiwyg_profile SET config = \'{
"pasteFromWordRemoveFontStyles": true,
"pasteFromWordRemoveStyles": true,
"language": "en",
"toolbar": [
["Styles", "Format", "Font", "FontSize"],
["Bold", "Italic"],
["NumberedList", "BulletedList", "-", "JustifyLeft", "JustifyCenter", "JustifyRight", "JustifyBlock"],
["Image", "Table", "-", "Link"]
],
"removePlugins": "link,about,image",
"extraPlugins": "adv_link,uploadimage,imagebrowser,image2"
}\' where name = \'Light\'');
        $this->addSql('UPDATE wysiwyg_profile SET config = \'{
"plugins": "adv_link,uploadimage,imagebrowser,a11yhelp,basicstyles,bidi,blockquote,clipboard,colorbutton,colordialog,contextmenu,dialogadvtab,div,elementspath,enterkey,entities,filebrowser,find,floatingspace,format,horizontalrule,htmlwriter,image2,indentlist,indentblock,justify,language,list,liststyle,magicline,maximize,newpage,pastefromword,pastetext,preview,removeformat,resize,save,scayt,selectall,showborders,sourcearea,specialchar,stylescombo,tab,table,tabletools,templates,toolbar,undo,wsc,wysiwygarea,font,showblocks,smiley,iframe,pagebreak,print",
"pasteFromWordRemoveFontStyles": true,
"pasteFromWordRemoveStyles": true,
"language": "en",
"toolbarGroups": [{
"name": "document",
"groups": ["mode", "document", "doctools"]
}, {
"name": "clipboard",
"groups": ["clipboard", "undo"]
}, {
"name": "editing",
"groups": ["find", "spellchecker"]
}, "/", {
"name": "basicstyles",
"groups": ["basicstyles", "cleanup"]
}, {
"name": "paragraph",
"groups": ["list", "indent", "blocks", "align", "bidi"]
}, {
"name": "links"
}, {
"name": "insert"
}, "/", {
"name": "styles"
}, {
"name": "colors"
}, {
"name": "tools"
}, {
"name": "others"
}, {
"name": "about"
}]
}\' where name = \'Full\'');
        $this->addSql('UPDATE wysiwyg_profile SET config = \'{
"uiColor": "#66AB16",
"plugins": "adv_link,uploadimage,imagebrowser,a11yhelp,basicstyles,bidi,blockquote,clipboard,colorbutton,colordialog,contextmenu,dialogadvtab,div,elementspath,enterkey,entities,filebrowser,find,flash,floatingspace,font,format,horizontalrule,htmlwriter,image2,iframe,indentlist,indentblock,justify,language,list,liststyle,magicline,maximize,newpage,pagebreak,pastefromword,pastetext,preview,print,removeformat,resize,save,scayt,selectall,showblocks,showborders,smiley,sourcearea,specialchar,stylescombo,tab,table,tabletools,templates,toolbar,undo,wsc,wysiwygarea",
"pasteFromWordRemoveFontStyles": true,
"pasteFromWordRemoveStyles": true,
"language": "en",
"toolbarGroups": [{
"name": "document",
"groups": ["mode", "document", "doctools"]
}, {
"name": "clipboard",
"groups": ["clipboard", "undo"]
}, {
"name": "editing",
"groups": ["find", "spellchecker"]
}, {
"name": "forms"
},
"/", {
"name": "basicstyles",
"groups": ["basicstyles", "cleanup"]
}, {
"name": "paragraph",
"groups": ["list", "indent", "blocks", "align", "bidi"]
}, {
"name": "links"
}, {
"name": "insert"
},
"/", {
"name": "styles"
}, {
"name": "colors"
}, {
"name": "tools"
}, {
"name": "others"
}, {
"name": "about"
}
]
}\' where name = \'Sample\'');
    }
}
