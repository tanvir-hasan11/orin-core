<?php
/** @var array<string, mixed> $merchant */
/** @var array<string, mixed> $usage */
/** @var array<string, int> $leadCounts */
/** @var array<string, mixed> $profile */
/** @var array<int, array<string, mixed>> $connected */
?>
<div class='cards'>
    <div class='card'><div class='l'>Plan</div><div class='n'><?= e($merchant['plan_name'] ?? 'Free') ?></div></div>
    <div class='card'><div class='l'>Business type</div><div class='n' style='font-size:1.1rem'><?= e((string) ($profile['business_type'] ?? 'generic')) ?></div></div>
    <div class='card'><div class='l'>Tokens this month</div><div class='n'><?= number_format((int) $usage['tokens_used']) ?></div></div>
    <div class='card'><div class='l'>Total calls</div><div class='n'><?= number_format((int) $usage['calls']) ?></div></div>
</div>

<h2 style='margin-top:1.5rem'>Leads by stage</h2>
<div class='cards'>
    <?php foreach ($leadCounts as $stage => $count): ?>
        <div class='card'><div class='l'><?= e((string) $stage) ?></div><div class='n'><?= number_format($count) ?></div></div>
    <?php endforeach; ?>
    <?php if ($leadCounts === []): ?>
        <div class='card'><div class='l'>No leads yet</div><div class='n'>&mdash;</div></div>
    <?php endif; ?>
</div>

<h2 style='margin-top:1.5rem'>Your setup</h2>
<ol>
    <li>Connect a channel under <a href='/merchant/channels'>Channels</a>.</li>
    <li>Describe your business under <a href='/merchant/profile'>Business Profile</a>.</li>
    <li>Teach your agent under <a href='/merchant/knowledge'>Knowledge</a>.</li>
    <li>Check its autonomy in <a href='/merchant/agents'>AI Agents</a>.</li>
</ol>
