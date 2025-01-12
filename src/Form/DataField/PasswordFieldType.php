<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class PasswordFieldType extends DataFieldType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'Password field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'glyphicon glyphicon-asterisk';
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['metadata'];
        $builder->add('password_value', PasswordType::class, [
            'label' => false,
            'disabled' => $this->isDisabled($options),
            'required' => false,
            'attr' => [
                'autocomplete' => 'new-password', // http://stackoverflow.com/questions/18531437/stop-google-chrome-auto-fill-the-input
            ],
        ]);

        $builder->add('reset_password_value', CheckboxType::class, [
            'label' => 'Reset the password',
            'disabled' => $this->isDisabled($options),
            'required' => false,
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('encryption', null);
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific display options
        $optionsForm->get('displayOptions')->add('encryption', ChoiceType::class, [
            'required' => false,
            'choices' => [
                'sha1' => 'sha1',
                'md5' => 'md5',
                'bcrypt (cost 12)' => 'bcrypt_12',
            ],
            'empty_data' => 'sha1',
        ]);
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        return [
            'password_value' => '',
            'password_backup' => $out,
            'reset_password_value' => false,
        ];
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $out = $data['password_backup'];
        if ($data['reset_password_value']) {
            $out = null;
        } elseif (isset($data['password_value'])) {
            $out = match ($fieldType->getDisplayOptions()['encryption']) {
                'md5' => \md5((string) $data['password_value']),
                'bcrypt_12' => \password_hash((string) $data['password_value'], PASSWORD_BCRYPT, ['cost' => 12]),
                default => \sha1((string) $data['password_value']),
            };
        }

        return parent::reverseViewTransform($out, $fieldType);
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            $out[$data->giveFieldType()->getName()] = match ($data->giveFieldType()->getDisplayOptions()['encryption']) {
                'md5' => \md5((string) $data->getTextValue()),
                default => \sha1((string) $data->getTextValue()),
            };
        }
    }

    #[\Override]
    public function getDefaultOptions(string $name): array
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['analyzer'] = 'keyword';

        return $out;
    }
}
