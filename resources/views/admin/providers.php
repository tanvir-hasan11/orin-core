<?php
/** @var array<int, array<string, mixed>> $providers */
/** @var array<string, array<string, mixed>> $configured */
/** @var array<int, string> $known */
?>
<h2>Platform AI Providers</h2>
<p style="color:#64748b">
    Merchants never choose a provider. The engine walks this list by priority and uses the first
    enabled provider that answers. Keys are stored encrypted; leave the box empty to keep the
    existing key.
</p>

<table>
    <thead>
        <tr><th>Provider</th><th>Enabled</th><th>Default model</th><th>Priority</th><th>Key</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($known as $name): ?>
        <?php $row = $configured[$name] ?? null; ?>
        <tr>
            <form method="post" action="/admin/providers">
                <?= csrf_field() ?>
                <input type="hidden" name="provider" value="<?= e($name) ?>">
                <td><strong><?= e($name) ?></strong></td>
                <td>
                    <select name="enabled" style="padding:.3rem">
                        <option value="1" <?= $row !== null && (int) $row['enabled'] === 1 ? 'selected' : '' ?>>on</option>
                        <option value="0" <?= $row === null || (int) $row['enabled'] === 0 ? 'selected' : '' ?>>off</option>
                    </select>
                </td>
                <td><input type="text" name="default_model" value="<?= e($row['default_model'] ?? '') ?>" style="padding:.3rem;width:180px"></td>
                <td><input type="number" name="priority" value="<?= (int) ($row['priority'] ?? 0) ?>" style="padding:.3rem;width:80px"></td>
                <td>
                    <?php if ($row !== null && !empty($row['api_key'])): ?>
                        <span style="color:#16a34a">configured</span>
                    <?php else: ?>
                        <span style="color:#b91c1c">not set</span>
                    <?php endif; ?>
                    <input type="password" name="api_key" placeholder="new key (optional)" autocomplete="new-password" style="padding:.3rem;width:200px">
                </td>
                <td><button class="btn" type="submit">Save</button></td>
            </form>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>