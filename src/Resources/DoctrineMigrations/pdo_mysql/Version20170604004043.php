<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20170604004043 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('CREATE TABLE wysiwyg_profile (id INT AUTO_INCREMENT NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, config LONGTEXT DEFAULT NULL, orderKey INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');

        $this->addSql('INSERT INTO wysiwyg_profile VALUES (NULL, \'2017-06-03 23:51:05\', \'2017-06-04 02:32:49\', \'Standard\', \'{
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
}\', 1)');
        $this->addSql('INSERT INTO wysiwyg_profile VALUES (NULL, \'2017-06-03 22:31:15\', \'2017-06-04 00:26:23\', \'Light\', \'{
"pasteFromWordRemoveFontStyles": true,
"pasteFromWordRemoveStyles": true,
"language": "en",
"toolbar": [
["Styles", "Format", "Font", "FontSize"],
["Bold", "Italic"],
["NumberedList", "BulletedList", "-", "JustifyLeft", "JustifyCenter", "JustifyRight", "JustifyBlock"],
["Image", "Table", "-", "Link"]
],
"removePlugins": "link,about",
"extraPlugins": "adv_link,"
}\', 2)');
        $this->addSql('INSERT INTO wysiwyg_profile VALUES (NULL, \'2017-06-03 23:07:24\', \'2017-06-04 00:26:42\', \'Full\', \'{
"plugins": "adv_link,uploadimage,imagebrowser,a11yhelp,basicstyles,bidi,blockquote,clipboard,colorbutton,colordialog,contextmenu,dialogadvtab,div,elementspath,enterkey,entities,filebrowser,find,floatingspace,font,format,horizontalrule,htmlwriter,image2,iframe,indentlist,indentblock,justify,language,list,liststyle,magicline,maximize,newpage,pagebreak,pastefromword,pastetext,preview,print,removeformat,resize,save,scayt,selectall,showblocks,showborders,smiley,sourcearea,specialchar,stylescombo,tab,table,tabletools,templates,toolbar,undo,wsc,wysiwygarea",
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
}\', 3)');
        $this->addSql('INSERT INTO wysiwyg_profile VALUES (NULL, \'2017-06-03 23:11:33\', \'2017-06-04 00:26:42\', \'Sample\', \'{
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
}\', 4)');

        $this->addSql('ALTER TABLE user ADD wysiwyg_profile_id INT DEFAULT NULL');

        $this->addSql('UPDATE user SET wysiwyg_profile_id = 1');
        $this->addSql('UPDATE user SET wysiwyg_profile_id = 3 where wysiwyg_profile = \'full\'');
        $this->addSql('UPDATE user SET wysiwyg_profile_id = 2 where wysiwyg_profile = \'light\'');
        $this->addSql('UPDATE user SET wysiwyg_profile_id = NULL where wysiwyg_profile = \'custom\'');

        $this->addSql('ALTER TABLE user DROP wysiwyg_profile');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649A282F7EA FOREIGN KEY (wysiwyg_profile_id) REFERENCES wysiwyg_profile (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649A282F7EA ON user (wysiwyg_profile_id)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );

        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649A282F7EA');
        $this->addSql('DROP TABLE wysiwyg_profile');
        $this->addSql('DROP INDEX IDX_8D93D649A282F7EA ON `user`');
        $this->addSql('ALTER TABLE `user` ADD wysiwyg_profile TINYTEXT DEFAULT NULL COLLATE utf8_unicode_ci, DROP wysiwyg_profile_id');
    }
}
