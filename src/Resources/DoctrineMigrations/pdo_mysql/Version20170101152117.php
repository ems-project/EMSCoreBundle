<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20170101152117 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\UrlAttachmentFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\UrlAttachmentFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\TimeFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\TimeFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\TextStringFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\TextStringFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\TextareaFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\TextareaFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\TabsFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\TabsFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\SubfieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\SubfieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\SelectFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\SelectFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\RadioFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\RadioFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\ChoiceFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\ChoiceFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\CheckboxFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\CheckboxFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\ComputedFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\ComputedFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\CollectionItemFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\CollectionItemFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\ColorPickerFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\ColorPickerFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\DateRangeFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\DateRangeFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\ContainerFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\ContainerFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\DataFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\DataFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\DataLinkFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\DataLinkFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\DateFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\DateFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\HiddenFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\HiddenFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\EmailFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\EmailFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\FileAttachmentFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\FileAttachmentFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\JSONFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\JSONFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\IconFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\IconFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\IntegerFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\IntegerFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\NestedFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\NestedFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\NumberFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\NumberFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\PasswordFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\PasswordFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\OuuidFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\OuuidFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\AssetFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\AssetFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\WysiwygFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\WysiwygFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\CollectionFieldType\' where  ft.type = \'AppBundle\\\\Form\\\\DataField\\\\CollectionFieldType\'');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\UrlAttachmentFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\UrlAttachmentFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\TimeFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\TimeFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\TextStringFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\TextStringFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\TextareaFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\TextareaFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\TabsFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\TabsFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\SubfieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\SubfieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\SelectFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\SelectFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\RadioFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\RadioFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\ChoiceFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\ChoiceFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\CheckboxFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\CheckboxFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\ComputedFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\ComputedFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\CollectionItemFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\CollectionItemFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\ColorPickerFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\ColorPickerFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\DateRangeFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\DateRangeFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\ContainerFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\ContainerFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\DataFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\DataFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\DataLinkFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\DataLinkFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\DateFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\DateFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\HiddenFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\HiddenFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\EmailFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\EmailFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\FileAttachmentFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\FileAttachmentFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\JSONFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\JSONFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\IconFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\IconFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\IntegerFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\IntegerFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\NestedFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\NestedFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\NumberFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\NumberFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\PasswordFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\PasswordFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\OuuidFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\OuuidFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\AssetFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\AssetFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\WysiwygFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\WysiwygFieldType\'');
        $this->addSql('update field_type ft set ft.type = \'AppBundle\\\\Form\\\\DataField\\\\CollectionFieldType\' where  ft.type = \'EMS\\\\CoreBundle\\\\Form\\\\DataField\\\\CollectionFieldType\'');
    }
}
