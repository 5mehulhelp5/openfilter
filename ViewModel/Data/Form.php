<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel\Data;

/**
 * A GET form that works without JavaScript (1.1 dropdown): its action and the hidden fields that keep the other
 * filters, sorting and paging; the control adds its own parameter.
 */
class Form
{
    /**
     * @param string $param the parameter the form's control sends
     * @param string $action URL without query
     * @param array<string, string> $fields hidden fields
     */
    public function __construct(
        private readonly string $param,
        private readonly string $action,
        private readonly array $fields
    ) {
    }

    /**
     * @return string
     */
    public function getParam(): string
    {
        return $this->param;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return array<string, string>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
