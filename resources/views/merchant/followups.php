<?php
/** @var array<int, array<string, mixed>> $followups */
/** @var int $pendingCount */
?>
<h2>Follow-ups</h2>
<p style='color:#64748b'>When a customer goes quiet, your agent can ask the system to come back to them later. Those scheduled messages live here. A cron job sends the due ones.</p>

<div class='cards' style='margin-bottom:1rem'>
    <div class='card'><div class='l'>Waiting to send</div><div class='n'><?= number_format($pendingCount) ?></div></div>
</div>

<table>
    <thead><tr><th>Customer</th><th>Channel</th><th>Reason</th><th>Due</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($followups as $row): ?>
        <tr>
            <td><?= e((string) ($row['contact_name'] ?: $row['contact_phone'] ?: '-')) ?></td>
            <td><?= e((string) ($row['contact_channel'] ?? '-')) ?></td>
            <td><?= e((string) $row['reason']) ?></td>
            <td><?= e((string) $row['due_at']) ?></td>
            <td><?= e((string) $row['status']) ?></td>
            <td>
                <?php if (($row['status'] ?? '') === 'pending'): ?>
                    <form method='post' action='/merchant/followups/<?= (int) $row['id'] ?>/cancel' style='display:inline'>
                        <?= csrf_field() ?>
                        <button class='btn' type='submit' style='background:#b91c1c'>Cancel</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($followups === []): ?>
        <tr><td colspan='6' style='color:#64748b'>No follow-ups scheduled.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
