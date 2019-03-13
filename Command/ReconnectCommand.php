<?php

// src/EMS/CoreBundle/Command/GreetCommand.php
namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use EMS\CoreBundle\Repository\RevisionRepository;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CoreBundle\Repository\NotificationRepository;
use Symfony\Component\Console\Helper\ProgressBar;

class ReconnectCommand extends ContainerAwareCommand
{

    /**@var ContentTypeService*/
    private $contentTypeService;

    /**@var Client*/
    protected $client;

    /**@var EnvironmentService*/
    protected $environmentService;
    
    public function __construct(Client $client, ContentTypeService $contentTypeService, EnvironmentService $environmentService)
    {
        $this->client = $client;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        parent::__construct();
    }
    
    protected function configure()
    {
        $this
            ->setName('ems:environment:reconnect')
            ->setDescription('Reconnect single type indexes for an environment alias')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Environment name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environmentName = $input->getArgument('name');
        $environment = $this->environmentService->getByName($environmentName);

        if(!$environment){
            $output->writeln('Environment not found');
            return -1;
        }

        $mappings = $this->client->indices()->getMapping([
            'index' => $environment->getAlias(),
        ]);

        foreach ($mappings as $index => $indexMapping){
            foreach ($indexMapping['mappings'] as $type => $typeMapping){
                if(isset($typeMapping['_meta']['generator']) && $typeMapping['_meta']['generator'] === 'elasticms' && isset($typeMapping['_meta']['content_type'])){
                    $contentType = $this->contentTypeService->getByName($typeMapping['_meta']['content_type']);
                    if($contentType){
                        $this->contentTypeService->setSingleTypeIndex($environment, $contentType, $index);
                        $output->writeln('Index found for content type: '.$typeMapping['_meta']['content_type']);
                    }
                    else {
                        $output->writeln('Content type not found: '.$typeMapping['_meta']['content_type']);
                    }
                }
                else {
                    $output->writeln('This mapping was not defined be elasticms: '.$type);
                }
            }
        }
    }
    


}