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
    const SERVICE_URL = 'http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    // Not compatible with PHP 5.5 (but starting with PHP 5.6):
    //const SERVICECACHE_FILE = _PS_CACHE_DIR_.'/ecbexchangeServiceCache.php';
    // Instead (remove this after dropping PHP 5.5 support):
    protected static $SERVICECACHE_FILE;
    const SERVICECACHE_MAX_AGE = 3600; // seconds

    /*
     * If filled, an array with currency exchange rates, like this:
     *
     *     [
     *         'EUR' => 1.233434,
     *         'USD' => 1.343,
     *         [...]
     *     ]
     */
    protected $serviceCache = [];

    /**
     * ECBExchange constructor.
     */
    public function __construct()
    {
        $this->name = 'ecbexchange';
        $this->tab = 'administration';
        $this->version = '1.0.2';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('ECB Exchange Rate Services');
        $this->description = $this->l('Fetches currency exchange rates from the European Central Bank.');
        $this->tb_versions_compliancy = '> 1.0.0';

        // For PHP 5.5 support.
        static::$SERVICECACHE_FILE = _PS_CACHE_DIR_.'/ecbexchangeServiceCache.php';
    }

    /**
     * @return bool
     */
    public function install()
    {
        return parent::install()
               && $this->registerHook('actionRetrieveCurrencyRates');
    }

    /**
     * @param  array $params Description see hookActionRetrieveCurrencyRates()
     *                       in classes/module/CurrencyRateModule.php in core.
     *
     * @return false|array   Description see hookActionRetrieveCurrencyRates()
     *                       in classes/module/CurrencyRateModule.php in core.
     *
     * @since 1.0.0
     */
    public function hookActionRetrieveCurrencyRates($params)
    {
        static::fillServiceCache();

        $divisor = $this->serviceCache[$params['baseCurrency']];

        $exchangeRates = [];
        foreach ($params['currencies'] as $currency) {
            if (array_key_exists($currency, $this->serviceCache)) {
                $exchangeRates[$currency] =
                    $this->serviceCache[$currency] / $divisor;
            } else {
                $exchangeRates[$currency] = false;
            }
        }

        return $exchangeRates;
    }

    /**
     * @return array An array with uppercase currency codes (ISO 4217).
     *
     * @since 1.0.0
     */
    public function getSupportedCurrencies()
    {
        static::fillServiceCache();

        return array_keys($this->serviceCache);
    }

    /**
     * Makes sure that $this->serviceCache is filled and does an service
     * request if not. Note that $this->serviceCache can be still an empty
     * array after return, e.g. if the request failed for some reason.
     *
     * @since 1.0.0
     */
    public function fillServiceCache()
    {
        @include static::$SERVICECACHE_FILE;

        if (file_exists(static::$SERVICECACHE_FILE)) {
            $cacheAge = time() - filemtime(static::$SERVICECACHE_FILE);
        } else {
            $cacheAge = PHP_INT_MAX;
        }

        if (!count($this->serviceCache)
            || $cacheAge > static::SERVICECACHE_MAX_AGE) {
            $this->serviceCache = [];

            $guzzle = new \GuzzleHttp\Client([
                'verify'    => _PS_TOOL_DIR_.'cacert.pem',
                'timeout'   => 20,
            ]);
            try {
                $response = $guzzle->get(static::SERVICE_URL)->getBody();
                $XML = simplexml_load_string($response);

                $this->serviceCache['EUR'] = 1.0;
                foreach ($XML->Cube->Cube->Cube as $entry) {
                    $this->serviceCache[(string) $entry['currency']] =
                        (float) $entry['rate'];
                }

                file_put_contents(static::$SERVICECACHE_FILE,
                                  "<?php\n\n".'$this->serviceCache = '
                                  .var_export($this->serviceCache, true)
                                  .";\n");
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate(static::$SERVICECACHE_FILE);
                }
            } catch (Exception $e) {
                $this->serviceCache = [];
            }
        }
    }
}
