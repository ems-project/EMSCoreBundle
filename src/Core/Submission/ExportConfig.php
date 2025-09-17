<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Submission;

use EMS\Helpers\Standard\Json;
use Symfony\Component\OptionsResolver\OptionsResolver;

final readonly class ExportConfig
{
    /**
     * @param mixed[]  $columns
     * @param string[] $emailsTo
     */
    public function __construct(
        public array $columns,
        public array $emailsTo,
        public string $subject,
        public ?string $filter = null,
        public ?string $filename = 'crm-export',
        public ?string $format = 'xlsx',
        public int $batchSize = 500,
    ) {
    }

    public static function fromJson(string $json): self
    {
        $raw = Json::decode($json);
        $resolver = new OptionsResolver();

        $resolver->setRequired(['columns', 'emails-to', 'subject']);
        $resolver->setDefaults([
            'filter' => null,
            'filename' => 'crm-export',
            'format' => 'xlsx',
            'batch-size' => 500,
        ]);

        $resolver->setAllowedTypes('columns', 'array');
        $resolver->setAllowedTypes('emails-to', 'array');
        $resolver->setAllowedTypes('subject', 'string');
        $resolver->setAllowedTypes('filter', ['null', 'string']);
        $resolver->setAllowedTypes('filename', ['null', 'string']);
        $resolver->setAllowedTypes('format', ['null', 'string']);
        $resolver->setAllowedTypes('batch-size', ['int']);

        $resolver->setNormalizer('emails-to', function ($options, $value) {
            foreach ($value as $email) {
                if (!\is_string($email) || !\filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException(\sprintf('Invalid email: %s', $email));
                }
            }

            return $value;
        });

        /** @var array{
         *     columns: array<array<string, mixed>>,
         *     emails-to: string[],
         *     subject: string,
         *     filter: string|null,
         *     filename: string|null,
         *     format: string|null,
         *     batch-size: int
         * } $options */
        $options = $resolver->resolve($raw);

        return new self(
            $options['columns'],
            $options['emails-to'],
            $options['subject'],
            $options['filter'],
            $options['filename'],
            $options['format'],
            $options['batch-size'],
        );
    }
}
