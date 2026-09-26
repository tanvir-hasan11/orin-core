<?php
/** @var array<int, string> $providers */
/** @var array<string, array<string, mixed>> $settings */
?>
<h2>AI Providers</h2>
<p style="color:#64748b">Enable the providers you want available to your API keys and set a default model per provider.</p>

<form method="post" action="/merchant/providers">
    <?= csrf_field() ?>
    <table>
        <thead><tr><th>Provider</th><th>Enabled</th><th>Default model</th></tr></thead>
        <tbody>
        <?php foreach ($providers as $provider): ?>
            <?php $row = $settings[$provider] ?? []; ?>
            <tr>
                <td style="text-transform:capitalize"><?= e($provider) ?></td>
                <td>
                    <input type="checkbox" name="<?= e($provider) ?>_enabled" value="1"
                        <?= ((int) ($row['enabled'] ?? 1) === 1) ? 'checked' : '' ?>>
                </td>
                <td>
                    <input type="text" name="<?= e($provider) ?>_model"
                        value="<?= e($row['default_model'] ?? '') ?>"
                        placeholder="leave blank for platform default" style="width:260px;padding:.3rem">
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top:1rem"><button class="btn" type="submit">Save providers</button></p>
</form>
