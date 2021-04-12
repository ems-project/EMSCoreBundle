<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CommonBundle\Twig\AssetRuntime;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\WysiwygStylesSetPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
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
    private RouterInterface $router;
    private WysiwygStylesSetService $wysiwygStylesSetService;
    private AssetRuntime $assetRuntime;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, RouterInterface $router, WysiwygStylesSetService $wysiwygStylesSetService, AssetRuntime $assetRuntime)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->router = $router;
        $this->wysiwygStylesSetService = $wysiwygStylesSetService;
        $this->assetRuntime = $assetRuntime;
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'WYSIWYG field';
    }

    /**
     * Get a icon to visually identify a FieldType.
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-newspaper-o';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Form\AbstractType::getParent()
     */
    public function getParent()
    {
        return TextareaSymfonyType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        /*get options for twig context*/
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
            $formatTags = $formatTags ?? $styleSet->getFormatTags();
            $contentCss = $contentCss ?? $styleSet->getContentCss();
            $assets = $styleSet->getAssets();
            $hash = $assets['sha1'] ?? null;
            if (null !== $assets && \is_string($hash)) {
                $this->assetRuntime->unzip($hash, $styleSet->getSaveDir() ?? 'bundles/emsch_assets');
            }
            $attr['data-table-default-css'] = $styleSet->getTableDefaultCss();
        }

        if (isset($options['language'])) {
            $language = \strval($options['language']);
            $attr['data-lang'] = \explode('_', $language)[0];
        }

        $attr['data-height'] = $options['height'];
        $attr['data-format-tags'] = $formatTags;
        $attr['data-styles-set'] = $styleSetName;
        $attr['data-content-css'] = $contentCss;
        $attr['class'] .= ' ckeditor_ems';
        $view->vars['attr'] = $attr;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /*set the default option value for this kind of compound field*/
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('language', null);
        $resolver->setDefault('height', 400);
        $resolver->setDefault('format_tags', '');
        $resolver->setDefault('styles_set', 'default');
        $resolver->setDefault('content_css', '');
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::reverseViewTransform()
     */
    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH);

        $out = \preg_replace_callback(
            '/('.\preg_quote(\substr($path, 0, \strlen($path) - 8), '/').')([^\n\r"\'\?]*)/i',
            function ($matches) {
                return 'ems://asset:'.$matches[2];
            },
            $data
        );
        if (empty($out)) {
            $out = null;
        }

        return parent::reverseViewTransform($out, $fieldType);
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
     */
    public function viewTransform(DataField $data)
    {
        $out = parent::viewTransform($data);

        if (empty($out)) {
            return '';
        }

        $path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $path = \substr($path, 0, \strlen($path) - 8);
        $out = \preg_replace_callback(
            '/(ems:\/\/asset:)([^\n\r"\'\?]*)/i',
            function ($matches) use ($path) {
                return $path.$matches[2];
            },
            $out
        );

        return $out;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions($name)
    {
        $out = parent::getDefaultOptions($name);

        $out['displayOptions']['height'] = 200;
        $out['displayOptions']['format_tags'] = '';
        $out['displayOptions']['styles_set'] = 'default';
        $out['displayOptions']['content_css'] = '';

        return $out;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific mapping options
        $optionsForm->get('mappingOptions')
        ->add('analyzer', AnalyzerPickerType::class)
        ->add('copy_to', TextType::class, [
                'required' => false,
        ]);
        $optionsForm->get('displayOptions')
            ->add('language', ChoiceType::class, [
                'required' => false,
                'choices' => \array_flip(Locales::getNames()),
            ])
            ->add('height', IntegerType::class, ['required' => false])
            ->add('styles_set', WysiwygStylesSetPickerType::class, ['required' => false])
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
        $optionsForm->get('migrationOptions')->add('transformer', TextType::class, [
            'required' => false,
        ]);
    }
}
