<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\MediaLibrary;

use EMS\CoreBundle\Core\Component\MediaLibrary\File\MediaLibraryFile;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryDocument;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class MediaLibraryDocumentFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array{'data': MediaLibraryDocument}        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $builder->getData();
        $builder->add('name', TextType::class);

        if ($data instanceof MediaLibraryFile) {
            if ($data->hasName()) {
                $builder
                    ->add('name', HiddenType::class)
                    ->add('filename', TextType::class, ['constraints' => new NotBlank()]);
            }

            $builder
                ->add('filesize', HiddenType::class, [
                    'constraints' => new NotBlank(),
                ])
                ->add('fileMimetype', HiddenType::class, [
                    'empty_data' => 'application/bin',
                ])
                ->add('fileHash', HiddenType::class, [
                    'constraints' => new NotBlank(),
                ])
                ->add('fileResizedHash', HiddenType::class, [
                    'empty_data' => null,
                ])
            ;
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MediaLibraryDocument::class,
        ]);
    }
}
