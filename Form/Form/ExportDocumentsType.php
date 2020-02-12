<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Form\ExportDocuments;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Field\RenderOptionType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

class ExportDocumentsType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var ExportDocuments $data */
        $data = $builder->getData();

        $formatChoices = ['JSON export' => 'json'];
        /** @var Template $template */
        foreach ($data->getContentType()->getTemplates() as $template) {
            if (RenderOptionType::EXPORT == $template->getRenderOption() && $template->getBody()) {
                $formatChoices[$template->getName()] = $template->getId();
            }
        }
        
        $builder
            ->setAction($data->getAction())
            ->add('query', HiddenType::class, [
                'data' => $data->getQuery(),
            ])
            ->add('format', ChoiceType::class, [
                'choices' => $formatChoices,
            ])
            ->add('withBusinessKey', CheckboxType::class, [
                'data' => true,
            ])
            ->add('export', SubmitEmsType::class, [
                'label' => 'Export ' . $data->getContentType()->getPluralName(),
                'attr' => ['class' => 'btn-primary btn-sm '],
                'icon' => 'glyphicon glyphicon-export'
            ]);
    }
}
