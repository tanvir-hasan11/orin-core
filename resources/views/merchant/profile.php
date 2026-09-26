<?php
/** @var array<string, mixed> $profile */
/** @var array<string, array<string, mixed>> $verticals */
/** @var bool $saved */
?>
<h2>Business Profile</h2>
<p style='color:#64748b'>This is what ORIN uses to decide how to answer. The more accurate it is, the fewer mistakes the AI makes.</p>

<?php if ($saved): ?>
    <div class='card' style='border-color:#16a34a;background:#f0fdf4'>Saved.</div>
<?php endif; ?>

<form method='post' action='/merchant/profile' style='max-width:640px'>
    <?= csrf_field() ?>

    <p><label>Business type<br>
        <select name='business_type' style='width:100%'>
            <?php foreach ($verticals as $key => $vertical): ?>
                <option value='<?= e($key) ?>' <?= $key === ($profile['business_type'] ?? '') ? 'selected' : '' ?>>
                    <?= e((string) ($vertical['label'] ?? $key)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label></p>

    <p><label>What you sell, in one or two sentences<br>
        <textarea name='description' rows='3' style='width:100%'><?= e((string) ($profile['description'] ?? '')) ?></textarea>
    </label></p>

    <p><label>Tone<br>
        <select name='tone' style='width:100%'>
            <?php foreach (['friendly' => 'Friendly', 'formal' => 'Formal', 'concise' => 'Short and direct'] as $key => $label): ?>
                <option value='<?= e($key) ?>' <?= $key === ($profile['tone'] ?? 'friendly') ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>

    <p><label>Opening message<br>
        <input type='text' name='greeting' value='<?= e((string) ($profile['greeting'] ?? '')) ?>' style='width:100%'>
    </label></p>

    <p><label>Working hours<br>
        <input type='text' name='working_hours' value='<?= e((string) ($profile['working_hours'] ?? '')) ?>' placeholder='Sat-Thu, 10am to 8pm' style='width:100%'>
    </label></p>

    <p><label>Delivery charge and time<br>
        <textarea name='delivery_info' rows='2' style='width:100%'><?= e((string) ($profile['delivery_info'] ?? '')) ?></textarea>
    </label></p>

    <p><label>Service area<br>
        <input type='text' name='service_area' value='<?= e((string) ($profile['service_area'] ?? '')) ?>' placeholder='Dhaka, Chattogram' style='width:100%'>
    </label></p>

    <p><button class='btn' type='submit'>Save profile</button></p>
</form>
