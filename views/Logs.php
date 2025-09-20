<?php
// $rows is provided by the route (array of latest logs)
?>
<div class="box">
  <h1>JSubscriberX Logs</h1>

  <table class="grid">
    <thead>
      <tr>
        <th>Time</th>
        <th>Email</th>
        <th>HTTP</th>
        <th>Status</th>
        <th>Action</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6">No log entries yet.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['created_at'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['email'] ?? '', ENT_QUOTES) ?></td>
            <td><?= (int)($r['http_code'] ?? 0) ?></td>
            <td><?= htmlspecialchars($r['status'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['action'] ?? '', ENT_QUOTES) ?></td>
            <td>
              <?php
                $resp = $r['response_json'] ?? '';
                if ($resp && strlen($resp) > 140) {
                  $short = htmlspecialchars(substr($resp, 0, 140), ENT_QUOTES) . '…';
                } else {
                  $short = htmlspecialchars($resp, ENT_QUOTES);
                }
              ?>
              <details>
                <summary><?= $short ?: 'view' ?></summary>
                <pre style="white-space:pre-wrap"><?= htmlspecialchars($resp, ENT_QUOTES) ?></pre>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
