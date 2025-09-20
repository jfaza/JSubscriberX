<?php

namespace JavidFazaeli\JSubscriberX\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class Index extends AbstractRoute
{
    protected $route_path    = 'index';
    protected $cp_page_title = 'JSubscriberX Overview';

    public function process($id = false)
    {
        // Inject scoped CP CSS
        $css_path = PATH_THIRD . 'jsubscriberx/views/css/cp.css';
        if (is_file($css_path)) {
            ee()->cp->add_to_head('<style>' . file_get_contents($css_path) . '</style>');
        }

        $this->addBreadcrumb('index', 'Overview');

        $row        = ee()->db->limit(1)->get_where('jsubx_settings', ['is_default' => 1])->row_array();
        $summary    = [];
        $configured = false;

        if (!empty($row['config_enc'])) {
            $crypto = ee('jsubscriberx:crypto');
            $cfg    = $crypto->decryptToArray($row['config_enc']);

            if ($cfg === []) {
                // Config exists but couldn’t be decrypted
                ee('CP/Alert')->makeBanner('jsubx-index')
                    ->asIssue()->withTitle('Decryption error')
                    ->addToBody('Could not read saved configuration. Check your <code>jsubx_master_key</code> or <code>encryption_key</code>.')
                    ->now();
            }

            $configured = !empty($cfg['api_key']) && !empty($cfg['list_id']);

            $summary = [
                'Provider'      => ucfirst($row['provider'] ?? 'mailchimp'),
                'Label'         => $row['label'] ?? 'Default',
                'Audience ID'   => $cfg['list_id'] ?? '—',
                'Data Center'   => $cfg['dc'] ?? '—',
                'Double Opt-In' => !empty($cfg['double_opt_in']) ? 'On' : 'Off',
                'Default Tags'  => !empty($cfg['default_tags']) ? implode(', ', (array) $cfg['default_tags']) : '—',
                'API Key'       => $this->maskApiKey($cfg['api_key'] ?? ''), // masked
                'Last Saved'    => $row['updated_at'] ?: ($row['created_at'] ?? '—'),
            ];
        }

        $variables = [
            'configured'   => $configured,
            'summary'      => $summary,
            'settings_url' => ee('CP/URL')->make('addons/settings/jsubscriberx/settings')->compile(),
            'logs_url'     => ee('CP/URL')->make('addons/settings/jsubscriberx/logs')->compile(),
        ];

        $this->setBody('Index', $variables);
        return $this;
    }

    private function maskApiKey(string $key): string
    {
        if ($key === '') return '—';
        if (strpos($key, '-') !== false) {
            [$part, $dc] = explode('-', $key, 2);
            $tail = substr($part, -4);
            return '••••' . $tail . '-' . $dc;
        }
        return '••••' . substr($key, -4);
    }
}
