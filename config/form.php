<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CommonBundle\Twig\AssetRuntime;
use EMS\CoreBundle\Form\DataField\ActionFieldType;
use EMS\CoreBundle\Form\DataField\AssetFieldType;
use EMS\CoreBundle\Form\DataField\CheckboxFieldType;
use EMS\CoreBundle\Form\DataField\ChoiceFieldType;
use EMS\CoreBundle\Form\DataField\CodeFieldType;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\CollectionItemFieldType;
use EMS\CoreBundle\Form\DataField\ColorPickerFieldType;
use EMS\CoreBundle\Form\DataField\ComputedFieldType;
use EMS\CoreBundle\Form\DataField\ContainerFieldType;
use EMS\CoreBundle\Form\DataField\CopyToFieldType;
use EMS\CoreBundle\Form\DataField\DataLinkFieldType;
use EMS\CoreBundle\Form\DataField\DateFieldType;
use EMS\CoreBundle\Form\DataField\DateRangeFieldType;
use EMS\CoreBundle\Form\DataField\DateTimeFieldType;
use EMS\CoreBundle\Form\DataField\EmailFieldType;
use EMS\CoreBundle\Form\DataField\FormFieldType;
use EMS\CoreBundle\Form\DataField\HolderFieldType;
use EMS\CoreBundle\Form\DataField\IconFieldType;
use EMS\CoreBundle\Form\DataField\IndexedAssetFieldType;
use EMS\CoreBundle\Form\DataField\IntegerFieldType;
use EMS\CoreBundle\Form\DataField\JSONFieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuEditorFieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuLinkFieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuNestedEditorFieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuNestedLinkFieldType;
use EMS\CoreBundle\Form\DataField\MultiplexedTabContainerFieldType;
use EMS\CoreBundle\Form\DataField\NestedFieldType;
use EMS\CoreBundle\Form\DataField\NumberFieldType;
use EMS\CoreBundle\Form\DataField\Options\MigrationOptionsType;
use EMS\CoreBundle\Form\DataField\OuuidFieldType;
use EMS\CoreBundle\Form\DataField\PasswordFieldType;
use EMS\CoreBundle\Form\DataField\SelectUserPropertyFieldType;
use EMS\CoreBundle\Form\DataField\SubfieldType;
use EMS\CoreBundle\Form\DataField\TabsFieldType;
use EMS\CoreBundle\Form\DataField\TextareaFieldType;
use EMS\CoreBundle\Form\DataField\TextStringFieldType;
use EMS\CoreBundle\Form\DataField\TimeFieldType;
use EMS\CoreBundle\Form\DataField\VersionTagFieldType;
use EMS\CoreBundle\Form\DataField\WysiwygFieldType;
use EMS\CoreBundle\Form\DataTransformer\AssetTransformer;
use EMS\CoreBundle\Form\Extension\LocaleFormExtension;
use EMS\CoreBundle\Form\Factory\ContentTypeFieldChoiceListFactory;
use EMS\CoreBundle\Form\Factory\ObjectChoiceListFactory;
use EMS\CoreBundle\Form\Field\AlignIndexesType;
use EMS\CoreBundle\Form\Field\AnalyzerOptionsType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use EMS\CoreBundle\Form\Field\ContentTypePickerType;
use EMS\CoreBundle\Form\Field\EditImageType;
use EMS\CoreBundle\Form\Field\EnvironmentPickerType;
use EMS\CoreBundle\Form\Field\FieldTypePickerType;
use EMS\CoreBundle\Form\Field\FormPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\QuerySearchPickerType;
use EMS\CoreBundle\Form\Field\RolePickerType;
use EMS\CoreBundle\Form\Field\SelectUserPropertyType;
use EMS\CoreBundle\Form\Field\WysiwygStylesSetPickerType;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use EMS\CoreBundle\Form\Form\ActionType;
use EMS\CoreBundle\Form\Form\Dashboard\DashboardType;
use EMS\CoreBundle\Form\Form\EditImageModalType;
use EMS\CoreBundle\Form\Form\EmsCollectionType;
use EMS\CoreBundle\Form\Form\FieldHolderType;
use EMS\CoreBundle\Form\Form\FormType;
use EMS\CoreBundle\Form\Form\GroupType;
use EMS\CoreBundle\Form\Form\LoadLinkModalType;
use EMS\CoreBundle\Form\Form\ManagedAliasType;
use EMS\CoreBundle\Form\Form\NotificationFormType;
use EMS\CoreBundle\Form\Form\QuerySearchType;
use EMS\CoreBundle\Form\Form\RevisionJsonMenuNestedType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Form\Form\ScheduleType;
use EMS\CoreBundle\Form\Form\SearchFormType;
use EMS\CoreBundle\Form\Form\UserOptionsType;
use EMS\CoreBundle\Form\Form\UserType;
use EMS\CoreBundle\Form\Form\ViewType;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskFiltersType;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskType;
use EMS\CoreBundle\Form\Submission\ProcessType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->private();

    $services->set('ems.form.factories.objectChoiceListFactory', ObjectChoiceListFactory::class)
        ->args([
            service('ems.service.contenttype'),
            service('ems.service.objectchoicecache'),
        ]);

    $services->set('ems.form.factories.contentTypeFieldChoiceListFactory', ContentTypeFieldChoiceListFactory::class);

    $services->set('ems_core.form_data_field_options.migration_options_type', MigrationOptionsType::class)
        ->args([service('ems_core.core_content_type_transformer.content_transformers')])
        ->tag('form.type');

    $services->set('ems.fieldtype.container', ContainerFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'container'])
        ->tag('form.type');

    $services->set('ems.fieldtype.collection', CollectionFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
            service('ems.service.data'),
            service('logger'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'collection'])
        ->tag('form.type');

    $services->set('ems.fieldtype.colorpicker', ColorPickerFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'colorpicker'])
        ->tag('form.type');

    $services->set('ems.fieldtype.nested', NestedFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'nested'])
        ->tag('form.type');

    $services->set('ems.fieldtype.tabs', TabsFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
            service('emsco.manager.user'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'tabs'])
        ->tag('form.type');

    $services->set('ems.fieldtype.ouuid', OuuidFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'ouuid'])
        ->tag('form.type');

    $services->set('ems.fieldtype.computed', ComputedFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'computed'])
        ->tag('form.type');

    $services->set('ems.fieldtype.json', JSONFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'json'])
        ->tag('form.type');

    $services->set('ems.fieldtype.dataLink', DataLinkFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
            service('event_dispatcher'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'datalink'])
        ->tag('form.type');

    $services->set('ems.fieldtype.textstring', TextStringFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'textstring'])
        ->tag('form.type');

    $services->set('ems.fieldtype.wysiwyg', WysiwygFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
            service('router'),
            service('ems.service.wysiwyg_styles_set'),
            service(AssetRuntime::class),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'wysiwyg'])
        ->tag('form.type');

    $services->set('ems.fieldtype.code', CodeFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'code'])
        ->tag('form.type');

    $services->set('ems.fieldtype.json_menu_editor', JsonMenuEditorFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'json_menu'])
        ->tag('form.type');

    $services->set('ems.fieldtype.json_menu_nested_editor', JsonMenuNestedEditorFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'json_menu_nested'])
        ->tag('form.type');

    $services->set('ems.fieldtype.textarea', TextareaFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'textarea'])
        ->tag('form.type');

    $services->set('ems.fieldtype.password', PasswordFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'password'])
        ->tag('form.type');

    $services->set('ems.fieldtype.email', EmailFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'email'])
        ->tag('form.type');

    $services->set('ems.fieldtype.icon', IconFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'icon'])
        ->tag('form.type');

    $services->set('ems.fieldtype.action', ActionFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'action'])
        ->tag('form.type');

    $services->set('ems.fieldtype.asset', AssetFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
            service('ems.service.file'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'asset'])
        ->tag('form.type');

    $services->set('ems.fieldtype.indexed_asset', IndexedAssetFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
            service('ems.service.file'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'file_attachment'])
        ->tag('form.type');

    $services->set('ems.fieldtype.choice', ChoiceFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'choice'])
        ->tag('form.type');

    $services->set('ems.fieldtype.multiplexed_tab_container', MultiplexedTabContainerFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
            service('emsco.manager.user'),
            '%emsch.locales%',
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'multiplexed_tab_container'])
        ->tag('form.type');

    $services->set('ems.fieldtype.json_menu_link', JsonMenuLinkFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
            service('ems.service.contenttype'),
            service('ems_common.service.elastica'),
            service('ems_common.json.decoder'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'json_menu_link'])
        ->tag('form.type');

    $services->set('ems.fieldtype.json_menu_nested_link', JsonMenuNestedLinkFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
            service('ems_common.service.elastica'),
            service(EnvironmentService::class),
            service('twig'),
            service('emsco.logger'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'json_menu_nested_link'])
        ->tag('form.type');

    $services->set('ems.fieldtype.checkbox', CheckboxFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'checkbox'])
        ->tag('form.type');

    $services->set('ems.fieldtype.number', NumberFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'number'])
        ->tag('form.type');

    $services->set('ems.fieldtype.integer', IntegerFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'integer'])
        ->tag('form.type');

    $services->set('ems.fieldtype.date', DateFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'date'])
        ->tag('form.type');

    $services->set('ems.fieldtype.date_time', DateTimeFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'date_time'])
        ->tag('form.type');

    $services->set('ems.fieldtype.daterange', DateRangeFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'daterange'])
        ->tag('form.type');

    $services->set('ems.fieldtype.time', TimeFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'time'])
        ->tag('form.type');

    $services->set('ems.fieldtype.collection_item', CollectionItemFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('form.type');

    $services->set('ems.fieldtype.copyto', CopyToFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'copyto'])
        ->tag('form.type');

    $services->set('ems.fieldtype.subfield', SubfieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('form.type');

    $services->set('ems.fieldtype.select_user_property', SelectUserPropertyFieldType::class)
        ->args([
            service('ems.service.user'),
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'select_user_property'])
        ->tag('form.type');

    $services->set('ems.fieldtype.version_tag', VersionTagFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
            service('ems.service.revision'),
            service('ems.service.environment'),
            service(ContentTypeService::class),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'version_tag'])
        ->tag('form.type');

    $services->set('ems.fieldtype.form', FormFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
            service('ems.form.fieldtype.fieldtypetype'),
            service('ems.form.manager'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'form'])
        ->tag('form.type');

    $services->set('ems.fieldtype.holder', HolderFieldType::class)
        ->args([
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.service.elasticsearch'),
        ])
        ->tag('ems.form.datafieldtype', ['alias' => 'holder'])
        ->tag('form.type');

    $services->set('ems.exteneded.collectiontype', EmsCollectionType::class)
        ->args([service('security.authorization_checker')])
        ->tag('form.type');

    $services->set(ProcessType::class)
        ->args([service('router')])
        ->tag('form.type');

    $services->set(SearchFormType::class)
        ->args([
            service('security.authorization_checker'),
            service('ems.service.sort_option'),
            service('ems.service.search_field_option'),
        ])
        ->tag('form.type');

    $services->set('ems.form.field.objectpickertype', ObjectPickerType::class)
        ->args([
            service('ems.form.factories.objectChoiceListFactory'),
            service('ems.service.query_search'),
        ])
        ->tag('form.type');

    $services->set('ems_core.form.dashboard_type', DashboardType::class)
        ->args([service('ems_core.dashboard.dashboards')])
        ->tag('form.type');

    $services->set('ems_core.form.form_type', FormType::class)
        ->tag('form.type');

    $services->set('ems_core.form.view_type', ViewType::class)
        ->args([service('service_container')])
        ->tag('form.type');

    $services->set('ems_core.form.schedule_type', ScheduleType::class)
        ->tag('form.type');

    $services->set('ems_core.form.user_type', UserType::class)
        ->args([
            service('ems.service.user'),
            service('ems.group.manager'),
            '%ems_core.circles_object%',
            '%ems_core.group_feature%',
        ])
        ->tag('form.type');

    $services->set('ems.form.field.contenttypefieldpickertype', ContentTypeFieldPickerType::class)
        ->args([service('ems.form.factories.contentTypeFieldChoiceListFactory')])
        ->tag('form.type');

    $services->set('ems.form.field.querySearchPickerType', QuerySearchPickerType::class)
        ->args([service('ems.service.query_search')])
        ->tag('form.type');

    $services->set('ems.form.field.datafieldtypepickertype', FieldTypePickerType::class)
        ->tag('form.type');

    $services->set('ems.form.field.wysiwygstylessetpickertype', WysiwygStylesSetPickerType::class)
        ->args([service('ems.service.wysiwyg_styles_set')])
        ->tag('form.type');

    $services->set('ems.form.field.rolepickertype', RolePickerType::class)
        ->args([service('ems.service.user')])
        ->tag('form.type');

    $services->set('ems.form.field.environmentpickertype', EnvironmentPickerType::class)
        ->args([service('ems.service.environment')])
        ->tag('form.type');

    $services->set('ems.form.field.contenttypepickertype', ContentTypePickerType::class)
        ->args([service('ems.service.contenttype')])
        ->tag('form.type');

    $services->set('ems.form.field.icon_picker_type', IconPickerType::class)
        ->call('setTemplateNamespace', ['%ems_core.template_namespace%'])
        ->tag('form.type');

    $services->set('ems.form.fieldtype.fieldtypetype', FieldTypeType::class)
        ->args([
            service('ems.form.field.datafieldtypepickertype'),
            service('form.registry'),
            service('logger'),
        ])
        ->tag('form.type');

    $services->set('ems.form.form.action_type', ActionType::class)
        ->args([
            '%ems_core.circles_object%',
            service('ems.service.environment'),
        ])
        ->tag('form.type');

    $services->set('ems.form.form.querysearchtype', QuerySearchType::class)
        ->args([service('ems.service.environment')])
        ->tag('form.type');

    $services->set('ems.form.form.notificationtype', NotificationFormType::class)
        ->args([service('ems.service.environment')])
        ->tag('form.type');

    $services->set('ems.form.form.revisiontype', RevisionType::class)
        ->args([
            service('form.registry'),
            service('emsco.manager.user'),
        ])
        ->tag('form.type');

    $services->set('ems.form.revision_json_menu_nested', RevisionJsonMenuNestedType::class)
        ->args([service('form.registry')])
        ->tag('form.type');

    $services->set('ems.form.field.analyzeroptionstype', AnalyzerOptionsType::class)
        ->args([service('doctrine')])
        ->tag('form.type');

    $services->set('ems.form.field.analyzerpickertype', AnalyzerPickerType::class)
        ->args([service('ems.repository.analyzer')])
        ->tag('form.type');

    $services->set('ems.form.field.formpickertype', FormPickerType::class)
        ->args([service('ems.form.manager')])
        ->tag('form.type');

    $services->set('ems.form.field.alignindexes', AlignIndexesType::class)
        ->args([service('ems.service.alias')])
        ->tag('form.type');

    $services->set('ems.form.managed_alias', ManagedAliasType::class)
        ->args([service('ems.service.alias')])
        ->tag('form.type');

    $services->set('ems.form.field.select_user_property', SelectUserPropertyType::class)
        ->args([service('ems.service.user')])
        ->tag('form.type');

    $services->set('emsco.form.revision.task.filters', RevisionTaskFiltersType::class)
        ->tag('form.type');

    $services->set('emsco.form.revision.task', RevisionTaskType::class)
        ->args(['%ems_core.datepicker_format%'])
        ->tag('form.type');

    $services->set('ems_core.form.user', UserOptionsType::class)
        ->args([
            service('ems.form.manager'),
            service('form.registry'),
            service('ems.service.data'),
            service('logger'),
            '%ems_core.custom_user_options_form%',
        ])
        ->tag('form.type');

    $services->set('ems_core.form.field-holder', FieldHolderType::class)
        ->args([
            service('ems.form.manager'),
            service('form.registry'),
            service('ems.service.data'),
        ])
        ->tag('form.type');

    $services->set('ems_core.form.modal.link', LoadLinkModalType::class)
        ->args([service('router')])
        ->tag('form.type');

    $services->set('ems_core.form.modal.edit-image', EditImageModalType::class)
        ->args([service('router')])
        ->tag('form.type');

    $services->set('ems_core.form.field.image', EditImageType::class)
        ->args([service('ems_core.form.transformer.asset')])
        ->tag('form.type');

    $services->set('ems_core.form.user_group', GroupType::class)
        ->args([service('ems.service.user')])
        ->tag('form.type');

    $services->set('emsco.form_extension.locale_form_extension', LocaleFormExtension::class)
        ->tag('form.type_extension');

    $services->set('ems_core.form.transformer.asset', AssetTransformer::class)
        ->args([
            service('router'),
            service('ems_common.storage.manager'),
        ]);
};
