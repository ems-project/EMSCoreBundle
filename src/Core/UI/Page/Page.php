<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI\Page;

use Symfony\Component\Form\FormView;
use Symfony\Component\Translation\TranslatableMessage;

readonly class Page
{
    /**
     * @param array{
     *     title: TranslatableMessage,
     *     subTitle?: TranslatableMessage,
     *     icon?: string,
     *     breadcrumb?: Navigation,
     *     datatable?: array{ form: FormView, icon?: string, title?: TranslatableMessage, table_id?: string },
     *     datatables?: array<int, array{ form: FormView, icon?: string, title?: TranslatableMessage, table_id?: string }>,
     *     form?: FormView,
     *     notice?: TranslatableMessage
     * } $context
     */
    public function __construct(
        public array $context,
        public string $template = 'page/page.html.twig'
    ) {
    }
}
