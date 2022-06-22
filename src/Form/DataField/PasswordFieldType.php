<?php

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
    public function getLabel(): string
    {
        return 'Password field';
    }

    public static function getIcon(): string
    {
        return 'glyphicon glyphicon-asterisk';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['metadata'];
        $builder->add('password_value', PasswordType::class, [
                                'label' => false,
                'disabled' => $this->isDisabled($options),
                'required' => false,
                'attr' => [
                        'autocomplete' => 'new-password', //http://stackoverflow.com/questions/18531437/stop-google-chrome-auto-fill-the-input
                ],
        ]);

        $builder->add('reset_password_value', CheckboxType::class, [
                'label' => 'Reset the password',
                'disabled' => $this->isDisabled($options),
                'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('encryption', null);
    }

    /**
     * {@inheritDoc}
     */
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

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('index', ChoiceType::class, [
                'required' => false,
                'choices' => ['No' => 'no'],
                'empty_data' => 'no',
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
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
     * {@inheritDoc}
     *
     * @param array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $out = $data['password_backup'];
        if ($data['reset_password_value']) {
            $out = null;
        } elseif (isset($data['password_value'])) {
            //new password defined?
            switch ($fieldType->getDisplayOptions()['encryption']) {
                case 'md5':
                    $out = \md5($data['password_value']);
                    break;
                case 'bcrypt_12':
                    $out = \password_hash($data['password_value'], PASSWORD_BCRYPT, ['cost' => 12]);
                    break;
                default:
                    $out = \sha1($data['password_value']);
            }
        }

        return parent::reverseViewTransform($out, $fieldType);
    }

    /**
     * {@inheritDoc}
     */
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->getFieldType()->getDeleted()) {
            switch ($data->getFieldType()->getDisplayOptions()['encryption']) {
                case 'md5':
                    $out[$data->getFieldType()->getName()] = \md5($data->getTextValue());
                    break;
                default:
                    $out[$data->getFieldType()->getName()] = \sha1($data->getTextValue());
                    break;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultOptions(string $name): array
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['index'] = 'not_analyzed';

        return $out;
    }
}
