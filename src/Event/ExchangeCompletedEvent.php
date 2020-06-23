<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/23/2020
 * Time: 3:18 PM
 */

namespace App\Event;

use App\Entity\AccountExchange;
use Symfony\Component\EventDispatcher\Event;

class ExchangeCompletedEvent extends Event
{
    const NAME = 'exchange.completed';

    /**
     * @var AccountExchange
     */
    private $exchange;

    public function __construct(AccountExchange $exchange)
    {
        $this->exchange = $exchange;
    }

    /**
     * @return AccountExchange
     */
    public function getExchange()
    {
        return $this->exchange;
    }
}