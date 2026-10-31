<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$search = clean_text($_GET['q'] ?? '', 80);
$status = clean_text($_GET['status'] ?? '', 20);
$allowedStatuses = ['healthy', 'breeding', 'gestating', 'isolated'];
$query = [
    'select' => 'id,name,tag_number,status,notes,updated_at',
    'owner_id' => 'eq.' . auth_user_id(),
    'archived_at' => 'is.null',
    'order' => 'name.asc',
];
if ($search !== '') {
    $safeSearch = str_replace(['*', ',', '(', ')'], '', $search);
    $query['or'] = '(name.ilike.*' . $safeSearch . '*,tag_number.ilike.*' . $safeSearch . '*)';
}
if (in_array($status, $allowedStatuses, true)) {
    $query['status'] = 'eq.' . $status;
}

$loadError = null;
try {
    $sows = $supabase->select('sows', $query, access_token());
} catch (Throwable $exception) {
    error_log('Sow list failed: ' . $exception->getMessage());
    $sows = [];
    $loadError = 'We could not load your sow records. Please try again.';
}

$pageTitle = 'Sow management';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading">
    <div><span class="eyebrow">Herd records</span><h1>Sow management</h1><p>Know your herd and keep every record current.</p></div>
    <a class="button" href="<?= e(url('/sows/create.php')) ?>">Add sow</a>
</div>
<?php if ($loadError): ?><div class="form-alert" role="alert"><?= e($loadError) ?></div><?php endif; ?>
<section class="toolbar card">
    <form method="get" class="filter-form">
        <label class="search-field"><span class="sr-only">Search sows</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by name or tag number"></label>
        <label><span class="sr-only">Filter by status</span>
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="button button-secondary" type="submit">Filter</button>
        <?php if ($search !== '' || $status !== ''): ?><a class="text-link" href="<?= e(url('/sows/index.php')) ?>">Clear</a><?php endif; ?>
    </form>
</section>
<?php if (!$sows && !$loadError): ?>
    <section class="empty-state card">
        <span class="empty-icon"><?= nav_icon('sow') ?></span>
        <h2><?= $search !== '' || $status !== '' ? 'No matching sows' : 'Start your herd records' ?></h2>
        <p><?= $search !== '' || $status !== '' ? 'Try a different name, tag, or status.' : 'Add your first sow to begin tracking breeding and care.' ?></p>
        <a class="button" href="<?= e($search !== '' || $status !== '' ? url('/sows/index.php') : url('/sows/create.php')) ?>"><?= $search !== '' || $status !== '' ? 'Clear filters' : 'Add first sow' ?></a>
    </section>
<?php else: ?>
    <div class="record-summary"><strong><?= count($sows) ?></strong> <?= count($sows) === 1 ? 'sow' : 'sows' ?> shown</div>
    <section class="record-grid">
        <?php foreach ($sows as $sow): ?>
            <article class="record-card">
                <div class="record-card-top">
                    <span class="animal-avatar"><?= e(strtoupper(mb_substr($sow['name'], 0, 1))) ?></span>
                    <span class="status status-<?= e($sow['status']) ?>"><?= e(ucfirst($sow['status'])) ?></span>
                </div>
                <h2><?= e($sow['name']) ?></h2>
                <p class="record-meta">Tag <?= e($sow['tag_number']) ?></p>
                <p class="record-note"><?= e($sow['notes'] ?: 'No care notes added yet.') ?></p>
                <div class="record-actions">
                    <a href="<?= e(url('/sows/view.php?id=' . rawurlencode($sow['id']))) ?>">View record</a>
                    <a href="<?= e(url('/sows/edit.php?id=' . rawurlencode($sow['id']))) ?>">Edit</a>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
