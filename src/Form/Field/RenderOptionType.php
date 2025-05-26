<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class RenderOptionType extends ChoiceType
{
    final public const string EMBED = 'embed';
    final public const string EXPORT = 'export';
    final public const string IMPORT = 'import';
    final public const string EXTERNALLINK = 'externalLink';
    final public const string RAW_HTML = 'rawHTML';
    final public const string EVENT = 'event';
    final public const string NOTIFICATION = 'notification';
    final public const string JOB = 'job';
    final public const string PDF = 'pdf';

    /** @var array<string, string> */
    private readonly array $choices;

    public function __construct(?ChoiceListFactoryInterface $choiceListFactory = null, ?TranslatorInterface $translator = null)
    {
        parent::__construct($choiceListFactory, $translator);
        $this->choices = [
            t('core.action.render-option.embed', [], EMSCoreBundle::TRANS_CORE)->getMessage() => self::EMBED,
            t('core.action.render-option.export', [], EMSCoreBundle::TRANS_CORE)->getMessage() => self::EXPORT,
            t('core.action.render-option.import', [], EMSCoreBundle::TRANS_CORE)->getMessage() => self::IMPORT,
            t('core.action.render-option.external-link', [], EMSCoreBundle::TRANS_CORE)->getMessage() => self::EXTERNALLINK,
            t('core.action.render-option.raw-html', [], EMSCoreBundle::TRANS_CORE)->getMessage() => self::RAW_HTML,
            t('core.action.render-option.notification', [], EMSCoreBundle::TRANS_CORE)->getMessage() => self::NOTIFICATION,
            t('core.action.render-option.job', [], EMSCoreBundle::TRANS_CORE)->getMessage() => self::JOB,
            t('core.action.render-option.pdf', [], EMSCoreBundle::TRANS_CORE)->getMessage() => self::PDF,
            t('core.action.render-option.event', [], EMSCoreBundle::TRANS_CORE)->getMessage() => self::EVENT,
        ];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'choices' => $this->choices,
            'multiple' => false,
            'expanded' => false,
            'choices_as_values' => null, // to be deprecated in 3.1
            'choice_loader' => null,
            'choice_label' => null,
            'choice_name' => null,
            'choice_value' => fn ($value) => $value,
            'choice_attr' => null,
            'preferred_choices' => [],
            'group_by' => null,
            'empty_data' => '',
            'placeholder' => null,
            'error_bubbling' => false,
            'compound' => false,
            // The view data is always a string, even if the "data" option
            // is manually set to an object.
            // See https://github.com/symfony/symfony/pull/5582
            'data_class' => null,
            'choice_translation_domain' => EMSCoreBundle::TRANS_CORE,
        ]);
    }
}
