<?php

namespace MagicToolbox\Sirv\Block\Adminhtml\Settings\Edit\Form\Element;

/**
 * Form email element
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Email extends \Magento\Framework\Data\Form\Element\AbstractElement
{
    /**
     * Constructor
     *
     * @param \Magento\Framework\Data\Form\Element\Factory $factoryElement
     * @param \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection
     * @param \Magento\Framework\Escaper $escaper
     * @param array $data
     * @return void
     */
    public function __construct(
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
        $this->setType('text');
        $this->setExtType('textfield');
    }

    /**
     * Get the HTML
     *
     * @return mixed
     */
    public function getHtml()
    {
        $this->addClass('input-text admin__control-text');
        return parent::getHtml();
    }

    /**
     * Get the attributes
     *
     * @return string[]
     */
    public function getHtmlAttributes()
    {
        $htmlAttributes = parent::getHtmlAttributes();
        $htmlAttributes[] = 'autofocus';
        return $htmlAttributes;
    }

    /**
     * Serialize the element attributes
     *
     * @param string[] $attributes
     * @param string $valueSeparator
     * @param string $fieldSeparator
     * @param string $quote
     * @return string
     */
    public function serialize($attributes = [], $valueSeparator = '=', $fieldSeparator = ' ', $quote = '"')
    {
        $autofocus = false;

        if (in_array('autofocus', $attributes) && array_key_exists('autofocus', $this->_data)) {
            $autofocus = true;
            unset($this->_data['autofocus']);
        }

        $serializedAttributes = parent::serialize($attributes, $valueSeparator, $fieldSeparator, $quote);

        if ($autofocus) {
            $serializedAttributes = 'autofocus ' . $serializedAttributes;
        }

        return $serializedAttributes;
    }
}