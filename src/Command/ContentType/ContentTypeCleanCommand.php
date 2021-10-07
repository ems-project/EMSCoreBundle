<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\ContentType;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ContentTypeCleanCommand extends AbstractCommand
{
    private Registry $doctrine;

    protected static $defaultName = Commands::CONTENTTYPE_CLEAN;

    public function __construct(Registry $doctrine)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Clean all deleted content types');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var ContentTypeRepository $ctRepo */
        $ctRepo = $em->getRepository('EMSCoreBundle:ContentType');
        /** @var FieldTypeRepository $fieldRepo */
        $fieldRepo = $em->getRepository('EMSCoreBundle:FieldType');
        /** @var RevisionRepository $revisionRepo */
        $revisionRepo = $em->getRepository('EMSCoreBundle:Revision');
        /** @var TemplateRepository $templateRepo */
        $templateRepo = $em->getRepository('EMSCoreBundle:Template');
        /** @var ViewRepository $viewRepo */
        $viewRepo = $em->getRepository('EMSCoreBundle:View');

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
