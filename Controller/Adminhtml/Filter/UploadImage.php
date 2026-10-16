<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Iranimij\OpenFilter\Model\Admin\OptionImageForm;
use Iranimij\OpenFilter\Model\Admin\OptionImageUploader;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;

/**
 * Uploads an option image (1.2) to the temporary folder; the filter form's Save moves it into place.
 */
class UploadImage extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /**
     * @param Action\Context $context
     * @param OptionImageUploader $uploader
     */
    public function __construct(
        Action\Context $context,
        private readonly OptionImageUploader $uploader
    ) {
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        try {
            $result = $this->uploader->saveFileToTmpDir(OptionImageForm::FILE_FIELD);
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }
        /** @var Json $json */
        $json = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        return $json->setData($result);
    }
}
