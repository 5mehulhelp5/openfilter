<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Iranimij\OpenFilter\Model\Doctor\Check;
use Iranimij\OpenFilter\Model\Doctor\FilterDiagnosis;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * "Why is this filter not showing?" for one store view and, optionally, one category page (JSON).
 */
class Diagnose extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /**
     * @param Action\Context $context
     * @param JsonFactory $jsonFactory
     * @param FilterDiagnosis $diagnosis
     */
    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FilterDiagnosis $diagnosis
    ) {
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $request = $this->getRequest();
        $category = (int) $request->getParam('category');
        $checks = $this->diagnosis->diagnose(
            (string) $request->getParam('code'),
            max(1, (int) $request->getParam('store')),
            $category > 0 ? $category : null
        );

        return $this->jsonFactory->create()->setData([
            'checks' => array_map(static fn (Check $check): array => $check->toArray(), $checks),
        ]);
    }
}
