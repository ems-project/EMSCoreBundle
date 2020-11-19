<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\NotificationRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Mapping;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteCommand extends Command
{
    /** @var Client */
    protected $client;
    /** @var Mapping */
    protected $mapping;
    /** @var Registry */
    protected $doctrine;
    /** @var Logger */
    protected $logger;
    /** @var ContainerInterface */
    protected $container;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var EnvironmentService */
    private $environmentService;

    public function __construct(Registry $doctrine, Logger $logger, Client $client, Mapping $mapping, ContainerInterface $container, ContentTypeService $contentTypeService, EnvironmentService $environmentService)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->client = $client;
        $this->mapping = $mapping;
        $this->container = $container;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ems:contenttype:delete')
            ->setDescription('Delete all instances of a content type ')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Content type name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var Client $client */
        $client = $this->client;
        $name = $input->getArgument('name');
        if (!\is_string($name)) {
            throw new \RuntimeException('Unexpected content type name argument');
        }
        /** @var ContentTypeRepository $ctRepo */
        $ctRepo = $em->getRepository('EMSCoreBundle:ContentType');
        /** @var ContentType|null $contentType */
        $contentType = $ctRepo->findOneBy([
                'name' => $name,
                'deleted' => 0,
        ]);

        if (!$contentType instanceof ContentType) {
            $output->writeln('Content type '.$name.' not found');

            return -1;
        }

        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository('EMSCoreBundle:Revision');

        /** @var NotificationRepository $notRepo */
        $notRepo = $em->getRepository('EMSCoreBundle:Notification');

        $counter = 0;
        $total = $revRepo->countByContentType($contentType);
        if (0 == $total) {
            $output->writeln('Content type "'.$name.'" is already empty');
        } else {
            $progress = new ProgressBar($output, $total);
            $progress->start();

            $environmentsIndex = [];
            /** @var Environment $environment */
            foreach ($this->environmentService->getManagedEnvironement() as $environment) {
                $environmentsIndex[$environment->getName()] = $this->contentTypeService->getIndex($contentType, $environment);
            }

            while ($revRepo->countByContentType($contentType) > 0) {
                $revisions = $revRepo->findByContentType($contentType, null, 20);
                /** @var Revision $revision */
                foreach ($revisions as $revision) {
                    foreach ($revision->getEnvironments() as $environment) {
                        try {
                            $client->delete([
                                    'index' => $environmentsIndex[$environment->getName()],
                                    'type' => $contentType->getName(),
                                    'id' => $revision->getOuuid(),
                            ]);
                        } catch (\Throwable $e) {
                            //Deleting something that is not present shouldn't make problem.
                        }
                        $revision->removeEnvironment($environment);
                    }
                    ++$counter;
                    $notifications = $notRepo->findBy([
                        'revision' => $revision,
                    ]);
                    foreach ($notifications as $notification) {
                        $em->remove($notification);
                    }

                    $em->remove($revision);
                    $progress->advance();
                    $em->flush();
                }

                unset($revisions);
            }

            $progress->finish();
            $output->writeln(' deleting content type '.$name);
        }

        return 0;
    }
}
