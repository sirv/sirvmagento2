<?php

namespace Sirv\Magento2\Observer;

/**
 * Observer that processes the responses
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2021 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class ResponseProcessing implements \Magento\Framework\Event\ObserverInterface
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
     * @var \Sirv\Magento2\Helper\Sync
     */
    protected $syncHelper = null;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager = null;

    /**
     * Sirv host
     *
     * @var string
     */
    protected $sirvHost = '';

    /**
     * URL prefix
     *
     * @var string
     */
    protected $urlPrefix = '';

    /**
     * Whether auto fetch is enabled
     *
     * @var bool
     */
    protected $isAutoFetchEnabled = false;

    /**
     * Whether lazy load is enabled
     *
     * @var bool
     */
    protected $isLazyLoadEnabled = false;

    /**
     * Is Sirv Media Viewer used
     *
     * @var bool
     */
    protected $isSirvMediaViewerUsed = false;

    /**
     * sirv.js components
     *
     * @return string
     */
    protected $sirvJsComponents = '';

    /**
     * Constructor
     *
     * @param \Sirv\Magento2\Helper\Data $dataHelper
     * @param \Sirv\Magento2\Helper\Sync $syncHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @return void
     */
    public function __construct(
        \Sirv\Magento2\Helper\Data $dataHelper,
        \Sirv\Magento2\Helper\Sync $syncHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->isSirvEnabled = $dataHelper->isSirvEnabled();
        if ($this->isSirvEnabled) {
            $this->syncHelper = $syncHelper;
            $this->storeManager = $storeManager;

            $bucket = $dataHelper->getConfig('bucket') ?: $dataHelper->getConfig('account');
            $this->sirvHost = $bucket . '.sirv.com';
            $cdn = $dataHelper->getConfig('cdn_url');
            $cdn = is_string($cdn) ? trim($cdn) : '';
            if (!empty($cdn)) {
                $this->sirvHost = $cdn;
            }
            $this->urlPrefix = $dataHelper->getConfig('url_prefix');
            $this->urlPrefix = is_string($this->urlPrefix) ? trim($this->urlPrefix) : '';

            if ($this->urlPrefix) {
                //$this->urlPrefix = preg_replace('#^(?:https?\:)?//#', '', $this->urlPrefix);
                $autoFetch = $dataHelper->getConfig('auto_fetch');
                $this->isAutoFetchEnabled = $autoFetch == 'custom' || $autoFetch == 'all';
                $this->isLazyLoadEnabled = $dataHelper->getConfig('lazy_load') == 'true';
            }

            $this->isSirvMediaViewerUsed = $dataHelper->useSirvMediaViewer();
            $this->sirvJsComponents = $dataHelper->getConfig('js_components');
        }
    }

    /**
     * Execute method
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->isSirvEnabled) {
            /** @var \Magento\Framework\App\Response\Http\Interceptor $response */
            $response = $observer->getResponse();

            if ($response) {
                $html = $response->getBody();
                if ($html) {

                    $this->addHeadContent($html);

                    $this->processImageUrls($html);

                    if ($this->isAutoFetchEnabled) {
                        $this->processResourceUrls($html);
                    }

                    if ($this->isLazyLoadEnabled) {
                        $this->processImageTags($html);
                    }

                    $response->setBody($html);
                }
            }

            //NOTE: fetch files with API
            $this->syncHelper->doFetch();
        }
    }

    /**
     * Add HEAD content
     *
     * @param string $html
     * @return void
     */
    protected function addHeadContent(&$html)
    {
        $sirvUrl = $this->syncHelper->getBaseUrl();
        $replace = "<link rel=\"preconnect\" href=\"" . $sirvUrl . "\" crossorigin/>\n";
        $replace .= "<link rel=\"dns-prefetch\" href=\"" . $sirvUrl . "\"/>\n";
        $html = preg_replace(
            '#<link[^>]++>#',
            $replace . '$0',
            $html,
            1
        );

        if ($this->isSirvMediaViewerUsed || $this->isLazyLoadEnabled) {
            $replace = "<link rel=\"preconnect\" href=\"https://scripts.sirv.com\" crossorigin/>\n";
            $replace .= "<link rel=\"dns-prefetch\" href=\"https://scripts.sirv.com\"/>\n";
            $html = preg_replace(
                '#<link[^>]++>#',
                $replace . '$0',
                $html,
                1
            );

            $sirvJsComponents = explode(',', $this->sirvJsComponents);
            if (count($sirvJsComponents) == 4) {
                $replace = "<script type=\"text/javascript\" src=\"https://scripts.sirv.com/sirvjs/v3/sirv.full.js\"></script>\n";
            } else {
                $replace = "<script type=\"text/javascript\" src=\"https://scripts.sirv.com/sirvjs/v3/sirv.js\" data-components=\"" . $this->sirvJsComponents . "\"></script>";
            }
            $html = preg_replace(
                '#<script[^>]++>#',
                $replace . '$0',
                $html,
                1
            );
        }
    }

    /**
     * Process image URLs for fetching the rest of the images
     *
     * @param string $html
     * @return void
     */
    protected function processImageUrls(&$html)
    {
        $fetchList = $this->syncHelper->getImagesFetchList();
        $fetchList = array_flip($fetchList);
        $pathType = \Sirv\Magento2\Helper\Sync::MAGENTO_MEDIA_PATH;

        $mediaDirAbsPath = $this->syncHelper->getMediaDirAbsPath();
        //NOTE: /abs_path_to_www_root/path_to_magento/pub/media/

        $store = $this->storeManager->getStore();
        $baseMediaUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA, false);
        //NOTE: protocol://host/path_to_magento/pub/media/

        $baseMediaPath = parse_url($baseMediaUrl, PHP_URL_PATH) ?: '/';
        $baseMediaPath = rtrim($baseMediaPath, '/') . '/';
        //NOTE: /path_to_magento/pub/media/

        $baseMediaUrl = preg_replace('#^(?:https?\:)?//#', '', $baseMediaUrl);
        $baseMediaUrl = rtrim($baseMediaUrl, '/') . '/';
        //NOTE: host/path_to_magento/pub/media/

        $baseMediaDir = $store->getBaseMediaDir();
        $baseMediaDir = trim($baseMediaDir, '/') . '/';
        //NOTE: pub/media/

        $baseMediaUrlPattern =
            '(?:' .
            '(?:https?\:)?//' . preg_quote($baseMediaUrl, '#') .
            '|' .
            preg_quote($baseMediaPath, '#') .
            '|' .
            preg_quote($baseMediaDir, '#') .
            ')';
        $searchPattern = '#("|\')' . $baseMediaUrlPattern . '[^"\'\?]*+#';
        $extensionPattern = '#\.(?:jpe?g|png|gif|webp|tiff?|bmp)$#i';

        $matches = [];
        if (preg_match_all($searchPattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $key => $match) {
                if (!preg_match($extensionPattern, $match[0])) {
                    continue;
                }

                $relPath = preg_replace(
                    '#^(?:"|\')' . $baseMediaUrlPattern . '#',
                    '/',
                    $match[0]
                );
                if (isset($fetchList[$relPath])) {
                    continue;
                }

                if (preg_match('#/cache/#', $relPath)) {
                    //NOTE: to skip cached images
                    continue;
                    $origPath = preg_replace(
                        //NOTE: does not work for cached images that were not placed in "magento" way
                        //'#/cache/[^/]++(/[^/]/[^/]/[^/]++)$#',
                        '#/cache/[^/]++(/.++)$#',
                        '\1',
                        $relPath
                    );
                    if (isset($fetchList[$origPath])) {
                        continue;
                    }
                    $syncStatus = $this->syncHelper->getSyncStatus($origPath);
                    if ($syncStatus == \Sirv\Magento2\Helper\Sync::IS_NEW ||
                        $syncStatus == \Sirv\Magento2\Helper\Sync::IS_PROCESSING
                    ) {
                        continue;
                    }
                }

                $doReplace = false;
                $absPath = $mediaDirAbsPath . $relPath;
                if ($this->syncHelper->isNotExcluded($absPath)) {
                    if ($this->syncHelper->isSynced($relPath)) {
                        $doReplace = true;
                    } elseif (!$this->syncHelper->isCached($relPath)) {
                        if ($this->syncHelper->save($absPath, $pathType)) {
                            $doReplace = true;
                        }
                    }
                }

                if ($doReplace) {
                    $imageUrl = $this->syncHelper->getUrl($relPath);
                    $html = str_replace($match[0], $match[1] . $imageUrl, $html);
                    $fetchList[$relPath] = true;
                }
            }

        }
    }

    /**
     * Process resource URLs for auto fetching
     *
     * @param string $html
     * @return void
     */
    protected function processResourceUrls(&$html)
    {
        $store = $this->storeManager->getStore();

        $baseStaticUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_STATIC, false);
        //NOTE: protocol://host/path_to_magento/static/version{id}/

        $baseStaticUrl = preg_replace('#^(?:https?\:)?//#', '', $baseStaticUrl);
        $baseStaticUrl = rtrim($baseStaticUrl, '/') . '/';
        //NOTE: host/path_to_magento/static/version{id}/

        $baseMediaUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA, false);
        //NOTE: protocol://host/path_to_magento/pub/media/

        $baseMediaPath = parse_url($baseMediaUrl, PHP_URL_PATH) ?: '/';
        $baseMediaPath = rtrim($baseMediaPath, '/') . '/';
        //NOTE: /path_to_magento/pub/media/

        $baseMediaUrl = preg_replace('#^(?:https?\:)?//#', '', $baseMediaUrl);
        $baseMediaUrl = rtrim($baseMediaUrl, '/') . '/';
        //NOTE: host/path_to_magento/pub/media/

        $baseMediaDir = $store->getBaseMediaDir();
        $baseMediaDir = trim($baseMediaDir, '/') . '/';
        //NOTE: pub/media/

        $searchPattern =
            '(?:' .
            '(?:https?\:)?//' . preg_quote($baseMediaUrl, '#') .
            '|' .
            preg_quote($baseMediaPath, '#') .
            '|' .
            preg_quote($baseMediaDir, '#') .
            ')';
        $extensionPattern = '#\.(?:css|js|ico|woff2|svg)(\?[^\?]*+)?$#i';

        $matches = [];
        if (preg_match_all('#("|\')' . $searchPattern . '[^"\'\?]*+#', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $key => $match) {
                if (!preg_match($extensionPattern, $match[0])) {
                    continue;
                }

                $relPath = preg_replace(
                    '#^(?:"|\')' . $searchPattern . '#',
                    '',
                    $match[0]
                );

                if (preg_match('#/cache/#', $relPath)) {
                    //NOTE: skip cached images
                    continue;
                }

                if ($this->syncHelper->isNotExcluded($baseMediaPath . $relPath)) {
                    $html = str_replace(
                        $match[0],
                        $match[1] . 'https://' . $this->sirvHost . $baseMediaPath . $relPath,
                        $html
                    );
                }
            }
        }

        $searchPattern = '(?:https?\:)?//' . preg_quote($baseStaticUrl, '#');
        $matches = [];
        if (preg_match_all('#("|\')' . $searchPattern . '[^"\'\?]*+#', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $key => $match) {
                $relPath = preg_replace(
                    '#^(?:"|\')(?:https?\:)?//[^/]++/#',
                    '/',
                    $match[0]
                );

                if ($this->syncHelper->isNotExcluded($relPath)) {
                    $html = str_replace(
                        $match[0],
                        $match[1] . 'https://' . $this->sirvHost . $relPath,
                        $html
                    );
                }
            }
        }
    }

    /**
     * Prepare IMG tags for lazy load
     *
     * @param string $html
     * @return void
     */
    protected function processImageTags(&$html)
    {
        $backupCode = [];
        $regExp = '<(div|a)\b[^>]*?' .
            '(?:' .
                '\bclass\s*+=\s*+\\\\?"' .
                '[^"]*?' .
                '(?<=\s|")(?:Sirv|Magic(?:Zoom(?:Plus)?|Thumb|360|Scroll|Slideshow))(?=\s|\\\\?")' .
                '[^"]*+"' .
                '|' .
                '\bdata-(?:zoom|thumb)-id\s*+=\s*+\\\\?"' .
            ')' .
            '[^>]*+>' .
            '(' .
                '(?:' .
                    '[^<]++' .
                    '|' .
                    '<(?!(?:\\\\?/)?\1\b|!--)' .
                    '|' .
                    '<!--.*?-->' .
                    '|' .
                    '<\1\b[^>]*+>' .
                        '(?2)' .
                    '<\\\\?/\1\s*+>' .
                ')*+' .
            ')' .
            '<\\\\?/\1\s*+>';

        $matches = [];
        if (preg_match_all('#' . $regExp . '#is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $i => $match) {
                $count = 0;
                $html = str_replace($match[0], 'SIRV_PLACEHOLDER_' . $i . '_MATCH', $html, $count);
                if ($count) {
                    $backupCode[$i] = $match[0];
                }
            }
        }

        $matches = [];
        if (preg_match_all('#<img\s[^>]++>#', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $key => $tagMatches) {
                $imgTag = $tagMatches[0];
                //NOTE: backslash for escaping quotes (if need it) or empty
                $bs = preg_match('#\\\\(?:"|\')#', $imgTag) ? '\\' : '';

                $srcPattern = '#\ssrc\s*+=\s*+' . $bs . $bs . '("|\')([^"\']++)\1#';
                $srcMatches = [];
                if (!preg_match($srcPattern, $imgTag, $srcMatches)) {
                    continue;
                }

                $classPattern = '#\sclass\s*+=\s*+' . $bs . $bs . '("|\')([^"\']++)\1#';
                $classMatches = [];
                if (preg_match($classPattern, $imgTag, $classMatches)) {
                    if (preg_match('#(?:^|\s)Sirv(?:\s|' . $bs . $bs . '$)#', $classMatches[2])) {
                        continue;
                    } else {
                        $imgTag = preg_replace(
                            $classPattern,
                            ' class=' . $bs . $classMatches[1] . rtrim($classMatches[2], $bs) . ' Sirv' . $bs . $classMatches[1],
                            $imgTag
                        );
                    }
                } else {
                    $imgTag = preg_replace('#^<img#', '<img class=' . $bs . '"Sirv' . $bs . '"', $imgTag);
                }

                $srcHost = parse_url($srcMatches[2], PHP_URL_HOST) ?: '';
                if (strpos($srcHost, $this->sirvHost) === false) {
                    $imgTag = preg_replace('#^<img#', '<img data-type=' . $bs . '"static' . $bs . '"', $imgTag);
                }

                $imgTag = preg_replace('#\ssrc\s*+=\s*+#', ' data-src=', $imgTag);

                $html = str_replace($tagMatches[0], $imgTag, $html);
            }
        }

        if (!empty($backupCode)) {
            foreach ($backupCode as $i => $code) {
                $html = str_replace('SIRV_PLACEHOLDER_' . $i . '_MATCH', $code, $html);
            }
        }
    }
}
