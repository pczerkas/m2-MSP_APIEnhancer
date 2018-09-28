<?php
/**
 * MageSpecialist
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@magespecialist.it so we can send you a copy immediately.
 *
 * @category   MSP
 * @package    MSP_APIEnhancer
 * @copyright  Copyright (c) 2017 Skeeller srl (http://www.magespecialist.it)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MSP\APIEnhancer\Model;

use Magento\Framework\App\RequestInterface;
use Magento\PageCache\Model\Cache\Type as FullPageCacheType;
use Magento\Framework\App\Cache\StateInterface;
use Magento\PageCache\Model\Config as PageCacheConfig;
use MSP\APIEnhancer\Api\EnhancerManagementInterface;

class EnhancerManagement implements EnhancerManagementInterface
{
    const BASE_PATH = '/rest';

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var array
     */
    private $paths;

    /**
     * @var PageCacheConfig
     */
    private $pageCacheConfig;

    /**
     * @var StateInterface
     */
    private $state;

    /**
     * @var \Magento\Framework\App\Config\ReinitableConfigInterface
     */
    private $config;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        RequestInterface $request,
        PageCacheConfig $pageCacheConfig,
        StateInterface $state,
        \Magento\Framework\App\Config\ReinitableConfigInterface $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $paths = []
    ) {
        $this->request = $request;
        $this->paths = $paths;
        $this->pageCacheConfig = $pageCacheConfig;
        $this->state = $state;
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    public function getConfig($path)
    {
        $store = $this->storeManager->getStore();

        $data = $this->config->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store->getCode());
        if ($data === null) {
            $data = $this->config->getValue($path);
        }
        return $data === false ? null : $data;
    }

    /**
     * Return true if FPC is handled by Varnish
     * @return bool
     */
    public function useVarnish()
    {
        return
            $this->state->isEnabled(FullPageCacheType::TYPE_IDENTIFIER) &&
            $this->pageCacheConfig->getType() == PageCacheConfig::VARNISH;
    }

    public function getBasePath()
    {
        $basePath = static::BASE_PATH;

        if (!$this->getConfig(\Magento\Store\Model\Store::XML_PATH_USE_REWRITES)) {
            $indexFileName = basename($_SERVER['SCRIPT_FILENAME']);

            $basePath = '/' . $indexFileName . $basePath;
        }

        return $basePath;
    }

    /**
     * Return true if can cache this request
     * @return bool
     */
    public function canCacheRequest()
    {
        // Make sure it is a rest-API call (at this level we cannot rely on detected area)
        $uriPath = $this->request->getRequestUri();

		$basePath = $this->getBasePath();

        if (strpos($uriPath, $basePath . '/') !== 0) {
            return false;
        }

        // Not GET calls should not be cached
        if (strtoupper($this->request->getMethod()) != 'GET') {
            return false;
        }

        foreach ($this->paths as $code => $path) {
            if (preg_match('|' . $path . '|i', $uriPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return true if cache is enabled
     * @return bool
     */
    public function isCacheEnabled()
    {
        return $this->state->isEnabled(CacheType::TYPE_IDENTIFIER);
    }
}
