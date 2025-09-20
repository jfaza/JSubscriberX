<?php
use ExpressionEngine\Service\Addon\Installer;
use JavidFazaeli\JSubscriberX\Services\Crypto; 
use JavidFazaeli\JSubscriberX\Services\Logger;
use JavidFazaeli\JSubscriberX\Libraries\ProviderFactory;
use JavidFazaeli\JSubscriberX\Services\SubscriptionService;

/**
 * JSubscriberX Add-on Setup
 *
 * Defines meta information and service container bindings.
 * Registered services can be accessed via ee('jsubscriberx:serviceName').
 */
return [
    'name'              => 'JSubscriberX',
    'description'       => 'Provider-agnostic newsletter subscriptions with Mailchimp driver.',
    'version'           => '1.0.0',
    'author'            => 'Javid Fazaeli',
    'author_url'        => 'fazaeli.dev',
    'namespace'         => 'JavidFazaeli\JSubscriberX',
    'settings_exist'    => true,

    'services' => [
        /**
         * Crypto service
         *
         * Provides symmetric encryption/decryption for storing provider
         * configuration securely in the database.
         *
         * Example:
         *   ee('jsubscriberx:crypto')->encryptArray([...]);
         */
        'crypto' => function($addon){
            // future: if needed pass as parameter
            // ee('jsubscriberx:db') for dependency injection
            return new Crypto(); 
        },

         /**
         * Logger service
         *
         * Persists subscription events and API responses into `jsubx_logs`.
         * Useful for debugging, auditing, or building a CP log viewer.
         *
         * Example:
         *   ee('jsubscriberx:logger')->ok('mailchimp', 'user@example.com', 'subscribe');
         */
        'logger' => function($addon){
            return new Logger(); 
        },

        /**
         * Subscription service
         *
         * Resolves the default provider from `jsubx_settings`, decrypts its
         * configuration, and returns a fully-wired SubscriptionService that
         * can perform subscribe/tag/update calls with logging.
         *
         * Example:
         *   $svc = ee('jsubscriberx:subx');
         *   $res = $svc->subscribe('user@example.com', ['tags' => ['newsletter']]);
         *
         * @throws \RuntimeException if not configured or decryption fails
         */
        'subx' => function ($addon) {
            // 1. Load default provider row
            $row = ee()->db->limit(1)->get_where('jsubx_settings', ['is_default' => 1])->row_array();
            if (!$row || empty($row['config_enc'])) {
                throw new \RuntimeException('JSubscriberX is not configured.');
            }

            // 2. Decrypt config (expects JSON string)
            $cfg = ee('jsubscriberx:crypto')->decryptToArray($row['config_enc']);
            if ($cfg === []) {
                throw new \RuntimeException('Decryption error (check master key).');
            }

            // 3. Determine provider name (default: mailchimp)
            $providerName = strtolower((string)($row['provider'] ?? 'mailchimp'));

            // 4. Build provider instance (factory handles provider-specific wiring)
            $provider = ProviderFactory::make($providerName, $cfg);

            // 5. Return ready-to-use subscription service with logger attached
            return new SubscriptionService(
                $provider,
                ee('jsubscriberx:logger')
            );
        },

    ]

];
