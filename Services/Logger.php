<?php

namespace JavidFazaeli\JSubscriberX\Services;

/**
 * JSubscriberX Logger Service
 *
 * Persists provider interactions (subscribe, tag, update, etc.)
 * into the `jsubx_logs` table for auditing and debugging.
 *
 * - Automatically sanitizes sensitive fields before insert.
 * - Supports convenience methods for "ok" and "error" logs.
 * - Provides query helpers for Control Panel log views.
 * - Includes housekeeping (purge old logs).
 *
 * @package   JavidFazaeli\JSubscriberX
 * @author    Javid Fazaeli
 * @license   MIT
 */
class Logger
{
    /** @var string DB table name (without prefix) */
    private string $table = 'jsubx_logs';

    /** @var array Keys considered sensitive (redacted from payload/response) */
    private array $secretKeys = ['api_key','authorization','password','token'];

    /**
     * Insert a log row.
     *
     * @param string      $provider   Provider identifier (e.g. mailchimp)
     * @param string|null $email      Subscriber email (may be null for some actions)
     * @param string      $action     Action taken: subscribe|tag|update|resubscribe|test|webhook
     * @param int         $http_code  HTTP status returned by provider
     * @param string      $status     Outcome: subscribed|pending|exists|ok|invalid|error
     * @param array       $payload    Request data (sanitized before insert)
     * @param array       $response   Provider response (sanitized before insert)
     */
    public function log(
        string $provider,
        ?string $email,
        string $action,
        int $http_code,
        string $status,
        array $payload = [],
        array $response = []
    ): void {
        $payload  = $this->sanitize($payload);
        $response = $this->sanitize($response, 1000); // truncate very long strings

        ee()->db->insert($this->table, [
            'provider'      => $provider,
            'email'         => $email ?: '',
            'action'        => $action,
            'http_code'     => $http_code,
            'status'        => $status,
            'payload_json'  => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'response_json' => json_encode($response, JSON_UNESCAPED_SLASHES),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Shortcut for successful operations.
     */
    public function ok(
        string $provider,
        ?string $email,
        string $action,
        array $payload = [],
        array $response = [],
        int $http = 200
    ): void {
        $this->log($provider, $email, $action, $http, 'ok', $payload, $response);
    }

    /**
     * Shortcut for error cases.
     *
     * @param string $status More specific status string (default "error")
     */
    public function error(
        string $provider,
        ?string $email,
        string $action,
        string $status = 'error',
        array $payload = [],
        array $response = [],
        int $http = 0
    ): void {
        $this->log($provider, $email, $action, $http, $status, $payload, $response);
    }

    /**
     * Fetch recent log entries (with optional filters).
     *
     * @param int   $limit   Max rows to fetch
     * @param int   $offset  Offset for pagination
     * @param array $filters Supported: email|status|action|since (YYYY-mm-dd HH:ii:ss)
     * @return array ['rows' => array, 'total' => int]
     */
    public function recent(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        $db = ee()->db;
        if (!empty($filters['email']))   $db->like('email', $filters['email']);
        if (!empty($filters['status']))  $db->where('status', $filters['status']);
        if (!empty($filters['action']))  $db->where('action', $filters['action']);
        if (!empty($filters['since']))   $db->where('created_at >=', $filters['since']);

        $total = $db->count_all_results($this->table, false); // keep builder state
        $rows  = $db->order_by('id','DESC')->limit($limit, $offset)->get()->result_array();
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Delete logs older than N days.
     *
     * @param int $days
     * @return int Number of rows deleted
     */
    public function purgeOlderThanDays(int $days): int
    {
        ee()->db->where('created_at <', date('Y-m-d H:i:s', strtotime("-{$days} days")));
        ee()->db->delete($this->table);
        return ee()->db->affected_rows();
    }

    /**
     * Recursively sanitize arrays:
     * - Remove sensitive keys (api_key, token, etc.).
     * - Optionally truncate long string values.
     *
     * @param array $data
     * @param int   $truncate If >0, clamp strings to this length
     * @return array
     */
    private function sanitize(array $data, int $truncate = 0): array
    {
        $clean = [];
        foreach ($data as $k => $v) {
            if (in_array(strtolower((string) $k), $this->secretKeys, true)) continue;

            if (is_array($v)) {
                $clean[$k] = $this->sanitize($v, $truncate);
            } else {
                $s = (string) $v;
                if ($truncate > 0 && strlen($s) > $truncate) {
                    $s = substr($s, 0, $truncate) . '…';
                }
                $clean[$k] = $s;
            }
        }
        return $clean;
    }
}
