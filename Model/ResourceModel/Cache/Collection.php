<?php

namespace MagicToolbox\Sirv\Model\ResourceModel\Cache;

/**
 * Cache collection
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('MagicToolbox\Sirv\Model\Cache', 'MagicToolbox\Sirv\Model\ResourceModel\Cache');
    }

    /**
     * Truncate the cache table
     *
     * @return void
     */
    public function truncate()
    {
        $this->getConnection()->truncateTable($this->getMainTable());
    }
}
