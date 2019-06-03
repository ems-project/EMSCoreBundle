<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Elasticsearch\Client;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use Psr\Log\Test\LoggerInterfaceTest;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanDeletedContentTypeCommand extends ContainerAwareCommand
{
    protected $client;
    protected $mapping;
    protected $doctrine;
    protected $logger;
    protected $container;

    public function __construct(Registry $doctrine, LoggerInterfaceTest $logger, Client $client, $mapping, $container)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->client = $client;
        $this->mapping = $mapping;
        $this->container = $container;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('ems:contenttype:clean')
            ->setDescription('Clean all deleted content types');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws ORMException
     * @throws OptimisticLockException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
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

        /** @var ContentType $contentType */
        $contentTypes = $ctRepo->findBy([
                'deleted'=> true
        ]);

        foreach ($contentTypes as $contentType) {
            $output->writeln('Remove deleted content type '.$contentType->getName());
            //remove field types
            if ($contentType->getFieldType()) {
                $contentType->unsetFieldType();
                $em->persist($contentType);
            }
            $em->flush($contentType);
            $fields = $fieldRepo->findBy([
                'contentType'=> $contentType
            ]);

            $output->writeln('Remove '.count($fields).' assosiated fields');
            foreach ($fields as $field) {
                $em->remove($field);
                $em->flush($field);
            }

            $revisions = $revisionRepo->findBy(['contentType' => $contentType]);
            $output->writeln('Remove '.count($revisions).' assosiated revisions');
            foreach ($revisions as $revision) {
                $em->remove($revision);
                $em->flush($revision);
            }

            $templates = $templateRepo->findBy(['contentType' => $contentType]);
            $output->writeln('Remove '.count($templates).' assosiated templates');
            /**@var Template $template*/
            foreach ($templates as $template) {
                $em->remove($template);
                $em->flush($template);
            }

            $views = $viewRepo->findBy(['contentType' => $contentType]);
            $output->writeln('Remove '.count($views).' assosiated views');
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
                'deleted'=> true
        ]);
        foreach ($revisions as $revision) {
            $em->remove($revision);
        }
        $em->flush();


        $output->writeln('Done');
    }
}
