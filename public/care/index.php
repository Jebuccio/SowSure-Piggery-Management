<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$search = clean_text($_GET['search'] ?? '', 100);
$loadError = null;
$sows = [];

try {
    $userId = auth_user_id();
    $token = access_token();

    // Query active sows safely
    $sowsRaw = $supabase->select('sows', [
        'select' => 'id,name,tag_number,created_at',
        'owner_id' => 'eq.' . $userId,
        'order' => 'created_at.desc',
    ], $token);
    
    $sows = is_array($sowsRaw) ? $sowsRaw : [];

    // Filter by search query if typed
    if ($search !== '') {
        $searchLower = mb_strtolower($search);
        $sows = array_values(array_filter($sows, static function (array $sow) use ($searchLower): bool {
            return str_contains(mb_strtolower($sow['name'] ?? ''), $searchLower) ||
                   str_contains(mb_strtolower($sow['tag_number'] ?? ''), $searchLower);
        }));
    }

} catch (Throwable $exception) {
    error_log('Sow list failed: ' . $exception->getMessage());
    $sows = [];
    // TEMPORARY DEBUGGER: This will display the exact technical error on your screen
    $loadError = 'Database/Query Error: ' . $exception->getMessage();
}

$pageTitle = 'Sow management';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading">
    <div><span class="eyebrow">Herd records</span><h1>Sow management</h1><p>Know your herd and keep every record current.</p></div>
    <a class="button" href="<?= e(url('/sows/create.php')) ?>">Add sow</a>
</div>

<?php if ($loadError): ?>
    <div class="form-alert" role="alert" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: bold;">
        <?= e($loadError) ?>
    </div>
<?php endif; ?>

<form method="get" class="filter-bar card">
    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by name or tag number" maxlength="100">
    <button class="button button-secondary" type="submit">Filter</button>
</form>

<section class="agenda">
<?php if (!$sows && !$loadError): ?>
    <div class="empty-state card">
        <h2>No sows found</h2>
        <p>Register your first active sow to start tracking your herd.</p>
        <a class="button" href="<?= e(url('/sows/create.php')) ?>">Add sow</a>
    </div>
<?php elseif ($sows): ?>
    <div class="dashboard-list card" style="display: flex; flex-direction: column; gap: 8px; padding: 12px;">
    <?php foreach ($sows as $sow): ?>
        <a href="<?= e(url('/sows/view.php?id=' . rawurlencode($sow['id']))) ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; text-decoration: none; color: inherit;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span class="date-tile" style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 40px; height: 40px; background: var(--bg-subtle, #f1f5f9); border-radius: 6px; font-weight: bold; font-size: 14px;">
                    <strong>S</strong>
                    <small style="font-size: 9px; font-weight: normal; opacity: 0.8;">Active</small>
                </span>
                <span style="display: flex; flex-direction: column; gap: 2px;">
                    <strong style="font-size: 14px;"><?= e($sow['name']) ?></strong>
                    <small style="font-size: 12px; color: var(--text-muted, #64748b);">Tag <?= e($sow['tag_number']) ?> · Created <?= e(format_date($sow['created_at'])) ?></small>
                </span>
            </div>
            <span class="chevron" style="font-size: 18px; color: var(--text-muted, #94a3b8);">›</span>
        </a>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</section>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>