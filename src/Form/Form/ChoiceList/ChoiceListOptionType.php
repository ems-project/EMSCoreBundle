<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\ChoiceList;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class ChoiceListOptionType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('locales', ChoiceType::class, [
            'label' => t('field.locales', [], 'emsco-core'),
            'choices' => \array_flip(Locales::getNames()),
            'choice_translation_domain' => false,
            'multiple' => true,
            'required' => false,
            'attr' => ['class' => 'select2'],
        ])->add('sortOrder', ChoiceType::class, [
            'required' => false,
            'label' => t('field.sort_order', [], 'emsco-core'),
            'choices' => [
                t('key.ascending', [], 'emsco-core')->getMessage() => 'asc',
                t('key.descending', [], 'emsco-core')->getMessage() => 'desc',
            ],
            'translation_domain' => 'emsco-core',
            'help' => t('help.sort_choice_list', [], 'emsco-core'),
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $data = $event->getData();
            $locales = self::getLocales($data);
            $groups = self::getGroupNames($data);
            self::addLocalizedCollections($event->getForm(), $locales, $groups);
        });
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            $locales = self::getLocales($data);
            $data = self::removeLabelsForLocalesNotSelected($data, $locales);
            $event->setData($data);
            $groups = self::getGroupNames($data);
            self::addLocalizedCollections($event->getForm(), $locales, $groups);
        });

        $builder->addModelTransformer(new CallbackTransformer(
            static fn (mixed $data): mixed => $data,
            static fn (mixed $data): array => self::normalizeOptions($data),
        ));
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
    }

    /**
     * @param array<string, mixed>|null $data
     *
     * @return string[]
     */
    private static function getLocales(mixed $data): array
    {
        if (!\is_array($data) || !\is_array($data['locales'] ?? null)) {
            return [];
        }

        return \array_values(\array_filter($data['locales'], static fn (mixed $locale): bool => \is_string($locale) && '' !== $locale));
    }

    /**
     * @param array<string, mixed>|null $data
     *
     * @return string[]
     */
    private static function getGroupNames(mixed $data): array
    {
        if (!\is_array($data) || !\is_array($data['groups'] ?? null)) {
            return [];
        }

        $names = [];
        foreach ($data['groups'] as $group) {
            if (\is_array($group) && \is_string($group['name'] ?? null) && '' !== $group['name']) {
                $names[] = $group['name'];
            }
        }

        return $names;
    }

    /**
     * @param array<string, mixed>|null $data
     * @param string[]                  $locales
     *
     * @return array<string, mixed>|null
     */
    private static function removeLabelsForLocalesNotSelected(mixed $data, array $locales): ?array
    {
        if (!\is_array($data)) {
            return null;
        }

        $data['locales'] = \array_values($data['locales'] ?? []);
        foreach (['groups', 'choices'] as $collection) {
            if (!\is_array($data[$collection] ?? null)) {
                continue;
            }

            foreach ($data[$collection] as $key => $item) {
                if (!\is_array($item) || !\is_array($item['labels'] ?? null)) {
                    continue;
                }

                $labels = \array_intersect_key(
                    $item['labels'],
                    \array_fill_keys($locales, true),
                );
                $data[$collection][$key]['labels'] = \array_filter(
                    $labels,
                    static fn (mixed $label): bool => null !== $label && '' !== $label,
                );
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeOptions(mixed $data): array
    {
        if (!\is_array($data)) {
            return [];
        }

        $locales = self::getLocales($data);
        $normalized = self::removeLabelsForLocalesNotSelected($data, $locales) ?? [];
        $normalized['locales'] = $locales;

        return $normalized;
    }

    /**
     * @param string[]             $locales
     * @param string[]             $groups
     * @param FormInterface<mixed> $form
     */
    private static function addLocalizedCollections(FormInterface $form, array $locales, array $groups): void
    {
        $form->add('groups', CollectionType::class, [
            'entry_type' => ChoiceListOptionGroupType::class,
            'entry_options' => [
                'locales' => $locales,
                'label' => false,
            ],
            'label' => t('field.groups', [], 'emsco-core'),
            'allow_add' => true,
            'allow_delete' => true,
            'required' => false,
            'attr' => [
                'class' => 'a2lix_lib_sf_collection',
                'data-lang-add' => t('action.add_type', ['type' => 'group'], 'emsco-core'),
                'data-lang-remove' => t('action.remove_type', ['type' => 'group'], 'emsco-core'),
                'data-entry-remove-class' => 'btn btn-sm btn-danger',
            ],
        ]);
        $form->add('choices', CollectionType::class, [
            'entry_type' => ChoiceListOptionChoiceType::class,
            'entry_options' => [
                'locales' => $locales,
                'groups' => $groups,
                'label' => false,
            ],
            'label' => t('field.choices', [], 'emsco-core'),
            'allow_add' => true,
            'allow_delete' => true,
            'required' => false,
            'attr' => [
                'class' => 'a2lix_lib_sf_collection',
                'data-lang-add' => t('action.add_type', ['type' => 'choice'], 'emsco-core'),
                'data-lang-remove' => t('action.remove_type', ['type' => 'choice'], 'emsco-core'),
                'data-entry-remove-class' => 'btn btn-sm btn-danger',
            ],
        ]);
    }
}
