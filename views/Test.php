<?php
// Expect: $test_url, $settings_url, $values (api_key,list_id,dc), $result (optional)
ee()->load->helper('form');
$api_key = $values['api_key'] ?? '';
$list_id = $values['list_id'] ?? '';
$dc      = $values['dc'] ?? '';
?>
<div class="jx-form">
  <h1 class="jx-title">Test Connection</h1>
  <p class="jx-lead">Check access to your Mailchimp audience. Prefilled from saved settings; you can override and test. This page does not change saved settings.</p>

  <?= form_open($test_url, ['class' => 'jx-card']) ?>
    <div class="jx-field">
      <label for="t_api_key">API Key</label>
      <input type="password" id="t_api_key" name="api_key" placeholder="••••••••" value="<?= htmlspecialchars($api_key, ENT_QUOTES) ?>">
      <small class="jx-hint">We won’t store changes made here unless you save them in Settings.</small>
    </div>

    <div class="jx-field">
      <label for="t_list_id">Audience (List) ID</label>
      <input type="text" id="t_list_id" name="list_id" placeholder="e.g. a1b2c3d4e5" value="<?= htmlspecialchars($list_id, ENT_QUOTES) ?>">
    </div>

    <div class="jx-field">
      <label for="t_dc">Data Center</label>
      <input type="text" id="t_dc" name="dc" placeholder="us9" value="<?= htmlspecialchars($dc, ENT_QUOTES) ?>">
    </div>

    <div class="jx-actions">
      <button type="submit" class="jx-btn">Run Test</button>
      <span class="jx-actions-spacer"></span>
      <a href="<?= $settings_url ?>" class="jx-btn jx-btn-primary">Edit Settings</a>
    </div>
  <?= form_close() ?>

  <?php if (!empty($result)): ?>
    <div class="jx-card" style="margin-top:12px;">
      <div class="jx-card-head">
        <span class="jx-pill <?= $result['ok'] ? 'success' : 'danger' ?>">
          <?= $result['ok'] ? 'Connected' : 'Failed' ?>
        </span>
      </div>
      <div class="jx-summary">
        <div class="jx-kv">
          <div class="jx-kv__label">HTTP</div>
          <div class="jx-kv__value"><?= (int)($result['http'] ?? 0) ?></div>
        </div>
        <?php if (!empty($result['list_name'])): ?>
        <div class="jx-kv">
          <div class="jx-kv__label">Audience</div>
          <div class="jx-kv__value"><?= htmlspecialchars($result['list_name'], ENT_QUOTES) ?></div>
        </div>
        <?php endif; ?>
        <?php if (isset($result['member_count'])): ?>
        <div class="jx-kv">
          <div class="jx-kv__label">Members</div>
          <div class="jx-kv__value"><?= (int)$result['member_count'] ?></div>
        </div>
        <?php endif; ?>
        <?php if (!$result['ok'] && !empty($result['message'])): ?>
        <div class="jx-kv">
          <div class="jx-kv__label">Message</div>
          <div class="jx-kv__value"><?= htmlspecialchars($result['message'], ENT_QUOTES) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
