<?php

namespace JavidFazaeli\JSubscriberX\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class Test extends AbstractRoute
{
    protected $route_path    = 'test';
    protected $cp_page_title = 'JSubscriberX — Test Connection';

    public function process($id = false)
    {
        // Inject CSS
        $css_path = PATH_THIRD . 'jsubscriberx/views/css/cp.css';
        if (is_file($css_path)) {
            ee()->cp->add_to_head('<style>' . file_get_contents($css_path) . '</style>');
        }

        $this->addBreadcrumb('test', 'Test Connection');

        // Prefill from saved settings
        $values = ['api_key' => '', 'list_id' => '', 'dc' => ''];
        $row = ee()->db->limit(1)->get_where('jsubx_settings', ['is_default' => 1])->row_array();
        if (!empty($row['config_enc'])) {
            $cfg = ee('jsubscriberx:crypto')->decryptToArray($row['config_enc']);
            if ($cfg === []) {
                ee('CP/Alert')->makeBanner('jsubx-test')
                    ->asIssue()->withTitle('Decryption error')
                    ->addToBody('Could not read saved configuration. Check your <code>jsubx_master_key</code> / <code>encryption_key</code>.')
                    ->now();
            } else {
                $values['api_key'] = $cfg['api_key'] ?? '';
                $values['list_id'] = $cfg['list_id'] ?? '';
                $values['dc']      = $cfg['dc'] ?? '';
            }
        }

        $result = null;

        // Handle POST (standard form submit)
        if (ee('Request')->isPost()) {
            // Override with posted values (if present)
            $values['api_key'] = trim((string) (ee()->input->post('api_key', true) ?: $values['api_key']));
            $values['list_id'] = trim((string) (ee()->input->post('list_id', true) ?: $values['list_id']));
            $values['dc']      = trim((string) (ee()->input->post('dc', true)      ?: $values['dc']));

            if ($values['dc'] === '' && strpos($values['api_key'], '-') !== false) {
                $values['dc'] = substr($values['api_key'], strpos($values['api_key'], '-') + 1);
            }

            // Validate
            $missing = [];
            if ($values['api_key'] === '') $missing[] = 'API Key';
            if ($values['list_id'] === '') $missing[] = 'Audience (List) ID';
            if ($values['dc']      === '') $missing[] = 'Data Center (dc)';

            if ($missing) {
                ee('CP/Alert')->makeBanner('jsubx-test')
                    ->asIssue()
                    ->withTitle('Missing required field' . (count($missing) > 1 ? 's' : ''))
                    ->addToBody('Please fill: <strong>' . implode('</strong>, <strong>', $missing) . '</strong>.')
                    ->now();
            } else {
                // Hit Mailchimp
                $url = "https://{$values['dc']}.api.mailchimp.com/3.0/lists/{$values['list_id']}";
                $ch  = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_USERPWD        => 'user:' . $values['api_key'],
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 12,
                ]);
                $resp = curl_exec($ch);
                $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);

                $json = $resp ? json_decode($resp, true) : [];
                $logger = ee('jsubscriberx:logger');

                if ($http >= 200 && $http < 300 && isset($json['id'])) {
                    ee('CP/Alert')->makeBanner('jsubx-test')
                        ->asSuccess()->withTitle('Connected')
                        ->addToBody('Audience <strong>' . htmlspecialchars($json['name'] ?? 'OK', ENT_QUOTES) . '</strong> is reachable.')
                        ->now();

                    $logger->ok('mailchimp', null, 'test',
                        ['list_id' => $values['list_id']],
                        ['name' => $json['name'] ?? null, 'http' => $http],
                        $http
                    );

                    $result = [
                        'ok'           => true,
                        'http'         => $http,
                        'list_id'      => $json['id'],
                        'list_name'    => $json['name'] ?? null,
                        'member_count' => $json['stats']['member_count'] ?? null,
                    ];
                } else {
                    $msg = $json['detail'] ?? ($json['title'] ?? 'Mailchimp error');

                    ee('CP/Alert')->makeBanner('jsubx-test')
                        ->asIssue()->withTitle('Failed')
                        ->addToBody(htmlspecialchars($msg, ENT_QUOTES) . ' (HTTP ' . $http . ')')
                        ->now();

                    $logger->error('mailchimp', null, 'test', 'error',
                        ['list_id' => $values['list_id']],
                        ['detail' => $msg, 'http' => $http],
                        $http
                    );

                    $result = [
                        'ok'   => false,
                        'http' => $http,
                        'message' => $msg,
                    ];
                }
            }
        }

        $variables = [
            'test_url'     => ee('CP/URL')->make('addons/settings/jsubscriberx/test')->compile(),
            'settings_url' => ee('CP/URL')->make('addons/settings/jsubscriberx/settings')->compile(),
            'values'       => $values,   // prefill fields
            'result'       => $result,   // optional details card
        ];

        $this->setBody('Test', $variables);
        return $this;
    }
}
