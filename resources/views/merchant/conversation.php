<?php
/** @var array<string, mixed> $conversation */
/** @var array<int, array<string, mixed>> $messages */
?>
<p><a href='/merchant/inbox'>&larr; Back to inbox</a></p>

<h2><?= e($conversation['contact_name'] ?: $conversation['contact_phone'] ?: 'Conversation') ?></h2>
<p style='color:#64748b'>
    <?= e($conversation['channel']) ?>
    &middot; phone: <?= e((string) ($conversation['contact_phone'] ?? 'unknown')) ?>
    &middot; address: <?= e((string) ($conversation['contact_address'] ?? 'unknown')) ?>
</p>

<p>
    <?php if (($conversation['handoff_status'] ?? 'none') === 'none'): ?>
        <span style='color:#16a34a'>AI is answering</span>
        <form method='post' action='/merchant/inbox/<?= (int) $conversation['id'] ?>/handoff' style='display:inline'>
            <?= csrf_field() ?>
            <button class='btn' type='submit' style='background:#b45309'>Take over</button>
        </form>
    <?php else: ?>
        <span style='color:#b45309'>You are answering</span>
        <form method='post' action='/merchant/inbox/<?= (int) $conversation['id'] ?>/resume-ai' style='display:inline'>
            <?= csrf_field() ?>
            <button class='btn' type='submit'>Give back to AI</button>
        </form>
    <?php endif; ?>
</p>

<div class='card' style='max-height:520px;overflow:auto'>
    <?php foreach ($messages as $m): ?>
        <?php $inbound = ($m['direction'] ?? 'in') === 'in'; ?>
        <div style='margin:.6rem 0;padding:.5rem .75rem;border-radius:.5rem;background:<?= $inbound ? '#f1f5f9' : '#dbeafe' ?>;max-width:75%;<?= $inbound ? '' : 'margin-left:auto' ?>'>
            <div style='font-size:.75rem;color:#64748b'>
                <?= e($m['sender']) ?> &middot; <?= e((string) $m['created_at']) ?>
                <?php if (!empty($m['ai_provider'])): ?> &middot; <?= e((string) $m['ai_provider']) ?><?php endif; ?>
            </div>
            <div><?= nl2br(e((string) $m['body'])) ?></div>
        </div>
    <?php endforeach; ?>
    <?php if ($messages === []): ?>
        <p style='color:#64748b'>No messages yet.</p>
    <?php endif; ?>
</div>

<h3 style='margin-top:1.25rem'>Reply</h3>
<form method='post' action='/merchant/inbox/<?= (int) $conversation['id'] ?>/reply'>
    <?= csrf_field() ?>
    <textarea name='body' rows='3' style='width:100%' placeholder='Type your reply...'></textarea>
    <p><button class='btn' type='submit'>Send</button></p>
</form>
