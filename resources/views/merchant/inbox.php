<?php
/** @var array<int, array<string, mixed>> $conversations */
?>
<h2>Inbox</h2>
<p style='color:#64748b'>Every conversation your agent is handling, newest first. Open one to read it, approve a draft, or take over.</p>

<table>
    <thead><tr><th>Contact</th><th>Channel</th><th>Last message</th><th>Lead</th><th>Owner</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach ($conversations as $row): ?>
        <tr>
            <td>
                <a href='/merchant/inbox/<?= (int) $row['id'] ?>'><?= e($row['contact_name'] ?: $row['contact_phone'] ?: 'Unknown') ?></a>
                <?php if ((int) ($row['draft_count'] ?? 0) > 0): ?>
                    <small style='color:#b45309'>&middot; draft</small>
                <?php endif; ?>
            </td>
            <td><?= e($row['channel']) ?></td>
            <td style='max-width:360px;overflow:hidden;text-overflow:ellipsis'>
                <?= $row['last_direction'] === 'out' ? '<span style="color:#64748b">Agent: </span>' : '' ?><?= e(mb_substr((string) ($row['last_body'] ?? ''), 0, 90)) ?>
            </td>
            <td><?= e((string) ($row['lead_stage'] ?? '-')) ?></td>
            <td>
                <?php if (($row['handoff_status'] ?? 'none') === 'none'): ?>
                    <span style='color:#16a34a'>Agent</span>
                <?php else: ?>
                    <span style='color:#b45309'>Human</span>
                <?php endif; ?>
            </td>
            <td><?= e((string) ($row['last_message_at'] ?? '')) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($conversations === []): ?>
        <tr><td colspan='6' style='color:#64748b'>No conversations yet. Connect a channel to start receiving messages.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
