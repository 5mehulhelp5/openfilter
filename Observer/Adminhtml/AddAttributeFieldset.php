<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Observer\Adminhtml;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Config\Source\DisplayMode;
use Iranimij\OpenFilter\Model\Config\Validator;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Data\Form;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;

/**
 * Adds an "OpenFilter" fieldset to the Storefront Properties tab of a product attribute that is a filter:
 * the common settings, the URL name, and a link to the full filter form.
 */
class AddAttributeFieldset implements ObserverInterface
{
    /**
     * @param FilterConfigRepositoryInterface $repository
     * @param SlugRepositoryInterface $slugs
     * @param FilterConfigStorage $storage
     * @param FilterConfigProvider $provider
     * @param Validator $validator
     * @param DisplayMode $displayModes
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly SlugRepositoryInterface $slugs,
        private readonly FilterConfigStorage $storage,
        private readonly FilterConfigProvider $provider,
        private readonly Validator $validator,
        private readonly DisplayMode $displayModes,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $form = $observer->getEvent()->getData('form');
        $attribute = $observer->getEvent()->getData('attribute');
        if (!$form instanceof Form || !$attribute instanceof AbstractAttribute || !$attribute->getId()) {
            return;
        }
        $attributeId = (int) $attribute->getId();
        try {
            $config = $this->repository->getByAttributeId($attributeId);
        } catch (NoSuchEntityException) {
            return;
        }
        $labels = $this->displayModes->labels();
        $modes = [];
        foreach ($this->validator->allowedModes($config, $this->storage->optionCount($attributeId)) as $mode) {
            $modes[] = ['value' => $mode, 'label' => $labels[$mode]];
        }
        $yesNo = [['value' => '1', 'label' => __('Yes')], ['value' => '0', 'label' => __('No')]];

        $values = [
            'openfilter_display_mode' => $config->getDisplayMode(),
            'openfilter_multiselect' => $config->isMultiselect() ? '1' : '0',
            'openfilter_visible_options' => (string) $config->getVisibleOptions(),
            'openfilter_search_threshold' => (string) $config->getSearchThreshold(),
            'openfilter_url_name' => $this->slugs->getAttributeSlug($attributeId, 0),
            'openfilter_seo_indexable' => $config->isSeoIndexable() ? '1' : '0',
        ];
        // Core's tab calls $form->setValues($attribute->getData()) after this event: the values must be there too.
        $attribute->addData($values);

        $fieldset = $form->addFieldset('openfilter_fieldset', ['legend' => __('OpenFilter')]);
        $fieldset->addField('openfilter_display_mode', 'select', [
            'name' => 'openfilter[display_mode]',
            'label' => __('Looks like'),
            'title' => __('Looks like'),
            'values' => $modes,
            'value' => $values['openfilter_display_mode'],
        ]);
        $fieldset->addField('openfilter_multiselect', 'select', [
            'name' => 'openfilter[multiselect]',
            'label' => __('Shoppers can pick several values'),
            'title' => __('Shoppers can pick several values'),
            'values' => $yesNo,
            'value' => $values['openfilter_multiselect'],
        ]);
        $fieldset->addField('openfilter_visible_options', 'text', [
            'name' => 'openfilter[visible_options]',
            'label' => __('Options before "Show more"'),
            'title' => __('Options before "Show more"'),
            'class' => 'validate-digits',
            'note' => __('0 = show all options.'),
            'value' => $values['openfilter_visible_options'],
        ]);
        $fieldset->addField('openfilter_search_threshold', 'text', [
            'name' => 'openfilter[search_threshold]',
            'label' => __('Search box from this many options'),
            'title' => __('Search box from this many options'),
            'class' => 'validate-digits',
            'note' => __('Long lists get a search box at the top. 0 = never.'),
            'value' => $values['openfilter_search_threshold'],
        ]);
        $fieldset->addField('openfilter_url_name', 'text', [
            'name' => 'openfilter[url_name]',
            'label' => __('URL name'),
            'title' => __('URL name'),
            'note' => __('The name of this filter in URLs, for example ?color=red. Changing it changes published URLs.'),
            'value' => $values['openfilter_url_name'],
        ]);
        $fieldset->addField('openfilter_seo_indexable', 'select', [
            'name' => 'openfilter[seo_indexable]',
            'label' => __('Let Google index pages filtered by this'),
            'title' => __('Let Google index pages filtered by this'),
            'values' => $yesNo,
            'value' => $values['openfilter_seo_indexable'],
        ]);
        $fieldset->addField('openfilter_more', 'note', [
            'text' => sprintf(
                '<a href="%s">%s</a>',
                $this->url->getUrl('openfilter/filter/edit', ['code' => $this->provider->keyOf($config)]),
                __('More settings: labels per store view, option URL names, position')
            ),
        ]);
    }
}
