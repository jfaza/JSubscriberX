<?php
// Expect: $configured (bool), $summary (array), $settings_url, $logs_url
?>
<div class="jx-form">
  <h1 class="jx-title">JSubscriberX</h1>
  <p class="jx-lead">Overview of your current newsletter configuration.</p>

  <div class="jx-card">
    <div class="jx-actions" style="justify-content:space-between; margin:0 0 8px;">
      <span class="jx-pill <?= $configured ? 'success' : 'danger' ?>">
        <?= $configured ? 'Configured' : 'Not configured' ?>
      </span>
      <div class="jx-actions">
        <a href="<?= $settings_url ?>" class="jx-btn jx-btn-primary">Edit Settings</a>
        <a href="<?= $logs_url ?>" class="jx-btn">View Logs</a>
      </div>
    </div>

    <?php if (!empty($summary)): ?>
      <div style="display:grid;gap:10px;">
        <?php foreach ($summary as $label => $value): ?>
          <div class="jx-field" style="margin-top:0;">
            <label style="margin-bottom:4px;"><?= htmlspecialchars($label, ENT_QUOTES) ?></label>
            <div>
              <?php if ($label === 'Double Opt-In'): ?>
                <span class="jx-pill <?= $value === 'On' ? 'success' : '' ?>"><?= $value ?></span>
              <?php else: ?>
                <span class="muted"><?= htmlspecialchars((string)$value, ENT_QUOTES) ?></span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="jx-hint">No configuration saved yet. Click <strong>Edit Settings</strong> to get started.</p>
    <?php endif; ?>
  </div>
</div>
