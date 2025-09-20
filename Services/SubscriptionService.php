<?php namespace JavidFazaeli\JSubscriberX\Services;

use JavidFazaeli\JSubscriberX\Libraries\ProviderFactory;
use JavidFazaeli\JSubscriberX\Libraries\ProviderInterface;


class SubscriptionService {
    protected ProviderInterface $provider;
    protected Logger $logger;

    public function __construct(?ProviderInterface $p = null, ?Logger $l = null) {
        $this->logger = $l ?: ee('jsubscriberx:logger');

        if ($p) {
            $this->provider = $p;
            return;
        }

        // Build from saved settings
        $row = ee()->db->limit(1)->get_where('jsubx_settings', ['is_default'=>1])->row_array();
        if (!$row || empty($row['config_enc'])) {
            throw new \RuntimeException('JSubscriberX is not configured.');
        }
        $cfg = ee('jsubscriberx:crypto')->decryptToArray($row['config_enc']);
        if ($cfg === []) {
            throw new \RuntimeException('Decryption error (check master key).');
        }
        $providerName = strtolower((string)($row['provider'] ?? 'mailchimp'));

        // You’re already passing api_key, list_id, dc, double_opt_in, default_tags in $cfg
        $this->provider = ProviderFactory::make($providerName, $cfg);
    }

    public function subscribe(string $email, array $opts): array {
        $payload = [
            'email'        => $email,
            'status'       => $opts['status'] ?? ($this->provider->doubleOptIn() ? 'pending' : 'subscribed'),
            'tags'         => $opts['tags'] ?? $this->provider->defaultTags(),
            'merge_fields' => $opts['merge_fields'] ?? [],
        ];

        try {
            $res = $this->provider->subscribe($payload);
        } catch (\Throwable $e) {
            $res = ['ok'=>false,'http'=>0,'status'=>'error','response'=>['detail'=>$e->getMessage()],'message'=>'Subscription failed'];
        }

        $this->logger->log(
            $this->provider->name(),
            $email,
            'subscribe',
            (int)($res['http'] ?? 0),
            (string)($res['status'] ?? ($res['ok'] ? 'subscribed' : 'error')),
            ['tags'=>$payload['tags'], 'merge_fields'=>$payload['merge_fields']],
            (array)($res['response'] ?? [])
        );

        return $res;
    }
}
