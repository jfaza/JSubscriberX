<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use ExpressionEngine\Service\Addon\Module;
use JavidFazaeli\JSubscriberX\Services\SubscriptionService;
use JavidFazaeli\JSubscriberX\Services\Logger;
use JavidFazaeli\JSubscriberX\Libraries\ProviderFactory;

class Jsubscriberx extends Module
{
    protected $addon_name = 'jsubscriberx';

}
