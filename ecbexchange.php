<?php
/**
 * Copyright (C) 2018 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2018 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

/**
 * Class ECBExchange
 */
class ECBExchange extends CurrencyRateModule
{
    /**
     * ECBExchange constructor.
     */
    public function __construct()
    {
        $this->name = 'ecbexchange';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('ECB Exchange Rate Services');
        $this->description = $this->l('Fetches currency exchange rates from the European Central Bank.');
        $this->tb_versions_compliancy = '> 1.0.0';
    }

    /**
     * @return bool
     */
    public function install()
    {
        return parent::install();
    }
}
