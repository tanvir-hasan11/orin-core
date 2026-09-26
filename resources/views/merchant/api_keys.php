<?php
/** @var array<string, mixed> $merchant */
/** @var array<int, array<string, mixed>> $keys */
/** @var array<string, mixed>|null $newKey */
/** @var int $activeCount */
?>
<h2>API Keys (<?= (int) $activeCount ?> active)</h2>

<?php if ($newKey !== null && isset($newKey['plain'])): ?>
    <div class="card" style="border-color:#16a34a;background:#f0fdf4">
        <strong>Copy this key now &mdash; it is shown only once.</strong>
        <p style="word-break:break-all"><code><?= e($newKey['plain']) ?></code></p>
    </div>
<?php elseif ($newKey !== null && isset($newKey['error'])): ?>
    <div class="card" style="border-color:#b91c1c;background:#fef2f2">
        <strong><?= e($newKey['error']) ?></strong>
    </div>
<?php endif; ?>

<form method="post" action="/merchant/api-keys" style="margin:1rem 0">
    <?= csrf_field() ?>
    <input type="text" name="label" placeholder="Key label (e.g. Production)" style="padding:.4rem;width:260px">
    <button class="btn" type="submit">Create key</button>
</form>

<table>
    <thead><tr><th>Label</th><th>Prefix</th><th>Last used</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($keys as $key): ?>
        <tr>
            <td><?= e($key['label']) ?></td>
            <td><code>orin_<?= e($key['key_prefix']) ?>_...</code></td>
            <td><?= e($key['last_used_at'] ?? 'never') ?></td>
            <td><?= $key['revoked_at'] === null ? 'active' : 'revoked' ?></td>
            <td>
                <?php if ($key['revoked_at'] === null): ?>
                    <form method="post" action="/merchant/api-keys/<?= (int) $key['id'] ?>/revoke" style="display:inline">
                        <?= csrf_field() ?>
                        <button class="btn" type="submit" style="background:#b91c1c">Revoke</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($keys === []): ?>
        <tr><td colspan="5" style="color:#64748b">No API keys yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
