<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Webhook;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\WebhookService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::WEBHOOK_DISPATCH,
    description: 'Dispatch custom webhook notifications',
    hidden: false
)]
class DispatchWebhookCommand extends AbstractCommand
{
    public const ARGUMENT_EVENT_NAME = 'event-name';
    public const ARGUMENT_DATA = 'data';
    private string $eventName;
    /**
     * @var mixed[]
     */
    private array $data;

    public function __construct(private readonly WebhookService $webhookService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(self::ARGUMENT_EVENT_NAME, InputArgument::REQUIRED, 'Name of the webhook event');
        $this->addArgument(self::ARGUMENT_DATA, InputArgument::OPTIONAL, 'Data (JSON format)', '{}');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->eventName = $this->getArgumentString(self::ARGUMENT_EVENT_NAME);
        $this->data = Json::decode($this->getArgumentString(self::ARGUMENT_DATA));
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title(\sprintf('Dispatch %s webhooks', $this->eventName));
        $counter = $this->webhookService->dispatch($this->eventName, $this->data);
        $this->io->success(\sprintf('The webhook has been dispatched to %d subscribers', $counter));

        return self::EXECUTE_SUCCESS;
    }
}
