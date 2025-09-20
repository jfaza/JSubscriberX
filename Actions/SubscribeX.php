<?php

namespace JavidFazaeli\JSubscriberX\Actions;

use ExpressionEngine\Service\Addon\Controllers\Action\AbstractRoute;

class SubscribeX extends AbstractRoute
{
    public function process()
    {
        $isAjax = ee('Request')->isAjax(); // checks X-Requested-With + fetch()
        if (! ee('Request')->isPost()) {
            return $this->respond(['success' => false, 'message' => 'POST required'], 405, $isAjax);
        }

        // Accept JSON bodies too
        $contentType = (string) ee()->input->get_request_header('Content-Type', true);
        $isJsonBody  = (stripos($contentType, 'application/json') === 0);

        $body = [];
        if ($isJsonBody) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $body = $decoded;
        }

        $get = fn($key, $xss = true) => $body[$key] ?? ee()->input->post($key, $xss);

        // Honeypot (bot -> ignore but "success" to avoid probing)
        if (trim((string) $get('hp')) !== '') {
            return $this->respond(['success' => true, 'status' => 'ignored', 'message' => ''], 200, $isAjax);
        }

        // Inputs
        $email = trim((string) $get('email', true));
        $fname = trim((string) $get('first_name', true));
        $lname = trim((string) $get('last_name', true));
        $tags  = $this->normalizeTags($get('tags'));

        // Basic syntax validation (consider MX check if you want stricter)
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respond(['success' => false, 'message' => 'Invalid email'], 422, $isAjax);
        }

        // Resolve subscription service
        try {
            $svc = ee('jsubscriberx:subx');
        } catch (\Throwable $e) {
            error_log('[JSubscriberX:init] ' . $e->getMessage());
            return $this->respond(['success' => false, 'message' => 'Server error (init)'], 500, $isAjax);
        }

        // mx check
        if (! $this->hasMx($email)) {
            return $this->respond(
                [
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'Email domain does not accept mail',
                    'http'    => 422,
                ],
                422,
                $isAjax
            );
        }


        // Call provider
        try {
            $res = $svc->subscribe($email, [
                'merge_fields' => array_filter([
                    'FNAME' => $fname ?: null,
                    'LNAME' => $lname ?: null,
                ]),
                'tags' => $tags,
            ]);
        } catch (\Throwable $e) {
            error_log('[JSubscriberX:subscribe] ' . $e->getMessage());
            return $this->respond(['success' => false, 'message' => 'Server error (subscribe)'], 500, $isAjax);
        }

        // Normalize provider response
        $success = !empty($res['ok']) || !empty($res['success']);
        $status  = strtolower((string) ($res['status'] ?? ($success ? 'subscribed' : 'error')));
        $msg     = isset($res['message']) ? (string) $res['message'] : null;

        // Prefer 202 for pending (double opt-in), otherwise 200/400
        $http = isset($res['http'])
            ? (int) $res['http']
            : ($success ? ($status === 'pending' ? 202 : 200) : 400);

        // Enforce consistent, human-friendly messages
        if ($status === 'pending') {
            $msg = 'Check your inbox to confirm.'; // override provider phrasing
        } elseif ($success && $status === 'subscribed' && $msg === null) {
            $msg = 'You are subscribed.';
        } elseif (! $success && $msg === null) {
            $msg = 'Unable to subscribe';
        }

        // Respond (JSON for AJAX, or redirect with QS for non-AJAX)
        return $this->respond(
            ['success' => $success, 'status' => $status, 'message' => $msg, 'http' => $http],
            $http,
            $isAjax
        );

    }

     /**
     * Normalize tag inputs (CSV string, array, or array-with-CSV as [0]).
     *
     * @param mixed $input
     * @return array
     */
    private function normalizeTags($input): array
    {
        if (is_array($input)) {
            if (count($input) === 1 && strpos((string) $input[0], ',') !== false) {
                $input = explode(',', (string) $input[0]);
            }
            return array_values(array_filter(array_map(fn($t) => trim((string) $t), $input)));
        }
        if (is_string($input) && $input !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $input))));
        }
        return [];
    }

    /**
     * Respond:
     * - If AJAX: send JSON with chosen HTTP status
     * - If non-AJAX: redirect to "return" URL (if safe) with QS; otherwise JSON
     *
     * @param array $data Normalized payload (success,status,message,http)
     * @param int   $code HTTP status code
     * @param bool  $isAjax
     * @return mixed
     */
    // private function respond(array $data, int $code, bool $isAjax)
    // {
    //     if ($isAjax) {
    //         return ee()->output->send_ajax_response($data, $code);
    //     }

    //     // Non-AJAX fallback (no JS): redirect to "return" with qs, if present
    //     $return = (string) ee()->input->post('return', true);
    //     if ($return !== '') {
    //         // Keep relative/same-host only (avoid open-redirect)
    //         $safe = $this->sanitizeReturnUrl($return);
    //         if ($safe) {
    //             $qs = http_build_query([
    //                 'ok'     => !empty($data['success']) ? 1 : 0,
    //                 'status' => (string) ($data['status'] ?? ''),
    //                 'msg'    => (string) ($data['message'] ?? ''),
    //             ]);
    //             ee()->functions->redirect($safe . (strpos($safe, '?') !== false ? '&' : '?') . $qs);
    //             return;
    //         }
    //     }

    //     // Fallback: JSON even for non-AJAX (useful in dev)
    //     return ee()->output->send_ajax_response($data, $code);
    // }
    private function respond(array $data, int $code, bool $isAjax)
    {
        // Reason-phrases for codes CI doesn't know by default
        static $httpText = [
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
        ];

        if ($isAjax) {
            // Explicitly set status header with a reason phrase (prevents CI error)
            ee()->output->set_status_header($code, $httpText[$code] ?? null);
            // Now send JSON WITHOUT passing $code again
            return ee()->output->send_ajax_response($data);
        }

        // Non-AJAX: optional redirect with QS
        $return = (string) ee()->input->post('return', true);
        if ($return !== '') {
            $safe = $this->sanitizeReturnUrl($return);
            if ($safe) {
                $qs = http_build_query([
                    // use 'success' to match your payload’s key
                    'success' => !empty($data['success']) ? 1 : 0,
                    'status'  => (string) ($data['status'] ?? ''),
                    'msg'     => (string) ($data['message'] ?? ''),
                    'http'    => (int)    ($data['http'] ?? $code),
                ]);
                ee()->functions->redirect($safe . (strpos($safe, '?') !== false ? '&' : '?') . $qs);
                return;
            }
        }

        // Fallback JSON for non-AJAX too
        ee()->output->set_status_header($code, $httpText[$code] ?? null);
        return ee()->output->send_ajax_response($data);
    }


    /**
     * Allow only relative or same-host URLs to prevent open-redirects.
     */
    private function sanitizeReturnUrl(string $url): ?string
    {
        if (strlen($url) && $url[0] === '/') return $url;

        $t = parse_url($url);
        if (! isset($t['host'])) return '/' . ltrim($url, '/');

        $curr = parse_url(ee()->config->site_url());
        $hostOk = strtolower($t['host'] ?? '') === strtolower($curr['host'] ?? '');
        $portOk = (string)($t['port'] ?? '') === (string)($curr['port'] ?? '');
        if ($hostOk && $portOk) {
            $path = ($t['path'] ?? '/');
            $q    = isset($t['query']) ? '?' . $t['query'] : '';
            $f    = isset($t['fragment']) ? '#' . $t['fragment'] : '';
            return $path . $q . $f;
        }
        return null;
    }

    private function hasMx(string $email): bool
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return false;

        return checkdnsrr($parts[1], 'MX');
    }


}
