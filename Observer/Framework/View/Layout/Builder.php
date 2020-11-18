<?php
/**
 * @author   : Daan van den Bergh
 * @url      : https://daan.dev
 * @package  : Dan0sz/ResourceHints
 * @copyright: (c) 2019 Daan van den Bergh
 */

namespace Dan0sz\ResourceHints\Observer\Framework\View\Layout;

use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;

class Builder implements ObserverInterface
{
    const WEB_CONFIG_RESOURCE_HINTS = 'web/resource_hints/config';
    const LOCALE_CODE = 'general/locale/code';

    /** @var ScopeConfig $scopeConfig */
    private $scopeConfig;

    /** @var PageConfig $pageConfig */
    private $pageConfig;

    /**
     * Builder constructor.
     *
     * @param ScopeConfig $scopeConfig
     * @param PageConfig  $pageConfig
     */
    public function __construct(
        Filesystem $filesystem,
        DirectoryList $directoryList,
        ScopeConfig $scopeConfig,
        PageConfig $pageConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
        $this->scopeConfig = $scopeConfig;
        $this->pageConfig  = $pageConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $configArray = $this->scopeConfig->getValue(
            self::WEB_CONFIG_RESOURCE_HINTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!$configArray) {
            return $this;
        }

        $resourceHints = $this->sort((array) json_decode($configArray));
        $this->mediaRead = $this->filesystem->getDirectoryRead(DirectoryList::PUB);

        $lang = $this->scopeConfig->getValue(
            self::LOCALE_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $version = $this->mediaRead->readFile("static/deployed_version.txt");

        foreach ($resourceHints as $resource) {
            $attributes = [];
            $attributes['rel'] = $resource->type;
            $resource->resource = str_replace('VERSION', 'version' . $version, $resource->resource);
            $resource->resource = str_replace('LANG', $lang, $resource->resource);

            if ($resource->type == 'preload') {
                $attributes['as'] = $resource->preload_as;
            }
            if ($resource->preload_as === "font" || $resource->preload_as === "style") {
                $attributes['crossorigin'] = 'crossorigin';
            }

            $this->pageConfig->addRemotePageAsset(
                $resource->resource,
                'link_rel',
                [
                    'attributes' => $attributes
                ]
            );
        }

        return $this;
    }

    private function sort(array $resourceHints)
    {
        usort($resourceHints, function ($first, $second) {
            return $first->sort_order <=> $second->sort_order;
        });

        return $resourceHints;
    }
}
