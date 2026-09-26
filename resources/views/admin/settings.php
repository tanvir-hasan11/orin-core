<?php
/** @var array<string, string> $settings */
/** @var array<int, string> $allowed */
?>
<h2>Platform Settings</h2>
<p style="color:#64748b">These are platform-wide values. Only whitelisted keys are saved.</p>

<form method="post" action="/admin/settings" style="max-width:520px">
    <?= csrf_field() ?>
    <?php foreach ($allowed as $key): ?>
        <div style="margin-bottom:.9rem">
            <label style="display:block;font-size:.85rem;color:#475569"><?= e($key) ?></label>
            <input type="text" name="<?= e($key) ?>" value="<?= e($settings[$key] ?? '') ?>" style="padding:.45rem;width:100%">
        </div>
    <?php endforeach; ?>
    <button class="btn" type="submit">Save settings</button>
</form>