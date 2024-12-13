<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CommonBundle\Twig\AssetRuntime;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\WysiwygStylesSetPickerType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use EMS\Helpers\Standard\Locale;
use EMS\Helpers\Standard\Type;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType as TextareaSymfonyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class WysiwygFieldType extends DataFieldType
{
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, private readonly RouterInterface $router, private readonly WysiwygStylesSetService $wysiwygStylesSetService, private readonly AssetRuntime $assetRuntime)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
    }

    public function getLabel(): string
    {
        return 'WYSIWYG field';
    }

    public static function getIcon(): string
    {
        return 'fa fa-newspaper-o';
    }

    public function getParent(): string
    {
        return TextareaSymfonyType::class;
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /* get options for twig context */
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];
        $attr = $view->vars['attr'];
        if (empty($attr['class'])) {
            $attr['class'] = '';
        }

        $styleSetName = $options['styles_set'] ?? null;
        $formatTags = $options['format_tags'] ?? null;
        $contentCss = $options['content_css'] ?? null;
        $styleSet = $this->wysiwygStylesSetService->getByName($styleSetName);
        if (null !== $styleSet) {
            $formatTags ??= $styleSet->getFormatTags();
            $contentCss ??= $styleSet->getContentCss();
            $assets = $styleSet->getAssets();
            $hash = $assets['sha1'] ?? null;
            $saveDir = $styleSet->getSaveDir();
            if (null !== $assets && \is_string($hash) && null !== $saveDir) {
                $this->assetRuntime->unzip($hash, $saveDir);
            }
            if (null === $saveDir && $contentCss) {
                $contentCss = $this->router->generate('ems_asset_in_archive', [
                    'hash' => $hash,
                    'path' => $contentCss,
                ]);
            }
            $attr['data-table-default-css'] = $styleSet->getTableDefaultCss();
        }

        if (isset($options['language'])) {
            $attr['data-lang'] = Locale::getLanguage($options['language']);
        }

        $attr['data-referrer-ems-id'] = $options['referrer-ems-id'] ?? false;
        $attr['data-height'] = $options['height'];
        $attr['data-format-tags'] = $formatTags;
        $attr['data-styles-set'] = $styleSetName;
        $attr['data-content-css'] = $contentCss;
        $attr['class'] .= ' ckeditor_ems';
        $view->vars['attr'] = $attr;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('language', null);
        $resolver->setDefault('height', 400);
        $resolver->setDefault('format_tags', '');
        $resolver->setDefault('styles_set', 'default');
        $resolver->setDefault('content_css', '');
        $resolver->setDefault('styles_set_preview', false);
    }

    /**
     * @param array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $out = \preg_replace_callback(
            '/('.\preg_quote(\substr($path, 0, \strlen($path) - 8), '/').')([^\n\r"\'\?]*)/i',
            fn ($matches) => 'ems://asset:'.$matches[2],
            $data
        );

        $path = $this->router->generate(Routes::DATA_LINK, ['key' => '__KEY__'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $out = \preg_replace_callback(
            '/('.\preg_quote(\substr($path, 0, \strlen($path) - 7), '/').')(?P<key>[^\n\r"\'\?]*)/i',
            fn ($matches) => 'ems://'.$matches['key'],
            $out
        );

        if ('' === $out) {
            $out = null;
        }

        return parent::reverseViewTransform($out, $fieldType);
    }

    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        if (!\is_string($out)) {
            return '';
        }

        $path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $path = \substr($path, 0, \strlen($path) - 8);
        $out = \preg_replace_callback(
            '/(ems:\/\/asset:)([^\n\r"\'\?]*)/i',
            fn ($matches) => $path.$matches[2],
            $out
        );
        $path = $this->router->generate(Routes::DATA_LINK, ['key' => '__KEY__'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $out = \preg_replace_callback(
            '/ems:\/\/(?P<key>file:([^\n\r"\'\?]*))/i',
            fn ($matches) => \str_replace('__KEY__', $matches['key'], $path),
            Type::string($out)
        );

        return $out;
    }

    public function getDefaultOptions(string $name): array
    {
        $out = parent::getDefaultOptions($name);

        $out['displayOptions']['height'] = 200;
        $out['displayOptions']['format_tags'] = '';
        $out['displayOptions']['styles_set'] = 'default';
        $out['displayOptions']['content_css'] = '';

        return $out;
    }

    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class)
                ->add('copy_to', TextType::class, ['required' => false]);
        }
        $optionsForm->get('displayOptions')
            ->add('language', ChoiceType::class, [
                'required' => false,
                'choices' => \array_flip(Locales::getNames()),
                'choice_translation_domain' => false,
            ])
            ->add('height', IntegerType::class, ['required' => false])
            ->add('styles_set', WysiwygStylesSetPickerType::class, ['required' => false])
            ->add('styles_set_preview', CheckboxType::class, ['required' => false])
            ->add('format_tags', TextType::class, [
                'required' => false,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
                'label' => 'form.form_field.wysiwyg.format_tags.label',
            ])
            ->add('content_css', TextType::class, [
                'required' => false,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
                'label' => 'form.form_field.wysiwyg.content_css.label',
            ])
        ;
    }
}
