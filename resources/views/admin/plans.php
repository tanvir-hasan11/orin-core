<?php
/** @var array<int, array<string, mixed>> $plans */
/** @var array<int, int> $merchantCounts */
?>
<h2>Plans</h2>

<table>
    <thead>
        <tr><th>ID</th><th>Name</th><th>Code</th><th>Price</th><th>Token quota</th><th>Max keys</th><th>Merchants</th><th>Active</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($plans as $plan): ?>
        <tr>
            <form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>">
                <?= csrf_field() ?>
                <td><?= (int) $plan['id'] ?></td>
                <td><input type="text" name="name" value="<?= e($plan['name']) ?>" style="padding:.3rem;width:120px"></td>
                <td><code><?= e($plan['code']) ?></code></td>
                <td><input type="number" step="0.01" name="price_usd" value="<?= e($plan['price_usd']) ?>" style="padding:.3rem;width:90px"></td>
                <td><input type="number" name="monthly_token_quota" value="<?= (int) $plan['monthly_token_quota'] ?>" style="padding:.3rem;width:120px"></td>
                <td><input type="number" name="max_api_keys" value="<?= (int) $plan['max_api_keys'] ?>" style="padding:.3rem;width:70px"></td>
                <td><?= (int) ($merchantCounts[(int) $plan['id']] ?? 0) ?></td>
                <td>
                    <select name="is_active" style="padding:.3rem">
                        <option value="1" <?= (int) $plan['is_active'] === 1 ? 'selected' : '' ?>>yes</option>
                        <option value="0" <?= (int) $plan['is_active'] === 0 ? 'selected' : '' ?>>no</option>
                    </select>
                </td>
                <td><button class="btn" type="submit">Save</button></td>
            </form>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h3 style="margin-top:1.5rem">Add a plan</h3>
<form method="post" action="/admin/plans" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
    <?= csrf_field() ?>
    <input type="text" name="name" placeholder="Name" required style="padding:.4rem">
    <input type="text" name="code" placeholder="code_lowercase" required style="padding:.4rem">
    <input type="number" step="0.01" name="price_usd" placeholder="Price USD" value="0" style="padding:.4rem;width:110px">
    <input type="number" name="monthly_token_quota" placeholder="Token quota" value="100000" style="padding:.4rem;width:130px">
    <input type="number" name="max_api_keys" placeholder="Max keys" value="3" style="padding:.4rem;width:90px">
    <button class="btn" type="submit">Create plan</button>
</form>