<?php
/** @var array<int, array<string, mixed>> $actions */
/** @var string $activeKind */
?>
<h2>Agent Actions</h2>
<p style='color:#64748b'>Orders, appointments, catalogue requests and delivery notes your agent recorded while talking to customers.</p>

<p>
    <a href='/merchant/actions'>All</a>
    <?php foreach (['order' => 'Orders', 'appointment' => 'Appointments', 'followup' => 'Follow-ups', 'catalogue' => 'Catalogue', 'location' => 'Delivery areas'] as $key => $label): ?>
        &middot; <a href='/merchant/actions?kind=<?= e($key) ?>'><?= e($label) ?></a>
    <?php endforeach; ?>
</p>

<table>
    <thead><tr><th>Kind</th><th>Title</th><th>Customer</th><th>Agent</th><th>Status</th><th>When</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($actions as $action): ?>
        <tr>
            <td><?= e((string) $action['kind']) ?></td>
            <td><?= e((string) $action['title']) ?></td>
            <td>
                <?= e((string) ($action['contact_name'] ?: $action['contact_phone'] ?: '-')) ?>
            </td>
            <td><?= e((string) ($action['agent_name'] ?? '-')) ?></td>
            <td><?= e((string) $action['status']) ?></td>
            <td><?= e((string) $action['created_at']) ?></td>
            <td>
                <?php if (($action['status'] ?? '') === 'pending'): ?>
                    <form method='post' action='/merchant/actions/<?= (int) $action['id'] ?>/status' style='display:inline'>
                        <?= csrf_field() ?>
                        <input type='hidden' name='status' value='confirmed'>
                        <button class='btn' type='submit'>Confirm</button>
                    </form>
                    <form method='post' action='/merchant/actions/<?= (int) $action['id'] ?>/status' style='display:inline'>
                        <?= csrf_field() ?>
                        <input type='hidden' name='status' value='cancelled'>
                        <button class='btn' type='submit' style='background:#b91c1c'>Cancel</button>
                    </form>
                <?php elseif (($action['status'] ?? '') === 'confirmed'): ?>
                    <form method='post' action='/merchant/actions/<?= (int) $action['id'] ?>/status' style='display:inline'>
                        <?= csrf_field() ?>
                        <input type='hidden' name='status' value='done'>
                        <button class='btn' type='submit' style='background:#16a34a'>Mark done</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($actions === []): ?>
        <tr><td colspan='7' style='color:#64748b'>Your agent has not recorded anything yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
