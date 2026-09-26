<?php
/** @var array<int, array<string, mixed>> $agents */
/** @var array<string, string> $autonomyLevels */
/** @var int $maxAgents */
/** @var string|null $notice */
?>
<h2>AI Agents</h2>
<p style='color:#64748b'>Your agent is the member of staff that answers your WhatsApp and Messenger around the clock. Teach it your business, decide what it may do on its own, and it handles the rest.</p>

<?php if (!empty($notice)): ?>
    <div class='card' style='border-color:#16a34a;background:#f0fdf4'><?= e($notice) ?></div>
<?php endif; ?>

<table>
    <thead><tr><th>Agent</th><th>Autonomy</th><th>Knowledge</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($agents as $agent): ?>
        <tr>
            <td>
                <strong><?= e((string) $agent['name']) ?></strong>
                <?php if ((int) $agent['is_default'] === 1): ?>
                    <small style='color:#16a34a'>&middot; default</small>
                <?php endif; ?>
                <br><small style='color:#64748b'><?= e((string) $agent['role_label']) ?></small>
            </td>
            <td><?= e($autonomyLevels[(string) $agent['autonomy']] ?? (string) $agent['autonomy']) ?></td>
            <td><?= number_format((int) ($agent['knowledge_count'] ?? 0)) ?> entries</td>
            <td><?= e((string) $agent['status']) ?></td>
            <td>
                <a class='btn' href='/merchant/agents/<?= (int) $agent['id'] ?>/edit'>Open</a>
                <?php if ((int) $agent['is_default'] !== 1): ?>
                    <form method='post' action='/merchant/agents/<?= (int) $agent['id'] ?>/status' style='display:inline'>
                        <?= csrf_field() ?>
                        <input type='hidden' name='status' value='<?= ($agent['status'] ?? '') === 'active' ? 'paused' : 'active' ?>'>
                        <button class='btn' type='submit' style='background:#64748b'><?= ($agent['status'] ?? '') === 'active' ? 'Pause' : 'Resume' ?></button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($agents === []): ?>
        <tr><td colspan='5' style='color:#64748b'>No agent yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if (count($agents) < $maxAgents): ?>
    <h3 style='margin-top:1.5rem'>Add another agent</h3>
    <p style='color:#64748b'>Useful when one agent should sell and another should handle support.</p>
    <form method='post' action='/merchant/agents' style='max-width:520px'>
        <?= csrf_field() ?>
        <p><label>Name<br><input type='text' name='name' required placeholder='Support Assistant' style='width:100%'></label></p>
        <p><label>Role shown to you<br><input type='text' name='role_label' value='Sales Assistant' style='width:100%'></label></p>
        <p><label>Autonomy<br>
            <select name='autonomy' style='width:100%'>
                <?php foreach ($autonomyLevels as $key => $label): ?>
                    <option value='<?= e($key) ?>' <?= $key === 'semi_auto' ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label></p>
        <p><button class='btn' type='submit'>Create agent</button></p>
    </form>
<?php else: ?>
    <p style='color:#64748b;margin-top:1.5rem'>Your plan allows <?= (int) $maxAgents ?> agent(s). Upgrade to add more.</p>
<?php endif; ?>
