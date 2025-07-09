<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\Submission;

use EMS\CoreBundle\Core\Submission\ExportConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

final class ExportConfigTest extends TestCase
{
    #[DataProvider('provideFormalConfigurations')]
    public function testFromJsonWithValidData(array $input, array $expected): void
    {
        $json = \json_encode($input, JSON_THROW_ON_ERROR);
        $config = ExportConfig::fromJson($json);

        $this->assertInstanceOf(ExportConfig::class, $config);
        $this->assertEquals($expected['subject'], $config->subject);
        $this->assertEquals($expected['emailsTo'], $config->emailsTo);
        $this->assertEquals($expected['filename'], $config->filename);
        $this->assertEquals($expected['format'], $config->format);
        $this->assertEquals($expected['filter'], $config->filter);
        $this->assertSame(\count($expected['columns']), \count($config->columns));

        foreach ($expected['columns'] as $index => $expectedColumn) {
            $this->assertEquals($expectedColumn['name'], $config->columns[$index]['name']);
            $this->assertEquals($expectedColumn['field'], $config->columns[$index]['field']);
        }
    }

    public static function provideFormalConfigurations(): array
    {
        return [
            'default_configuration' => [
                'input' => [
                    'columns' => [
                        ['name' => 'Email', 'field' => '[data][email]'],
                    ],
                    'emails-to' => ['test@example.com'],
                    'subject' => 'Test Export',
                ],
                'expected' => [
                    'subject' => 'Test Export',
                    'emailsTo' => ['test@example.com'],
                    'filename' => 'crm-export',
                    'format' => 'xlsx',
                    'filter' => null,
                    'columns' => [
                        ['name' => 'Email', 'field' => '[data][email]'],
                    ],
                ],
            ],
            'with_custom_filter' => [
                'input' => [
                    'columns' => [['name' => 'Email', 'field' => '[data][email]']],
                    'emails-to' => ['test@example.com'],
                    'subject' => 'Test Export',
                    'filter' => 'status:active', // Changed to string format
                ],
                'expected' => [
                    'subject' => 'Test Export',
                    'emailsTo' => ['test@example.com'],
                    'filename' => 'crm-export',
                    'format' => 'xlsx',
                    'filter' => 'status:active',
                    'columns' => [['name' => 'Email', 'field' => '[data][email]']],
                ],
            ],
            'multiple_columns' => [
                'input' => [
                    'columns' => [
                        ['name' => 'Email', 'field' => '[data][email]'],
                        ['name' => 'Name', 'field' => '[data][name]'],
                        ['name' => 'Phone', 'field' => '[data][phone]'],
                    ],
                    'emails-to' => ['test@example.com'],
                    'subject' => 'Test Export',
                ],
                'expected' => [
                    'subject' => 'Test Export',
                    'emailsTo' => ['test@example.com'],
                    'filename' => 'crm-export',
                    'format' => 'xlsx',
                    'filter' => null,
                    'columns' => [
                        ['name' => 'Email', 'field' => '[data][email]'],
                        ['name' => 'Name', 'field' => '[data][name]'],
                        ['name' => 'Phone', 'field' => '[data][phone]'],
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testInvalidConfigurationThrowsException(array $input, string $expectedException): void
    {
        $json = \json_encode($input, JSON_THROW_ON_ERROR);

        $this->expectException($expectedException);
        ExportConfig::fromJson($json);
    }

    public static function provideInvalidConfigurations(): array
    {
        return [
            'invalid_email' => [
                'input' => [
                    'columns' => [],
                    'emails-to' => ['invalid-email'],
                    'subject' => 'Test',
                ],
                'expectedException' => \InvalidArgumentException::class,
            ],
            'missing_required_field' => [
                'input' => [
                    'columns' => [],
                    'subject' => 'Test',
                ],
                'expectedException' => MissingOptionsException::class,
            ],
            'invalid_filter_type' => [
                'input' => [
                    'columns' => [['name' => 'Test', 'field' => '[data][test]']],
                    'emails-to' => ['test@example.com'],
                    'subject' => 'Test',
                    'filter' => ['invalid' => 'filter'],
                ],
                'expectedException' => InvalidOptionsException::class,
            ],
        ];
    }
}
