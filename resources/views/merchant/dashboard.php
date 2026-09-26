<?php
/** @var array<string, mixed> $merchant */
/** @var array<string, mixed> $usage */

$sidebar = '<a href="/merchant/dashboard">Dashboard</a>'
    . '<a href="/merchant/api-keys">API Keys</a>'
    . '<a href="/merchant/usage">Usage</a>'
    . '<a href="/merchant/providers">Providers</a>'
    . '<a href="/merchant/billing">Billing</a>'
    . '<a href="/merchant/settings">Settings</a>';
?>
<div class="cards">
    <div class="card"><div class="l">Plan</div><div class="n"><?= e($merchant['plan_name'] ?? 'Free') ?></div></div>
    <div class="card"><div class="l">Company</div><div class="n" style="font-size:1.1rem"><?= e($merchant['company_name'] ?? '') ?></div></div>
    <div class="card"><div class="l">Tokens this month (<?= e($usage['period']) ?>)</div><div class="n"><?= number_format((int) $usage['tokens_used']) ?></div></div>
    <div class="card"><div class="l">Estimated cost</div><div class="n">$<?= number_format((float) $usage['cost_usd'], 4) ?></div></div>
    <div class="card"><div class="l">Total calls</div><div class="n"><?= number_format((int) $usage['calls']) ?></div></div>
</div>

<h2 style="margin-top:1.5rem">Getting started</h2>
<ol>
    <li>Create your first API key under <a href="/merchant/api-keys">API Keys</a>.</li>
    <li>Configure providers under <a href="/merchant/providers">Providers</a>.</li>
    <li>Call <code>POST /api/v1/complete</code> with your key.</li>
</ol>
