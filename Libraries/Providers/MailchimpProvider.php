<?php namespace JavidFazaeli\JSubscriberX\Libraries\Providers;

use JavidFazaeli\JSubscriberX\Libraries\ProviderInterface;

class MailchimpProvider implements ProviderInterface
{
    protected string $apiKey = '';
    protected string $listId = '';
    protected string $dc     = '';
    protected bool   $doubleOpt   = false;
    protected array  $defaultTags = [];

    public function name(): string { return 'mailchimp'; }
    public function doubleOptIn(): bool { return $this->doubleOpt; }
    public function defaultTags(): array { return $this->defaultTags; }

    public function configure(array $cfg): void
    {
        $this->apiKey      = (string)($cfg['api_key'] ?? '');
        $this->listId      = (string)($cfg['list_id'] ?? '');
        $this->dc          = (string)($cfg['dc'] ?? '');
        $this->doubleOpt   = !empty($cfg['double_opt_in']);
        $this->defaultTags = array_values(array_filter((array)($cfg['default_tags'] ?? [])));

        if ($this->dc === '' && strpos($this->apiKey, '-') !== false) {
            $this->dc = substr($this->apiKey, strpos($this->apiKey, '-') + 1);
        }
    }

    protected function http(string $method, string $path, $body = null): array
    {
        $url = "https://{$this->dc}.api.mailchimp.com/3.0{$path}";
        $ch  = curl_init($url);
        $opts = [
            CURLOPT_USERPWD        => 'user:' . $this->apiKey,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'User-Agent: JSubscriberX/1.0'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_TIMEOUT        => 15,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);

        $resp = curl_exec($ch);
        $err  = $resp === false ? curl_error($ch) : null;
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($resp === false) {
            return [0, ['title' => 'Network error', 'detail' => $err]];
        }
        $json = json_decode($resp, true);
        return [$http, is_array($json) ? $json : []];
    }

    private function memberHash(string $email): string
    {
        return md5(strtolower($email));
    }

    public function subscribe(array $s): array
    {
        $email  = (string)($s['email'] ?? '');
        $status = (string)($s['status'] ?? ($this->doubleOpt ? 'pending' : 'subscribed'));

        // Ensure merge_fields is encoded as an OBJECT ({}), not [] when empty
        $mfArr = is_array($s['merge_fields'] ?? null) ? array_filter($s['merge_fields'], fn($v) => $v !== null && $v !== '') : [];
        $mergeFields = (object) $mfArr;

        // Merge default tags with per-request tags, unique + trimmed
        $tags = array_values(array_unique(array_filter(array_map(
            fn($t) => trim((string)$t),
            array_merge($this->defaultTags, (array)($s['tags'] ?? []))
        ))));

        // 1) CREATE member (no 'tags' here)
        [$http, $raw] = $this->http('POST', "/lists/{$this->listId}/members", [
            'email_address' => $email,
            'status'        => $status,
            'merge_fields'  => $mergeFields,
        ]);

        // If “member exists”, upsert via PUT, then tag
        if ($http === 400 && isset($raw['title']) && stripos($raw['title'], 'exists') !== false) {
            $hash = $this->memberHash($email);
            [$http, $raw] = $this->http('PUT', "/lists/{$this->listId}/members/{$hash}", [
                'email_address' => $email,
                'status_if_new' => $status,
                'merge_fields'  => $mergeFields, // object here too
            ]);

            if (!empty($tags)) {
                $this->upsertTags($email, $tags);
            }

            $respStatus = (string)($raw['status'] ?? $status);
            return [
                'ok'       => $http >= 200 && $http < 300,
                'http'     => $http,
                'status'   => $respStatus === 'pending' ? 'pending' : 'subscribed',
                'response' => $raw,
                'message'  => $respStatus === 'pending' ? 'Pending confirmation' : 'Already subscribed',
            ];
        }

        // 2xx create → tag in a follow-up
        if ($http >= 200 && $http < 300) {
            if (!empty($tags)) {
                $this->upsertTags($email, $tags);
            }
            $respStatus = (string)($raw['status'] ?? $status);
            return [
                'ok'       => true,
                'http'     => $http,
                'status'   => $respStatus === 'pending' ? 'pending' : 'subscribed',
                'response' => $raw,
                'message'  => $respStatus === 'pending' ? 'Pending confirmation' : 'Subscribed',
            ];
        }

        // 400+ with validation errors → surface a helpful message
        $detail = $raw['detail'] ?? 'Validation failed';
        if (!empty($raw['errors'][0])) {
            $e0 = $raw['errors'][0];
            $fieldMsg = trim(($e0['field'] ?? '') . ' ' . ($e0['message'] ?? ''));
            if ($fieldMsg !== '') $detail = $fieldMsg;
        }

        return [
            'ok'       => false,
            'http'     => $http,
            'status'   => 'error',
            'response' => $raw,
            'message'  => $detail,
        ];
    }

    public function unsubscribe(string $email): array
    {
        $hash = $this->memberHash($email);
        [$http, $raw] = $this->http('PATCH', "/lists/{$this->listId}/members/{$hash}", ['status' => 'unsubscribed']);

        return [
            'ok'       => $http >= 200 && $http < 300,
            'http'     => $http,
            'status'   => $http >= 200 && $http < 300 ? 'ok' : 'error',
            'response' => $raw,
            'message'  => $raw['detail'] ?? null,
        ];
    }

    public function upsertTags(string $email, array $tags): array
    {
        if (empty($tags)) {
            return ['ok' => true, 'http' => 204, 'status' => 'ok', 'response' => [], 'message' => null];
        }
        $hash = $this->memberHash($email);
        $body = ['tags' => array_map(fn($t) => ['name' => $t, 'status' => 'active'], $tags)];
        [$http, $raw] = $this->http('POST', "/lists/{$this->listId}/members/{$hash}/tags", $body);

        return [
            'ok'       => $http >= 200 && $http < 300,
            'http'     => $http,
            'status'   => $http >= 200 && $http < 300 ? 'ok' : 'error',
            'response' => $raw,
            'message'  => $raw['detail'] ?? null,
        ];
    }
}
