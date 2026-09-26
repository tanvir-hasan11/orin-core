<?php
/** @var array<string, mixed> $merchant */
/** @var array<int, array<string, mixed>> $subscriptions */
/** @var array<int, array<string, mixed>> $plans */
?>
<p><a href="/admin/merchants">&larr; All merchants</a></p>

<h2><?= e($merchant['company_name']) ?></h2>
<p style="color:#64748b">
    <?= e($merchant['owner_name']) ?> &middot; <?= e($merchant['owner_email']) ?>
    &middot; slug: <code><?= e($merchant['slug']) ?></code>
</p>

<div class="cards">
    <div class="card"><div class="l">Status</div><div class="n"><?= e($merchant['status']) ?></div></div>
    <div class="card"><div class="l">Plan</div><div class="n"><?= e($merchant['plan_name'] ?? '—') ?></div></div>
    <div class="card"><div class="l">Created</div><div class="n" style="font-size:1rem"><?= e($merchant['created_at']) ?></div></div>
</div>

<h3 style="margin-top:1.5rem">Change status</h3>
<form method="post" action="/admin/merchants/<?= (int) $merchant['id'] ?>/status">
    <?= csrf_field() ?>
    <select name="status" style="padding:.4rem">
        <?php foreach (['trial', 'active', 'suspended'] as $status): ?>
            <option value="<?= $status ?>" <?= $merchant['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Save status</button>
</form>

<h3 style="margin-top:1.5rem">Assign plan</h3>
<form method="post" action="/admin/merchants/<?= (int) $merchant['id'] ?>/plan">
    <?= csrf_field() ?>
    <select name="plan_id" style="padding:.4rem">
        <?php foreach ($plans as $plan): ?>
            <option value="<?= (int) $plan['id'] ?>" <?= (int) ($merchant['plan_id'] ?? 0) === (int) $plan['id'] ? 'selected' : '' ?>>
                <?= e($plan['name']) ?> — $<?= number_format((float) $plan['price_usd'], 2) ?>/mo
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Assign plan</button>
</form>

<h3 style="margin-top:1.5rem">Subscription history</h3>
<table>
    <thead><tr><th>ID</th><th>Plan</th><th>Status</th><th>Starts</th><th>Ends</th></tr></thead>
    <tbody>
    <?php foreach ($subscriptions as $s): ?>
        <tr>
            <td><?= (int) $s['id'] ?></td>
            <td><?= e($s['plan_name']) ?></td>
            <td><?= e($s['status']) ?></td>
            <td><?= e($s['starts_at']) ?></td>
            <td><?= e($s['ends_at'] ?? '—') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($subscriptions === []): ?>
        <tr><td colspan="5" style="color:#64748b">No subscriptions yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>