<?php

namespace MagicToolbox\Sirv\Model;

/**
 * Catalog category model
 *
 * @author    Magic Toolbox <support@magictoolbox.com>
 * @copyright Copyright (c) 2019 Magic Toolbox <support@magictoolbox.com>. All rights reserved
 * @license   http://www.magictoolbox.com/license/
 * @link      http://www.magictoolbox.com/
 */
class Category extends \Magento\Catalog\Model\Category
{
    /**
     * Is Sirv enabled flag
     *
     * @var bool
     */
    protected $isSirvEnabled = false;

    /**
     * Sync helper
     *
     * @var \MagicToolbox\Sirv\Helper\Sync
     */
    protected $syncHelper = null;

    /**
     * Absolute path to the document root directory
     *
     * @var string
     */
    protected $rootDirAbsPath = '';

    /**
     * Absolute path to the media directory
     *
     * @var string
     */
    protected $mediaDirAbsPath = '';

    /**
     * Path to category images relative to media directory
     *
     * @var string
     */
    protected $categoryMediaRelPath = '';

    /**
     * Model construct for object initialization
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $dataHelper = $objectManager->get(\MagicToolbox\Sirv\Helper\Data::class);
        $this->isSirvEnabled = $dataHelper->isSirvEnabled();

        if ($this->isSirvEnabled) {
            $this->syncHelper = $objectManager->get(\MagicToolbox\Sirv\Helper\Sync::class);
            $this->rootDirAbsPath = $this->syncHelper->getRootDirAbsPath();
            $this->mediaDirAbsPath = $this->syncHelper->getMediaDirAbsPath();
            $this->categoryMediaRelPath = $this->syncHelper->getCategoryMediaRelPath();
        }
    }

    /**
     * Get category image url
     *
     * @param string $attributeCode
     * @return bool|string
     */
    public function getImageUrl($attributeCode = 'image')
    {
        $imageUrl = null;

        if ($this->isSirvEnabled) {
            $image = $this->getData($attributeCode);

            if ($image && is_string($image)) {
                if (substr($image, 0, 1) === '/') {
                    $pathType = \MagicToolbox\Sirv\Helper\Sync::DOCUMENT_ROOT_PATH;
                    $relPath = $image;
                    $absPath = $this->rootDirAbsPath . $relPath;
                    if (strpos($absPath, $this->mediaDirAbsPath . '/') === 0) {
                        $pathType = \MagicToolbox\Sirv\Helper\Sync::MAGENTO_MEDIA_PATH;
                        $relPath = $this->syncHelper->getRelativePath($absPath, $pathType);
                    }
                } else {
                    $pathType = \MagicToolbox\Sirv\Helper\Sync::MAGENTO_MEDIA_PATH;
                    $relPath = $this->categoryMediaRelPath . '/' . $image;
                    $absPath = $this->mediaDirAbsPath . $relPath;
                }

                if ($this->syncHelper->isSynced($relPath)) {
                    $imageUrl = $this->syncHelper->getUrl($relPath);
                } elseif (!$this->syncHelper->isCached($relPath)) {
                    if ($this->syncHelper->save($absPath, $pathType)) {
                        $imageUrl = $this->syncHelper->getUrl($relPath);
                    }
                }
            }
        }

        if (!$imageUrl) {
            $imageUrl = parent::getImageUrl($attributeCode);
        }

        return $imageUrl;
    }
}
