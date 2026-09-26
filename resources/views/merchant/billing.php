<?php
/** @var array<string, mixed> $merchant */
/** @var array<int, array<string, mixed>> $invoices */
/** @var array<int, array<string, mixed>> $plans */
?>
<h2>Current plan</h2>
<div class="cards">
    <div class="card"><div class="l">Plan</div><div class="n"><?= e($merchant['plan_name'] ?? 'Free') ?></div></div>
    <div class="card"><div class="l">Price</div><div class="n">$<?= number_format((float) ($merchant['price_usd'] ?? 0), 2) ?></div></div>
    <div class="card"><div class="l">Monthly quota</div><div class="n"><?= number_format((int) ($merchant['monthly_token_quota'] ?? 0)) ?></div></div>
</div>

<h2 style="margin-top:1.5rem">Available plans</h2>
<table>
    <thead><tr><th>Plan</th><th>Price/mo</th><th>Token quota</th></tr></thead>
    <tbody>
    <?php foreach ($plans as $plan): ?>
        <tr>
            <td><?= e($plan['name']) ?></td>
            <td>$<?= number_format((float) $plan['price_usd'], 2) ?></td>
            <td><?= number_format((int) $plan['monthly_token_quota']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p style="color:#64748b">Plan upgrades are handled manually for now. Contact support to change plans.</p>

<h2 style="margin-top:1.5rem">Invoices</h2>
<table>
    <thead><tr><th>Period</th><th>Amount</th><th>Status</th><th>Issued</th></tr></thead>
    <tbody>
    <?php foreach ($invoices as $invoice): ?>
        <tr>
            <td><?= e($invoice['period']) ?></td>
            <td>$<?= number_format((float) $invoice['amount_usd'], 2) ?></td>
            <td><?= e($invoice['status']) ?></td>
            <td><?= e($invoice['issued_at'] ?? '-') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($invoices === []): ?>
        <tr><td colspan="4" style="color:#64748b">No invoices yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
