<?php
// Expect: $save_url, $settings, $config
ee()->load->helper('form');

$provider     = $settings['provider'] ?? 'mailchimp';
$label        = $settings['label'] ?? 'Default';
$api_key      = $config['api_key'] ?? '';
$list_id      = $config['list_id'] ?? '';
$dc           = $config['dc'] ?? '';
$double_in    = !empty($config['double_opt_in']);
$default_tags = !empty($config['default_tags']) ? implode(',', (array)$config['default_tags']) : 'newsletter';
?>

<div class="jx-form">
  <h1 class="jx-title">JSubscriberX</h1>
  <p class="jx-lead">Connect your newsletter provider and defaults.</p>

  <?= form_open($save_url, ['class' => 'jx-card']) ?>
  <input type="hidden" name="save_settings" value="1">
    <!-- Provider -->
    <div class="jx-field">
      <label for="provider">Provider</label>
      <select id="provider" name="provider">
        <option value="mailchimp" <?= $provider === 'mailchimp' ? 'selected' : '' ?>>Mailchimp</option>
        <!-- Future: add more providers here -->
      </select>

      <small class="jx-hint">Driver used for subscriptions.</small>
    </div>

    <!-- Label -->
    <div class="jx-field">
      <label for="label">Label</label>
      <input type="text" id="label" name="label" placeholder="Default" value="<?= $label ?>">
    </div>

    <!-- API Key -->
    <div class="jx-field">
      <label for="api_key">API Key</label>
      <input type="password" id="api_key" name="api_key" placeholder="••••••••" value="">
      <?php if ($api_key): ?>
        <small class="jx-hint">API key already saved. Leave blank to keep current.</small>
      <?php endif; ?>
    </div>

    <!-- Audience ID -->
    <div class="jx-field">
      <label for="list_id">Audience (List) ID</label>
      <input type="text" id="list_id" name="list_id" placeholder="e.g. a1b2c3d4e5" value="<?= $list_id ?>">
    </div>

    <!-- Data Center -->
    <div class="jx-field">
      <label for="dc">Data Center</label>
      <input type="text" id="dc" name="dc" placeholder="us21" value="<?= $dc ?>" >
      <small class="jx-hint">Usually the suffix of your API key.</small>
    </div>

    <!-- Double Opt-In -->
    <div class="jx-field">
      <label for="double_opt_in">Double Opt-In</label>
      <label class="jx-check">
        <input type="checkbox" id="double_opt_in" name="double_opt_in" value="1" <?= $double_in ? 'checked' : '' ?>>
        <span>Require confirmation email (adds as <code>pending</code>)</span>
      </label>
    </div>

    <!-- Default Tags -->
    <div class="jx-field">
      <label for="default_tags">Default Tags</label>
      <input type="text" id="default_tags" name="default_tags" placeholder="newsletter,beta" value="<?= $default_tags ?>">
    </div>

    <!-- Actions -->
    <div class="jx-actions">
      <button type="submit" name="save_settings" value="1" class="jx-btn jx-btn-primary">Save Settings</button>
    </div>
 <?= form_close() ?>
</div>

