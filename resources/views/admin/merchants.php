<?php
/** @var array<int, array<string, mixed>> $merchants */
?>
<h2>Merchants (<?= count($merchants) ?>)</h2>

<table>
    <thead>
        <tr><th>ID</th><th>Company</th><th>Owner</th><th>Plan</th><th>Status</th><th>Created</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($merchants as $m): ?>
        <tr>
            <td><?= (int) $m['id'] ?></td>
            <td><?= e($m['company_name']) ?></td>
            <td><?= e($m['owner_name']) ?><br><small style="color:#64748b"><?= e($m['owner_email']) ?></small></td>
            <td><?= e($m['plan_name'] ?? '—') ?></td>
            <td><?= e($m['status']) ?></td>
            <td><?= e($m['created_at']) ?></td>
            <td><a class="btn" href="/admin/merchants/<?= (int) $m['id'] ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($merchants === []): ?>
        <tr><td colspan="7" style="color:#64748b">No merchants yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>