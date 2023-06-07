<?php
/**
 * Copyright (C) 2018-2019 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2018-2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

/**
 * Class ECBExchange
 */
class ECBExchange extends CurrencyRateModule
{
    const SERVICE_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    const SERVICECACHE_FILE = _PS_CACHE_DIR_.'/ecbexchange.xml';
    const SERVICECACHE_MAX_AGE = 3600; // seconds

    /**
     * ECBExchange constructor.
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'ecbexchange';
        $this->tab = 'administration';
        $this->version = '1.1.0';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('ECB Exchange Rate Services');
        $this->description = $this->l('Fetches currency exchange rates from the European Central Bank.');
        $this->tb_versions_compliancy = '> 1.0.0';
        $this->tb_min_version = '1.0.0';
    }

    /**
     * @return bool
     * @throws PrestaShopException
     */
    public function install()
    {
        return (
            parent::install() &&
            $this->registerHook('actionRetrieveCurrencyRates')
        );
    }

    /**
     * @param array $params
     *
     * @return array see hookActionRetrieveCurrencyRates() in classes/module/CurrencyRateModule.php in core.
     *
     * @see hookActionRetrieveCurrencyRates() in classes/module/CurrencyRateModule.php in core.
     */
    public function hookActionRetrieveCurrencyRates($params)
    {
        $baseCurrency = $params['baseCurrency'];
        $currencies = $params['currencies'];
        $serviceExchangeRates = $this->getServiceExchangeRates();

        $exchangeRates = [];
        if (array_key_exists($baseCurrency, $serviceExchangeRates)) {
            $divisor = $serviceExchangeRates[$baseCurrency];
            foreach ($currencies as $currency) {
                if (array_key_exists($currency, $serviceExchangeRates)) {
                    $exchangeRates[$currency] = (float)$serviceExchangeRates[$currency] / $divisor;
                } else {
                    $exchangeRates[$currency] = false;
                }
            }
        } else {
            $this->log("ECB Exchange does not provide rate for your base currency");
            foreach ($currencies as $currency) {
                $exchangeRates[$currency] = false;
            }
        }

        return $exchangeRates;
    }

    /**
     * @return array An array with uppercase currency codes (ISO 4217).
     */
    public function getSupportedCurrencies()
    {
        return array_keys($this->getServiceExchangeRates());
    }

    /**
     * Returns exchange rates from webservice
     *
     * @return array
     */
    public function getServiceExchangeRates()
    {
        static $cache = null;
        if (is_null($cache)) {
            $cache = [];
            $cache['EUR'] = 1.0;

            $content = $this->getXml();
            if ($content) {
                $xml = simplexml_load_string($content);
                if ($xml) {
                    foreach ($xml->Cube->Cube->Cube as $entry) {
                        $currency = strtoupper((string)$entry['currency']);
                        $rate = (float)$entry['rate'];
                        $cache[$currency] = $rate;
                    }
                } else {
                    $this->log("Failed to parse xml file");
                }
            }
        }
        return $cache;
    }

    /**
     * Returns service XML file, either from local cache or from webservice
     *
     * @return string|false
     */
    protected function getXml()
    {
        // try to load data from cache
        if (file_exists(static::SERVICECACHE_FILE)) {
            $cacheAge = time() - filemtime(static::SERVICECACHE_FILE);
            if ($cacheAge < static::SERVICECACHE_MAX_AGE) {
                $content = file_get_contents(static::SERVICECACHE_FILE);
                if ($content) {
                    return $content;
                }
            } else {
                unlink(static::SERVICECACHE_FILE);
            }
        }

        // fetch fresh data
        $content = $this->downloadXml();
        if ($content) {
            file_put_contents(static::SERVICECACHE_FILE, $content);
        }
        return $content;
    }

    /**
     * Download XML file from webservice
     *
     * @return string|false
     */
    protected function downloadXml()
    {
        $guzzle = new \GuzzleHttp\Client([
            'verify'    => _PS_TOOL_DIR_.'cacert.pem',
            'timeout'   => 20,
        ]);
        try {
            return (string)$guzzle->get(static::SERVICE_URL)->getBody();
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $this->log("Failed to retrieve xml file: " . $e);
            return false;
        } catch (Exception $e) {
            $this->log("Failed to retrieve xml file: " . $e);
            return false;
        }
    }

    /**
     * @param string $message
     *
     * @return void
     */
    protected function log($message)
    {
        try {
            Logger::addLog($this->name . ': ' . $message);
        } catch (Exception $e) {}
    }
}
