<?php namespace JavidFazaeli\JSubscriberX\Libraries;

use JavidFazaeli\JSubscriberX\Libraries\Providers\MailchimpProvider;

class ProviderFactory {
    public static function make(string $provider, array $cfg): ProviderInterface {
        switch (strtolower($provider)) {
            case 'mailchimp':
            default:
                $p = new MailchimpProvider;
                $p->configure($cfg);
                return $p;
        }
    }
}
