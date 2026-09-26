<?php
/** @var array<string, int> $stats */
/** @var array<int, array<string, mixed>> $recent */
$sidebar = '<a href="/admin/dashboard">Dashboard</a>'
    . '<a href="/admin/merchants">Merchants</a>'
    . '<a href="/admin/plans">Plans</a>'
    . '<a href="/admin/subscriptions">Subscriptions</a>'
    . '<a href="/admin/providers">Providers</a>'
    . '<a href="/admin/settings">Settings</a>'
    . '<a href="/admin/audit">Audit</a>';
?>
<?php ob_start(); ?>
<div class="cards">
    <div class="card"><div class="l">Merchants</div><div class="n"><?= number_format($stats['merchants']) ?></div></div>
    <div class="card"><div class="l">Users</div><div class="n"><?= number_format($stats['users']) ?></div></div>
    <div class="card"><div class="l">Total calls</div><div class="n"><?= number_format($stats['calls']) ?></div></div>
    <div class="card"><div class="l">Tokens this month</div><div class="n"><?= number_format($stats['tokens']) ?></div></div>
</div>

<h2 style="margin-top:1.5rem">Recent merchants</h2>
<table>
    <thead><tr><th>ID</th><th>Company</th><th>Slug</th><th>Status</th><th>Created</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $row): ?>
        <tr>
            <td><?= (int) $row['id'] ?></td>
            <td><?= e($row['company_name']) ?></td>
            <td><?= e($row['slug']) ?></td>
            <td><?= e($row['status']) ?></td>
            <td><?= e($row['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($recent === []): ?>
        <tr><td colspan="5" style="color:#64748b">No merchants yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php $content = ob_get_clean(); ?>
<?= $content ?>
