<?php
/** @var array<int, array<string, mixed>> $subscriptions */
?>
<h2>Subscriptions (<?= count($subscriptions) ?>)</h2>

<table>
    <thead>
        <tr><th>ID</th><th>Merchant</th><th>Plan</th><th>Price</th><th>Status</th><th>Starts</th><th>Ends</th></tr>
    </thead>
    <tbody>
    <?php foreach ($subscriptions as $s): ?>
        <tr>
            <td><?= (int) $s['id'] ?></td>
            <td><a href="/admin/merchants/<?= (int) $s['merchant_id'] ?>"><?= e($s['company_name']) ?></a></td>
            <td><?= e($s['plan_name']) ?></td>
            <td>$<?= number_format((float) $s['price_usd'], 2) ?></td>
            <td><?= e($s['status']) ?></td>
            <td><?= e($s['starts_at']) ?></td>
            <td><?= e($s['ends_at'] ?? '—') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($subscriptions === []): ?>
        <tr><td colspan="7" style="color:#64748b">No subscriptions yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>