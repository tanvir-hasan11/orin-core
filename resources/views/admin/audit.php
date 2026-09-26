<?php
/** @var array<int, array<string, mixed>> $logs */
/** @var string $action */
?>
<h2>Audit Log</h2>

<form method="get" action="/admin/audit" style="margin:1rem 0">
    <input type="text" name="action" value="<?= e($action) ?>" placeholder="filter by action prefix (e.g. merchant.)" style="padding:.4rem;width:300px">
    <button class="btn" type="submit">Filter</button>
    <?php if ($action !== ''): ?>
        <a class="btn" href="/admin/audit" style="background:#64748b">Clear</a>
    <?php endif; ?>
</form>

<table>
    <thead>
        <tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>IP</th><th>Meta</th></tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= e($log['created_at']) ?></td>
            <td><?= e($log['actor_email'] ?? ($log['actor_role'] ?? 'system')) ?></td>
            <td><code><?= e($log['action']) ?></code></td>
            <td><?= e(($log['target_type'] ?? '') . ($log['target_id'] !== null ? '#' . $log['target_id'] : '')) ?></td>
            <td><?= e($log['ip'] ?? '—') ?></td>
            <td><small><?= e($log['meta'] ?? '') ?></small></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($logs === []): ?>
        <tr><td colspan="6" style="color:#64748b">No audit entries.</td></tr>
    <?php endif; ?>
    </tbody>
</table>