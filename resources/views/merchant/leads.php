<?php
/** @var array<int, array<string, mixed>> $leads */
/** @var array<string, int> $counts */
/** @var array<string, string> $stages */
/** @var string $activeStage */
?>
<h2>Leads</h2>

<div class='cards' style='margin-bottom:1rem'>
    <?php foreach ($stages as $key => $label): ?>
        <div class='card'>
            <div class='l'><?= e($label) ?></div>
            <div class='n'><?= number_format((int) ($counts[$key] ?? 0)) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<p>
    <a href='/merchant/leads'>All</a>
    <?php foreach ($stages as $key => $label): ?>
        &middot; <a href='/merchant/leads?stage=<?= e($key) ?>'><?= e($label) ?></a>
    <?php endforeach; ?>
</p>

<table>
    <thead><tr><th>Contact</th><th>Phone</th><th>Channel</th><th>Interest</th><th>Stage</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($leads as $lead): ?>
        <tr>
            <td>
                <?php if (!empty($lead['contact_id'])): ?>
                    <a href='/merchant/inbox/'><?= e((string) ($lead['name'] ?? 'Unknown')) ?></a>
                <?php else: ?>
                    <?= e((string) ($lead['name'] ?? 'Unknown')) ?>
                <?php endif; ?>
            </td>
            <td><?= e((string) ($lead['phone'] ?? '-')) ?></td>
            <td><?= e((string) ($lead['channel'] ?? '-')) ?></td>
            <td><?= e((string) ($lead['interest'] ?? '-')) ?></td>
            <td>
                <form method='post' action='/merchant/leads/<?= (int) $lead['id'] ?>/stage' style='display:flex;gap:.35rem'>
                    <?= csrf_field() ?>
                    <select name='stage'>
                        <?php foreach ($stages as $key => $label): ?>
                            <option value='<?= e($key) ?>' <?= $key === ($lead['stage'] ?? '') ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class='btn' type='submit'>Save</button>
                </form>
            </td>
            <td><?= e((string) ($lead['updated_at'] ?? '')) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($leads === []): ?>
        <tr><td colspan='6' style='color:#64748b'>No leads yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
