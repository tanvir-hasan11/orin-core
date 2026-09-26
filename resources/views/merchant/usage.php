<?php
/** @var array<string, mixed> $summary */
/** @var array<int, array<string, mixed>> $byProvider */
/** @var array<int, array<string, mixed>> $recent */
?>
<div class="cards">
    <div class="card"><div class="l">Period</div><div class="n"><?= e($summary['period']) ?></div></div>
    <div class="card"><div class="l">Tokens used</div><div class="n"><?= number_format((int) $summary['tokens_used']) ?></div></div>
    <div class="card"><div class="l">Cost</div><div class="n">$<?= number_format((float) $summary['cost_usd'], 4) ?></div></div>
    <div class="card"><div class="l">Total calls</div><div class="n"><?= number_format((int) $summary['calls']) ?></div></div>
</div>

<h2 style="margin-top:1.5rem">By provider</h2>
<table>
    <thead><tr><th>Provider</th><th>Calls</th><th>Tokens</th><th>Cost</th></tr></thead>
    <tbody>
    <?php foreach ($byProvider as $row): ?>
        <tr>
            <td><?= e($row['provider']) ?></td>
            <td><?= number_format((int) $row['calls']) ?></td>
            <td><?= number_format((int) $row['tokens']) ?></td>
            <td>$<?= number_format((float) $row['cost'], 4) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($byProvider === []): ?>
        <tr><td colspan="4" style="color:#64748b">No usage recorded yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h2 style="margin-top:1.5rem">Recent calls</h2>
<table>
    <thead><tr><th>When</th><th>Provider</th><th>Model</th><th>Tokens</th><th>Cost</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $row): ?>
        <tr>
            <td><?= e($row['created_at']) ?></td>
            <td><?= e($row['provider']) ?></td>
            <td><?= e($row['model']) ?></td>
            <td><?= number_format((int) $row['prompt_tokens'] + (int) $row['completion_tokens']) ?></td>
            <td>$<?= number_format((float) $row['cost_usd'], 4) ?></td>
            <td><?= e($row['status']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($recent === []): ?>
        <tr><td colspan="6" style="color:#64748b">No calls yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
