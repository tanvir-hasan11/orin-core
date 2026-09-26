<?php
/** @var array<int, array<string, mixed>> $entries */
/** @var array<string, string> $kinds */
/** @var array<int, array<string, mixed>> $agents */
/** @var string|null $notice */
?>
<h2>Knowledge</h2>
<p style='color:#64748b'>Everything you put here, your agent may state as fact. Anything that is not here and not in your business profile, it will refuse to guess.</p>

<?php if (!empty($notice)): ?>
    <div class='card' style='border-color:#16a34a;background:#f0fdf4'><?= e($notice) ?></div>
<?php endif; ?>

<h3>Add an entry</h3>
<form method='post' action='/merchant/knowledge' style='max-width:720px'>
    <?= csrf_field() ?>
    <p><label>Kind<br>
        <select name='kind' style='width:100%'>
            <?php foreach ($kinds as $key => $label): ?>
                <option value='<?= e($key) ?>'><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p><label>Title<br><input type='text' name='title' required style='width:100%' placeholder='Delivery charge outside Dhaka'></label></p>
    <p><label>Content<br><textarea name='body' rows='4' required style='width:100%'></textarea></label></p>
    <p><label>Keywords (helps matching)<br><input type='text' name='keywords' style='width:100%' placeholder='delivery, courier, charge, shipping'></label></p>
    <p><label>Only for this agent<br>
        <select name='agent_id' style='width:100%'>
            <option value=''>Every agent</option>
            <?php foreach ($agents as $agent): ?>
                <option value='<?= (int) $agent['id'] ?>'><?= e((string) $agent['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p><button class='btn' type='submit'>Add entry</button></p>
</form>

<h3 style='margin-top:2rem'>Entries (<?= count($entries) ?>)</h3>
<table>
    <thead><tr><th>Kind</th><th>Title</th><th>Scope</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($entries as $entry): ?>
        <tr>
            <td><?= e($kinds[(string) $entry['kind']] ?? (string) $entry['kind']) ?></td>
            <td>
                <strong><?= e((string) $entry['title']) ?></strong><br>
                <small style='color:#64748b'><?= e(mb_substr((string) $entry['body'], 0, 120)) ?></small>
            </td>
            <td><?= e((string) ($entry['agent_name'] ?? 'Every agent')) ?></td>
            <td><?= (int) $entry['is_active'] === 1 ? 'active' : 'hidden' ?></td>
            <td>
                <form method='post' action='/merchant/knowledge/<?= (int) $entry['id'] ?>/toggle' style='display:inline'>
                    <?= csrf_field() ?>
                    <button class='btn' type='submit' style='background:#64748b'><?= (int) $entry['is_active'] === 1 ? 'Hide' : 'Show' ?></button>
                </form>
                <form method='post' action='/merchant/knowledge/<?= (int) $entry['id'] ?>/delete' style='display:inline'>
                    <?= csrf_field() ?>
                    <button class='btn' type='submit' style='background:#b91c1c'>Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($entries === []): ?>
        <tr><td colspan='5' style='color:#64748b'>Nothing taught yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
