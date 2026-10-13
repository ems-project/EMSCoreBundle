<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\Environment;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Form\Field\CancelType;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\Mapping;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class ViewEnvironmentType extends AbstractType
{
    public function __construct(
        private readonly Mapping $mapping,
        private readonly LocalizedLoggerInterface $logger,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $environment = $options['data'];

        try {
            $builder
                ->add('info', CodeEditorType::class, [
                    'data' => Json::encode($this->mapping->getMapping($environment), true),
                    'label' => t('field.mapping', [], 'emsco-core'),
                    'mapped' => false,
                    'language' => 'ace/mode/json',
                    'max-lines' => 40,
                    'min-lines' => 40,
                ])
                ->add('cancel', CancelType::class, [
                    'attr' => ['data-testid' => 'environment-close'],
                    'label' => t('action.close', [], 'emsco-core'),
                    'route' => Routes::ADMIN_ENVIRONMENT_INDEX,
                ])
            ;
        } catch (NotFoundException $notFoundException) {
            $this->logger->messageError(t('message.environment_alias_missing', [
                'environment' => $environment->getLabel(),
            ], 'emsco-core'), [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $notFoundException->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $notFoundException,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['data_class' => Environment::class])
        ;
    }
}
