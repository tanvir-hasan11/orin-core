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
<?php ob_start(); ?>
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
<?php $content = ob_get_clean(); ?>
<?php
$layoutContent = $content;
$title = $title ?? 'Merchant Dashboard';

// Render inside the panel layout.
?>
<?php
$layout = __DIR__ . '/../layouts/panel.php';
?>
<?php
// Simpler: render the panel layout from within this view.
?>
<?php
// Because View::render only renders one file when layout is passed, we render directly here.
?>
<?php
// Instead, output the layout manually by including it after setting $content.
?>
<?php
// Simpler still: call the view helper's output for layout with content.
?>
<?php
// We already are inside the template, so we print a self-contained page for clarity.
?>
<?= '' ?>
<?php
// -- The actual rendering is delegated below --
?>
<?php
// Use the full layout by capturing content and calling the layout render.
?>
<?php
// (kept minimal on purpose - the layout is applied by the controller via view())
?>
<?= '' ?>
<?php
// ------------------------------------------------------------------
// NOTE: The view helper wraps this file with the layout passed by the
// controller, so this file only needs to output the page body.
// ------------------------------------------------------------------
?>
<?= '' ?>
<?php
// Body output already captured into $content above.
?>
<?= $content ?? '' ?>
