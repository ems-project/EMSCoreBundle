<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Service\ElasticsearchService;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType as TextareaSymfonyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use EMS\CoreBundle\Form\Field\WysiwygStylesSetPickerType;

class WysiwygFieldType extends DataFieldType
{
    
    /**@var RouterInterface*/
    private $router;
    
    
    
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService, RouterInterface $router)
    {
        parent::__construct($authorizationChecker, $formRegistry, $elasticsearchService);
        $this->router= $router;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getLabel()
    {
        return 'WYSIWYG field';
    }
    
    /**
     * Get a icon to visually identify a FieldType
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-newspaper-o';
    }
    
    /**
     *
     * {@inheritDoc}
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
        $view->vars ['icon'] = $options ['icon'];
        $attr = $view->vars['attr'];
        if (empty($attr['class'])) {
            $attr['class'] = '';
        }
        
        $attr['data-height'] = $options['height'];
        $attr['data-format-tags'] = $options['format_tags'];
        $attr['data-styles-set'] = $options['styles_set'];
        $attr['data-content-css'] = $options['content_css'];
        $attr['class'] .= ' ckeditor_ems';
        $view->vars ['attr'] = $attr;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /*set the default option value for this kind of compound field*/
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('height', 400);
        $resolver->setDefault('format_tags', 'p;h1;h2;h3;h4;h5;h6;pre;address;div');
        $resolver->setDefault('styles_set', 'default');
        $resolver->setDefault('content_css', '../../../../bundles/emscore/css/app.bundle.css');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::reverseViewTransform()
     */
    public function reverseViewTransform($data, FieldType $fieldType)
    {
        
        $path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH);
        
        $out= preg_replace_callback(
            '/('.preg_quote(substr($path, 0, strlen($path)-8), '/').')([^\n\r"\'\?]*)/i',
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
     *
     * {@inheritDoc}
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
     */
    public function viewTransform(DataField $data)
    {
        $out = parent::viewTransform($data);
        
        if (empty($out)) {
            return "";
        }
        
        $path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $path = substr($path, 0, strlen($path)-8);
        $out= preg_replace_callback(
            '/(ems:\/\/asset:)([^\n\r"\'\?]*)/i',
            function ($matches) use ($path) {
                return $path.$matches[2];
            },
            $out
        );
        return $out;
    }
    
    
    
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getDefaultOptions($name)
    {
        $out = parent::getDefaultOptions($name);
        
        $out['displayOptions']['height'] = 200;
        $out['displayOptions']['format_tags'] = 'p;h1;h2;h3;h4;h5;h6;pre;address;div';
        $out['displayOptions']['styles_set'] = 'default';
        $out['displayOptions']['content_css'] = '../../../../bundles/emscore/css/app.bundle.css';
        
        return $out;
    }
    
    /**
     *
     * {@inheritdoc}
     *
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
        $optionsForm->get('displayOptions')->add('height', IntegerType::class, [
                'required' => false,
        ])->add('format_tags', TextType::class, [
                'required' => false,
        ])->add('styles_set', WysiwygStylesSetPickerType::class, [
                'required' => false,
        ])->add('content_css', TextType::class, [
                'required' => false,
        ]);
    }
}
