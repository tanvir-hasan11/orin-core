<?php
/** @var array<string, mixed> $agent */
/** @var array<string, bool> $skills */
/** @var array<string, array<string, string>> $skillCatalog */
/** @var array<int, string> $alwaysOn */
/** @var array<string, string> $autonomyLevels */
/** @var array<string, string> $tones */
/** @var array<string, int> $stats */
/** @var array<int, array<string, mixed>> $runs */
/** @var int $knowledgeCount */
/** @var string|null $notice */
?>
<p><a href='/merchant/agents'>&larr; All agents</a></p>
<h2><?= e((string) $agent['name']) ?></h2>

<?php if (!empty($notice)): ?>
    <div class='card' style='border-color:#16a34a;background:#f0fdf4'><?= e($notice) ?></div>
<?php endif; ?>

<div class='cards' style='margin-bottom:1.25rem'>
    <div class='card'><div class='l'>Runs</div><div class='n'><?= number_format($stats['runs']) ?></div></div>
    <div class='card'><div class='l'>Replies sent</div><div class='n'><?= number_format($stats['replied']) ?></div></div>
    <div class='card'><div class='l'>Handed to a human</div><div class='n'><?= number_format($stats['handoffs']) ?></div></div>
    <div class='card'><div class='l'>Actions recorded</div><div class='n'><?= number_format($stats['actions']) ?></div></div>
    <div class='card'><div class='l'>Knowledge entries</div><div class='n'><?= number_format($knowledgeCount) ?></div></div>
</div>

<form method='post' action='/merchant/agents/<?= (int) $agent['id'] ?>' style='max-width:720px'>
    <?= csrf_field() ?>

    <h3>Identity</h3>
    <p><label>Name<br><input type='text' name='name' required value='<?= e((string) $agent['name']) ?>' style='width:100%'></label></p>
    <p><label>Role shown to you<br><input type='text' name='role_label' value='<?= e((string) $agent['role_label']) ?>' style='width:100%'></label></p>
    <p><label>Tone<br>
        <select name='tone' style='width:100%'>
            <?php foreach ($tones as $key => $label): ?>
                <option value='<?= e($key) ?>' <?= $key === ($agent['tone'] ?? '') ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>

    <h3>Autonomy</h3>
    <p style='color:#64748b'>This decides how much the agent is allowed to do without you.</p>
    <p><label>Autonomy level<br>
        <select name='autonomy' style='width:100%'>
            <?php foreach ($autonomyLevels as $key => $label): ?>
                <option value='<?= e($key) ?>' <?= $key === ($agent['autonomy'] ?? 'semi_auto') ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>

    <h3>Skills</h3>
    <p style='color:#64748b'>What this agent is allowed to do. Handing the thread to a human is always on and cannot be switched off.</p>
    <?php foreach ($skillCatalog as $skill => $meta): ?>
        <p style='margin:.35rem 0'>
            <label>
                <input type='checkbox' name='skills[<?= e($skill) ?>]' value='1' <?= !empty($skills[$skill]) ? 'checked' : '' ?>>
                <strong><?= e($meta['label']) ?></strong>
                <br><small style='color:#64748b'><?= e($meta['detail']) ?></small>
            </label>
        </p>
    <?php endforeach; ?>
    <?php foreach ($alwaysOn as $skill): ?>
        <p style='margin:.35rem 0'>
            <label>
                <input type='checkbox' checked disabled>
                <strong>Hand the thread to a human</strong>
                <br><small style='color:#64748b'>Always available - the agent must be able to reach you.</small>
            </label>
        </p>
    <?php endforeach; ?>

    <h3>Instructions</h3>
    <p><label>Anything else the agent must know or do<br>
        <textarea name='system_instructions' rows='4' style='width:100%' placeholder='Always mention our free delivery over 2000 taka.'><?= e((string) ($agent['system_instructions'] ?? '')) ?></textarea>
    </label></p>

    <h3>Escalation</h3>
    <p><label>Extra reasons to bring in a human<br>
        <textarea name='escalation_rules' rows='3' style='width:100%' placeholder='Wholesale enquiries, anything over 10 items.'><?= e((string) ($agent['escalation_rules'] ?? '')) ?></textarea>
    </label></p>
    <p><label>Message to send when handing over<br>
        <input type='text' name='after_hours_message' value='<?= e((string) ($agent['after_hours_message'] ?? '')) ?>' style='width:100%'>
    </label></p>

    <p style='margin-top:1rem'>
        <label><input type='checkbox' name='make_default' value='1' <?= (int) $agent['is_default'] === 1 ? 'checked disabled' : '' ?>> Make this the default agent</label>
    </p>

    <p><button class='btn' type='submit'>Save agent</button></p>
</form>

<h3 style='margin-top:2rem'>Recent runs</h3>
<table>
    <thead><tr><th>When</th><th>Decision</th><th>Tokens</th><th>Note</th></tr></thead>
    <tbody>
    <?php foreach ($runs as $run): ?>
        <tr>
            <td><?= e((string) $run['created_at']) ?></td>
            <td><?= e((string) $run['decision']) ?></td>
            <td><?= number_format((int) $run['tokens_in'] + (int) $run['tokens_out']) ?></td>
            <td><?= e((string) ($run['note'] ?? '')) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($runs === []): ?>
        <tr><td colspan='4' style='color:#64748b'>No runs recorded yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
