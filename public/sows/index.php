<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$search = clean_text($_GET['q'] ?? '', 80);
$status = clean_text($_GET['status'] ?? '', 20);
$view = ($_GET['view'] ?? 'active') === 'archived' ? 'archived' : 'active';
$allowedStatuses = ['healthy', 'breeding', 'gestating', 'isolated'];

// Handle Restore & Permanent Delete actions directly from Sow Management cards
if (method_is('POST')) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $targetSowId = trim((string) ($_POST['sow_id'] ?? ''));
    $userId = auth_user_id();
    $token = access_token();

    if ($targetSowId === '') {
        flash('error', 'Missing sow identifier.');
        redirect('/sows/index.php?view=' . rawurlencode($view));
    }

    try {
        if ($action === 'restore_sow') {
            $supabase->update('sows', [
                'id' => 'eq.' . $targetSowId,
                'owner_id' => 'eq.' . $userId,
            ], [
                'archived_at' => null,
            ], $token);

            flash('success', 'Sow restored to your active herd.');
            redirect('/sows/index.php?view=active');
        }

        if ($action === 'delete_permanently') {
            $supabase->delete('sows', [
                'id' => 'eq.' . $targetSowId,
                'owner_id' => 'eq.' . $userId,
            ], $token);

            flash('success', 'Sow record permanently deleted.');
            redirect('/sows/index.php?view=' . rawurlencode($view));
        }
    } catch (Throwable $exception) {
        error_log('Sow index action failed (' . $action . '): ' . $exception->getMessage());
        flash('error', $exception->getMessage());
        redirect('/sows/index.php?view=' . rawurlencode($view));
    }
}

$query = [
    'select' => 'id,name,tag_number,status,photo_url,notes,updated_at,archived_at',
    'owner_id' => 'eq.' . auth_user_id(),
    'archived_at' => $view === 'archived' ? 'not.is.null' : 'is.null',
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
    <div class="button-row">
        <a class="button <?= $view === 'active' ? '' : 'button-secondary' ?>" href="<?= e(url('/sows/index.php?view=active')) ?>">Active</a>
        <a class="button <?= $view === 'archived' ? '' : 'button-secondary' ?>" href="<?= e(url('/sows/index.php?view=archived')) ?>">Archived</a>
        <a class="button" href="<?= e(url('/sows/create.php')) ?>">Add sow</a>
    </div>
</div>
<?php if ($loadError): ?><div class="form-alert" role="alert"><?= e($loadError) ?></div><?php endif; ?>
<section class="toolbar card">
    <form method="get" class="filter-form">
        <input type="hidden" name="view" value="<?= e($view) ?>">
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
        <?php if ($search !== '' || $status !== ''): ?><a class="text-link" href="<?= e(url('/sows/index.php?view=' . rawurlencode($view))) ?>">Clear</a><?php endif; ?>
    </form>
</section>
<?php if (!$sows && !$loadError): ?>
    <section class="empty-state card">
        <span class="empty-icon"><?= nav_icon('sow') ?></span>
        <h2><?= $search !== '' || $status !== '' ? 'No matching sows' : ($view === 'archived' ? 'No archived sows' : 'Start your herd records') ?></h2>
        <p><?= $search !== '' || $status !== '' ? 'Try a different name, tag, or status.' : ($view === 'archived' ? 'Archived sows will appear here.' : 'Add your first sow to begin tracking breeding and care.') ?></p>
        <?php if ($view === 'active' || $search !== '' || $status !== ''): ?>
            <a class="button" href="<?= e($search !== '' || $status !== '' ? url('/sows/index.php?view=' . rawurlencode($view)) : url('/sows/create.php')) ?>"><?= $search !== '' || $status !== '' ? 'Clear filters' : 'Add first sow' ?></a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <div class="record-summary"><strong><?= count($sows) ?></strong> <?= $view === 'archived' ? 'archived ' : '' ?><?= count($sows) === 1 ? 'sow' : 'sows' ?> shown</div>
    <section class="record-grid">
        <?php foreach ($sows as $sow): ?>
            <article class="record-card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">
                <!-- Large Hero Card Image -->
                <div style="position:relative;width:100%;height:185px;background:linear-gradient(135deg,#e9e4d8 0%,#d8d0bf 100%);display:flex;align-items:center;justify-content:center;overflow:hidden;">
                    <?php if (!empty($sow['photo_url'])): ?>
                        <img src="<?= e($sow['photo_url']) ?>" alt="<?= e($sow['name']) ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
                    <?php else: ?>
                        <span class="animal-avatar large" style="width:72px;height:72px;font-size:1.85rem;"><?= e(strtoupper(mb_substr($sow['name'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <span class="status status-<?= e($sow['status']) ?>" style="position:absolute;top:12px;right:12px;box-shadow:0 2px 8px rgba(0,0,0,0.12);">
                        <?= e(ucfirst($sow['status'])) ?>
                    </span>
                </div>

                <!-- Card Details -->
                <div style="padding:1.25rem;display:flex;flex-direction:column;flex:1;justify-content:space-between;gap:0.85rem;">
                    <div>
                        <h2 style="margin:0 0 0.2rem 0;"><?= e($sow['name']) ?></h2>
                        <p class="record-meta" style="margin:0 0 0.55rem 0;">Tag <?= e($sow['tag_number']) ?></p>
                        <p class="record-note" style="margin:0;"><?= e($sow['notes'] ?: 'No care notes added yet.') ?></p>
                    </div>

                    <?php if ($view === 'archived'): ?>
                        <!-- Archived Sow Actions: View, Restore & Delete Permanently -->
                        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;justify-content:space-between;border-top:1px solid rgba(0,0,0,0.08);padding-top:0.85rem;">
                            <a class="text-link" href="<?= e(url('/sows/view.php?id=' . rawurlencode($sow['id']))) ?>">View record</a>
                            <div style="display:flex;gap:0.45rem;align-items:center;">
                                <form method="post" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="restore_sow">
                                    <input type="hidden" name="sow_id" value="<?= e((string) $sow['id']) ?>">
                                    <button type="submit" class="button button-secondary" style="padding:0.4rem 0.75rem;font-size:0.82rem;">Restore</button>
                                </form>
                                <form method="post" style="margin:0;" onsubmit="return confirm('Permanently delete <?= e(addslashes((string) $sow['name'])) ?> and all linked records? This cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_permanently">
                                    <input type="hidden" name="sow_id" value="<?= e((string) $sow['id']) ?>">
                                    <button type="submit" class="button button-danger" style="padding:0.4rem 0.75rem;font-size:0.82rem;">Delete permanently</button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Active Sow Actions -->
                        <div class="record-actions" style="border-top:1px solid rgba(0,0,0,0.08);padding-top:0.85rem;margin-top:0;">
                            <a href="<?= e(url('/sows/view.php?id=' . rawurlencode($sow['id']))) ?>">View record</a>
                            <a href="<?= e(url('/sows/edit.php?id=' . rawurlencode($sow['id']))) ?>">Edit</a>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>