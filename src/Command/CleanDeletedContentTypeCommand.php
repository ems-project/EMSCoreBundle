<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use EMS\CoreBundle\Service\Mapping;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[AsCommand(
    name: Commands::CONTENT_TYPE_CLEAN,
    description: 'Clean all deleted content types.',
    hidden: false,
    aliases: ['ems:contenttype:clean']
)]
class CleanDeletedContentTypeCommand extends Command
{
    public function __construct(protected Registry $doctrine, protected LoggerInterface $logger, protected Mapping $mapping, protected ContainerInterface $container)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var ContentTypeRepository $ctRepo */
        $ctRepo = $em->getRepository(ContentType::class);
        /** @var FieldTypeRepository $fieldRepo */
        $fieldRepo = $em->getRepository(FieldType::class);
        /** @var RevisionRepository $revisionRepo */
        $revisionRepo = $em->getRepository(Revision::class);
        /** @var TemplateRepository $templateRepo */
        $templateRepo = $em->getRepository(Template::class);
        /** @var ViewRepository $viewRepo */
        $viewRepo = $em->getRepository(View::class);

        $output->writeln('Cleaning deleted fields');
        $fields = $fieldRepo->findBy(['deleted' => true]);
        foreach ($fields as $field) {
            $em->remove($field);
        }
        $em->flush();

        $contentTypes = $ctRepo->findBy([
            'deleted' => true,
        ]);

        foreach ($contentTypes as $contentType) {
            $output->writeln('Remove deleted content type '.$contentType->getName());
            if ($contentType->hasFieldType()) {
                $contentType->unsetFieldType();
                $em->persist($contentType);
                $em->flush($contentType);
            }
            $fields = $fieldRepo->findBy([
                'contentType' => $contentType,
            ]);

            $output->writeln('Remove '.\count($fields).' assosiated fields');
            foreach ($fields as $field) {
                $em->remove($field);
                $em->flush($field);
            }

            $revisions = $revisionRepo->findBy(['contentType' => $contentType]);
            $output->writeln('Remove '.\count($revisions).' assosiated revisions');
            foreach ($revisions as $revision) {
                $em->remove($revision);
                $em->flush($revision);
            }

            $templates = $templateRepo->findBy(['contentType' => $contentType]);
            $output->writeln('Remove '.\count($templates).' assosiated templates');
            /** @var Template $template */
            foreach ($templates as $template) {
                $em->remove($template);
                $em->flush($template);
            }

            $views = $viewRepo->findBy(['contentType' => $contentType]);
            $output->writeln('Remove '.\count($views).' assosiated views');
            foreach ($views as $view) {
                $em->remove($view);
                $em->flush($view);
            }

            $em->remove($contentType);
            $em->flush($contentType);
        }

        $output->writeln('Remove deleted revisions');
        /** @var Revision $revision */
        $revisions = $revisionRepo->findBy([
            'deleted' => true,
        ]);
        foreach ($revisions as $revision) {
            $em->remove($revision);
        }
        $em->flush();

        $output->writeln('Done');

        return 0;
    }
}
