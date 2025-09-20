<?php

namespace JavidFazaeli\JSubscriberX\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class Settings extends AbstractRoute
{
    protected $route_path     = 'settings';
    protected $cp_page_title  = 'JSubscriberX Settings';

    // ... namespace + class headers stay the same ...

    public function process($id = false)
    {
        // Inject CSS
        $css_path = PATH_THIRD . 'jsubscriberx/views/css/cp.css';
        if (is_file($css_path)) {
            ee()->cp->add_to_head('<style>' . file_get_contents($css_path) . '</style>');
        }

        $this->addBreadcrumb('settings', 'Settings');

        // POST: save
        if (ee('Request')->post('save_settings')) {
            $provider = strtolower(trim((string) (ee()->input->post('provider', true) ?? 'mailchimp')));
            $label    = trim((string) (ee()->input->post('label', true) ?? 'Default'));

            $cfg = [
                'api_key'       => trim((string) (ee()->input->post('api_key', true) ?? '')),
                'list_id'       => trim((string) (ee()->input->post('list_id', true) ?? '')),
                'dc'            => trim((string) (ee()->input->post('dc', true) ?? '')),
                'double_opt_in' => ee()->input->post('double_opt_in') ? 1 : 0,
                'default_tags'  => $this->normalizeTags(ee()->input->post('default_tags')),
            ];

            if ($cfg['dc'] === '' && strpos($cfg['api_key'], '-') !== false) {
                $cfg['dc'] = substr($cfg['api_key'], strpos($cfg['api_key'], '-') + 1);
            }

            $missing = [];
            if ($cfg['api_key'] === '') $missing[] = 'API Key';
            if ($cfg['list_id'] === '') $missing[] = 'Audience (List) ID';
            if ($cfg['dc'] === '')      $missing[] = 'Data Center (dc)';

            if (!empty($missing)) {
                ee('CP/Alert')->makeBanner('jsubx-settings')
                    ->asIssue()
                    ->withTitle('Missing required field' . (count($missing) > 1 ? 's' : ''))
                    ->addToBody('Please fill: <strong>' . implode('</strong>, <strong>', $missing) . '</strong>.')
                    ->now();

                $row = ['provider' => $provider, 'label' => $label, 'is_default' => 1];
                $variables = [
                    'save_url' => ee('CP/URL')->make('addons/settings/jsubscriberx/settings')->compile(),
                    'settings' => $row,
                    'config'   => $cfg,
                ];
                $this->setBody('Settings', $variables);
                return $this;
            }

            // Encrypt + save
            $crypto = ee('jsubscriberx:crypto');

            ee()->db->trans_start();
            ee()->db->where('is_default', 1)->delete('jsubx_settings');
            ee()->db->insert('jsubx_settings', [
                'provider'   => $provider,
                'label'      => $label,
                'is_default' => 1,
                'config_enc' => $crypto->encryptArray($cfg),   // <— helper
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            ee()->db->trans_complete();

            if (!ee()->db->trans_status()) {
                ee('CP/Alert')->makeBanner('jsubx-settings')
                    ->asIssue()->withTitle('Save failed')
                    ->addToBody('Database error while saving settings.')
                    ->now();

                $row = ['provider' => $provider, 'label' => $label, 'is_default' => 1];
                $variables = [
                    'save_url' => ee('CP/URL')->make('addons/settings/jsubscriberx/settings')->compile(),
                    'settings' => $row,
                    'config'   => $cfg,
                ];
                $this->setBody('Settings', $variables);
                return $this;
            }

            ee('CP/Alert')->makeBanner('jsubx-settings')
                ->asSuccess()->withTitle('Saved')->addToBody('Settings updated.')
                ->defer();

            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jsubscriberx/settings'));
        }

        // GET: load settings
        $row = ee()->db->limit(1)->get_where('jsubx_settings', ['is_default' => 1])->row_array();
        $config = [];
        if ($row && !empty($row['config_enc'])) {
            $crypto = ee('jsubscriberx:crypto');
            $config = $crypto->decryptToArray($row['config_enc']); // <— helper

            if ($config === []) {
                ee('CP/Alert')->makeBanner('jsubx-settings')
                    ->asIssue()->withTitle('Decryption error')
                    ->addToBody('Could not read saved configuration. Check your <code>jsubx_master_key</code> / <code>encryption_key</code>.')
                    ->now();
            }
        }

        $variables = [
            'save_url' => ee('CP/URL')->make('addons/settings/jsubscriberx/settings')->compile(),
            'settings' => $row ?: ['provider' => 'mailchimp', 'label' => 'Default'],
            'config'   => $config,
        ];

        $this->setBody('Settings', $variables);
        return $this;
    }


    private function normalizeTags($input): array
    {
        if (is_array($input)) {
            if (count($input) === 1 && strpos((string) $input[0], ',') !== false) {
                $input = explode(',', (string) $input[0]);
            }
            return array_values(array_filter(array_map(fn($t) => trim((string) $t), $input)));
        }
        if (is_string($input)) {
            return array_values(array_filter(array_map('trim', explode(',', (string) $input))));
        }
        return [];
    }
}
