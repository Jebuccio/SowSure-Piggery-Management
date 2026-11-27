<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_auth();

$today = manila_today();
$dataError = null;
$sows = [];
$archivedSows = [];
$breedingRows = [];
$careRows = [];

$userId = auth_user_id();
$token = access_token();
$herdView = ($_GET['view'] ?? 'active') === 'archived' ? 'archived' : 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $targetSowId = trim((string) ($_POST['sow_id'] ?? ''));

    if ($targetSowId === '') {
        flash('error', 'Missing sow identifier.');
        redirect('/dashboard.php');
    }

    try {
        if ($action === 'delete_sow') {
            $supabase->delete('sows', [
                'id' => 'eq.' . $targetSowId,
                'owner_id' => 'eq.' . $userId,
            ], $token);

            flash('success', 'Sow record permanently deleted.');
            redirect('/dashboard.php?view=' . rawurlencode($herdView));
        }

        if ($action === 'restore_sow') {
            $supabase->update('sows', [
                'id' => 'eq.' . $targetSowId,
                'owner_id' => 'eq.' . $userId,
            ], [
                'archived_at' => null,
            ], $token);

            flash('success', 'Sow restored to your active herd.');
            redirect('/dashboard.php?view=active');
        }

        if ($action === 'upload_sow_photo') {
            if (!isset($_FILES['sow_photo']) || ($_FILES['sow_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Please choose a valid image file to upload.');
            }

            $file = $_FILES['sow_photo'];
            if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
                throw new RuntimeException('Image must be 5 MB or smaller.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = (string) $finfo->file($file['tmp_name']);
            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($allowedMimes[$mimeType])) {
                throw new RuntimeException('Only JPG, PNG, or WEBP images are supported.');
            }

            $ext = $allowedMimes[$mimeType];
            $objectPath = $userId . '/' . $targetSowId . '_' . time() . '.' . $ext;
            $publicUrl = $supabase->uploadStorageFile(
                'sow-photos',
                $objectPath,
                $file['tmp_name'],
                $mimeType,
                $token
            );

            $supabase->update('sows', [
                'id' => 'eq.' . $targetSowId,
                'owner_id' => 'eq.' . $userId,
            ], [
                'photo_url' => $publicUrl,
            ], $token);

            flash('success', 'Sow photo updated successfully.');
            redirect('/dashboard.php?view=' . rawurlencode($herdView));
        }
    } catch (Throwable $exception) {
        error_log('Dashboard sow action failed (' . $action . '): ' . $exception->getMessage());
        flash('error', $exception->getMessage());
        redirect('/dashboard.php?view=' . rawurlencode($herdView));
    }
}

try {
    $sowsRaw = $supabase->select('sows', [
        'select' => 'id,name,tag_number,status,photo_url,archived_at',
        'owner_id' => 'eq.' . $userId,
        'archived_at' => 'is.null',
        'order' => 'name.asc',
    ], $token);
    $sows = is_array($sowsRaw) ? $sowsRaw : [];
} catch (Throwable $exception) {
    error_log('Dashboard sows failed: ' . $exception->getMessage());
    $sows = [];
}

try {
    $archivedRaw = $supabase->select('sows', [
        'select' => 'id,name,tag_number,status,photo_url,archived_at',
        'owner_id' => 'eq.' . $userId,
        'archived_at' => 'not.is.null',
        'order' => 'archived_at.desc',
    ], $token);
    $archivedSows = is_array($archivedRaw) ? $archivedRaw : [];
} catch (Throwable $exception) {
    error_log('Dashboard archived sows failed: ' . $exception->getMessage());
    $archivedSows = [];
}

$sowMap = [];
foreach ($sows as $sow) {
    if (!empty($sow['id'])) {
        $sowMap[$sow['id']] = $sow;
    }
}

try {
    $breedingRaw = $supabase->select('breeding_records', [
        'select' => 'id,sow_id,breeding_date,expected_farrowing_date',
        'owner_id' => 'eq.' . $userId,
        'order' => 'breeding_date.desc',
    ], $token);

    if (is_array($breedingRaw)) {
        foreach ($breedingRaw as $record) {
            $sowId = $record['sow_id'] ?? null;
            if ($sowId && isset($sowMap[$sowId])) {
                $record['sows'] = $sowMap[$sowId];
                $breedingRows[] = $record;
            }
        }
    }
} catch (Throwable $exception) {
    error_log('Dashboard breeding failed: ' . $exception->getMessage());
    $breedingRows = [];
}

try {
    $careRaw = $supabase->select('care_activities', [
        'select' => 'id,sow_id,activity_type,scheduled_date,completed_at',
        'owner_id' => 'eq.' . $userId,
        'order' => 'scheduled_date.asc',
    ], $token);

    if (is_array($careRaw)) {
        foreach ($careRaw as $activity) {
            $sowId = $activity['sow_id'] ?? null;
            if ($sowId && isset($sowMap[$sowId])) {
                $activity['sows'] = $sowMap[$sowId];
                $careRows[] = $activity;
            }
        }
    }
} catch (Throwable $exception) {
    error_log('Dashboard care failed: ' . $exception->getMessage());
    $careRows = [];
}

$latestBySow = [];
foreach ($breedingRows as $record) {
    if (($record['sows']['archived_at'] ?? null) === null && !isset($latestBySow[$record['sow_id']])) {
        $latestBySow[$record['sow_id']] = $record;
    }
}
$upcomingFarrowing = array_values($latestBySow);
usort($upcomingFarrowing, static fn (array $a, array $b): int => strcmp($a['expected_farrowing_date'], $b['expected_farrowing_date']));
$upcomingFarrowing = array_slice($upcomingFarrowing, 0, 5);

$upcomingCare = array_values(array_filter($careRows, static fn (array $a): bool =>
    ($a['scheduled_date'] ?? '') >= $today && empty($a['completed_at']) && ($a['sows']['archived_at'] ?? null) === null
));
$upcomingCare = array_slice($upcomingCare, 0, 5);

$overdueCare = array_values(array_filter($careRows, static fn (array $a): bool =>
    ($a['scheduled_date'] ?? '') < $today && empty($a['completed_at']) && ($a['sows']['archived_at'] ?? null) === null
));

try {
    $profile = current_profile();
} catch (Throwable $exception) {
    $profile = ['full_name' => 'Farm User', 'farm_name' => 'My Farm'];
}

$hour = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim((string) ($profile['full_name'] ?? 'there')))[0];
$displayedSows = $herdView === 'archived' ? $archivedSows : $sows;

$pageTitle = 'Dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div><span class="eyebrow"><?= e(format_date($today, 'l, F j')) ?></span><h1><?= e($greeting) ?>, <?= e($firstName) ?>.</h1><p><?= e($profile['farm_name'] ?? 'Your farm') ?> · Here is what needs your attention.</p></div>
    <div class="button-row"><a class="button button-secondary" href="<?= e(url('/sows/create.php')) ?>">Add sow</a><a class="button" href="<?= e(url('/care/create.php')) ?>">Schedule care</a></div>
</div>

<?php if ($dataError): ?><div class="form-alert" role="alert" style="margin-bottom:1rem;"><?= e($dataError) ?></div><?php endif; ?>

<section class="stats-grid" aria-label="Farm summary">
    <article class="stat-card card"><span class="stat-icon"><?= nav_icon('sow') ?></span><div><small>Active sows</small><strong><?= count($sows) ?></strong><span>Current herd records</span></div></article>
    <article class="stat-card card"><span class="stat-icon light"><?= nav_icon('calendar') ?></span><div><small>Upcoming farrowing</small><strong><?= count($upcomingFarrowing) ?></strong><span>Latest relevant records</span></div></article>
    <article class="stat-card card"><span class="stat-icon earth"><?= nav_icon('care') ?></span><div><small>Upcoming care</small><strong><?= count($upcomingCare) ?></strong><span>Incomplete activities</span></div></article>
    <article class="stat-card card <?= $overdueCare ? 'needs-attention' : '' ?>"><span class="stat-icon amber"><?= nav_icon('care') ?></span><div><small>Overdue care</small><strong><?= count($overdueCare) ?></strong><span><?= $overdueCare ? 'Needs attention' : 'Nothing overdue' ?></span></div></article>
</section>

<section class="dashboard-grid">
    <article class="card dashboard-panel">
        <div class="section-heading"><div><span class="eyebrow">Gestation</span><h2>Upcoming farrowing</h2></div><a href="<?= e(url('/schedule/index.php')) ?>">View schedule</a></div>
        <?php if (!$upcomingFarrowing): ?>
            <div class="mini-empty"><p>No upcoming farrowing dates.</p><a href="<?= e(url('/breeding/create.php')) ?>">Add a breeding record</a></div>
        <?php else: ?>
            <div class="dashboard-list">
            <?php foreach ($upcomingFarrowing as $record): $sow = $record['sows'] ?? []; $days = (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($record['expected_farrowing_date']))->format('%a'); ?>
                <a href="<?= e(url('/sows/view.php?id=' . rawurlencode($record['sow_id']))) ?>">
                    <span class="date-tile"><strong><?= e((new DateTimeImmutable($record['expected_farrowing_date']))->format('d')) ?></strong><small><?= e((new DateTimeImmutable($record['expected_farrowing_date']))->format('M')) ?></small></span>
                    <span><strong><?= e($sow['name'] ?? 'Sow') ?></strong><small>Tag <?= e($sow['tag_number'] ?? '—') ?> · <?= $days ?> days away</small></span><span class="chevron">›</span>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
    <article class="card dashboard-panel">
        <div class="section-heading"><div><span class="eyebrow">Care agenda</span><h2>Coming up</h2></div><a href="<?= e(url('/care/index.php')) ?>">View care</a></div>
        <?php if (!$upcomingCare): ?>
            <div class="mini-empty"><p>No upcoming care scheduled.</p><a href="<?= e(url('/care/create.php')) ?>">Schedule an activity</a></div>
        <?php else: ?>
            <div class="dashboard-list">
            <?php foreach ($upcomingCare as $activity): $sow = $activity['sows'] ?? []; ?>
                <a href="<?= e(url('/care/edit.php?id=' . rawurlencode($activity['id']))) ?>">
                    <span class="date-tile earth"><strong><?= e((new DateTimeImmutable($activity['scheduled_date']))->format('d')) ?></strong><small><?= e((new DateTimeImmutable($activity['scheduled_date']))->format('M')) ?></small></span>
                    <span><strong><?= e(ucwords(str_replace('_', ' ', $activity['activity_type']))) ?></strong><small><?= e($sow['name'] ?? 'Sow') ?> · Tag <?= e($sow['tag_number'] ?? '—') ?></small></span><span class="chevron">›</span>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>

<?php if ($overdueCare): ?>
<section class="overdue-panel card">
    <div><span class="alert-mark">!</span><div><span class="eyebrow">Action needed</span><h2><?= count($overdueCare) ?> overdue <?= count($overdueCare) === 1 ? 'activity' : 'activities' ?></h2><p>Review incomplete care and mark finished work as complete.</p></div></div>
    <a class="button button-warm" href="<?= e(url('/care/index.php?view=overdue')) ?>">Review overdue care</a>
</section>
<?php endif; ?>

<section class="card dashboard-panel" style="margin-top:1.5rem;" aria-label="Herd overview">
    <div class="section-heading" style="flex-wrap:wrap;gap:0.75rem;">
        <div>
            <span class="eyebrow">Herd Directory</span>
            <h2><?= $herdView === 'archived' ? 'Archived sows (' . count($archivedSows) . ')' : 'Active sows (' . count($sows) . ')' ?></h2>
        </div>
        <div class="button-row">
            <a class="button <?= $herdView === 'active' ? '' : 'button-secondary' ?>" href="<?= e(url('/dashboard.php?view=active')) ?>">Active (<?= count($sows) ?>)</a>
            <a class="button <?= $herdView === 'archived' ? '' : 'button-secondary' ?>" href="<?= e(url('/dashboard.php?view=archived')) ?>">Archived (<?= count($archivedSows) ?>)</a>
        </div>
    </div>

    <?php if (!$displayedSows): ?>
        <div class="mini-empty">
            <p><?= $herdView === 'archived' ? 'No archived sows in history.' : 'No active sows registered yet.' ?></p>
            <?php if ($herdView === 'active'): ?><a href="<?= e(url('/sows/create.php')) ?>">Add your first sow</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.15rem;margin-top:1rem;">
            <?php foreach ($displayedSows as $sow): $sid = (string) $sow['id']; ?>
                <article class="card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;border:1px solid rgba(0,0,0,0.08);">
                    <!-- Large Hero Photo Banner -->
                    <div style="position:relative;width:100%;height:185px;background:linear-gradient(135deg,#e9e4d8 0%,#d8d0bf 100%);display:flex;align-items:center;justify-content:center;overflow:hidden;">
                        <?php if (!empty($sow['photo_url'])): ?>
                            <img src="<?= e($sow['photo_url']) ?>" alt="<?= e($sow['name'] ?? 'Sow') ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
                        <?php else: ?>
                            <span class="animal-avatar large" style="width:72px;height:72px;font-size:1.85rem;"><?= e(strtoupper(mb_substr((string) ($sow['name'] ?? 'S'), 0, 1))) ?></span>
                        <?php endif; ?>

                        <!-- Status Pill (Top Right) -->
                        <span class="status status-<?= e((string) ($sow['status'] ?? 'healthy')) ?>" style="position:absolute;top:12px;right:12px;box-shadow:0 2px 8px rgba(0,0,0,0.12);">
                            <?= e(ucfirst((string) ($sow['status'] ?? 'active'))) ?>
                        </span>

                        <!-- Sleek One-Click Photo Upload Button (Bottom Right) -->
                        <form id="dash-photo-form-<?= e($sid) ?>" method="post" enctype="multipart/form-data" style="position:absolute;bottom:12px;right:12px;margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="upload_sow_photo">
                            <input type="hidden" name="sow_id" value="<?= e($sid) ?>">
                            <input id="dash-photo-input-<?= e($sid) ?>" class="dash-auto-photo-input" data-form-id="dash-photo-form-<?= e($sid) ?>" type="file" name="sow_photo" accept="image/jpeg,image/png,image/webp" required style="display:none;">
                            <label for="dash-photo-input-<?= e($sid) ?>" class="button button-secondary" style="cursor:pointer;padding:0.4rem 0.8rem;font-size:0.8rem;background:rgba(255,255,255,0.94);backdrop-filter:blur(6px);border:1px solid rgba(0,0,0,0.12);box-shadow:0 2px 10px rgba(0,0,0,0.14);display:inline-flex;align-items:center;gap:0.35rem;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                <span><?= !empty($sow['photo_url']) ? 'Change photo' : 'Upload photo' ?></span>
                            </label>
                        </form>
                    </div>

                    <!-- Card Body -->
                    <div style="padding:1.15rem 1.25rem;display:flex;flex-direction:column;gap:0.85rem;">
                        <div>
                            <strong style="display:block;font-size:1.1rem;margin-bottom:0.15rem;"><?= e($sow['name'] ?? 'Unnamed Sow') ?></strong>
                            <small style="display:block;opacity:0.75;font-size:0.85rem;">Tag <?= e($sow['tag_number'] ?? '—') ?></small>
                        </div>

                        <div style="display:flex;gap:0.5rem;align-items:center;justify-content:space-between;border-top:1px solid rgba(0,0,0,0.08);padding-top:0.8rem;">
                            <a class="button button-secondary" style="padding:0.4rem 0.8rem;font-size:0.82rem;" href="<?= e(url('/sows/view.php?id=' . rawurlencode($sid))) ?>">View details</a>

                            <div style="display:flex;gap:0.4rem;align-items:center;">
                                <?php if ($herdView === 'archived'): ?>
                                    <form method="post" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="restore_sow">
                                        <input type="hidden" name="sow_id" value="<?= e($sid) ?>">
                                        <button type="submit" class="button button-secondary" style="padding:0.4rem 0.75rem;font-size:0.82rem;">Restore</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" style="margin:0;" onsubmit="return confirm('Permanently delete <?= e(addslashes((string) ($sow['name'] ?? 'this sow'))) ?> and all linked records? This cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_sow">
                                    <input type="hidden" name="sow_id" value="<?= e($sid) ?>">
                                    <button type="submit" class="button button-danger" style="padding:0.4rem 0.75rem;font-size:0.82rem;">Delete permanently</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="quick-actions">
    <div class="section-heading"><div><span class="eyebrow">Shortcuts</span><h2>Quick actions</h2></div></div>
    <div class="action-grid">
        <a href="<?= e(url('/sows/create.php')) ?>"><?= nav_icon('sow') ?><span><strong>Add a sow</strong><small>Create a herd record</small></span></a>
        <a href="<?= e(url('/breeding/create.php')) ?>"><?= nav_icon('breeding') ?><span><strong>Record breeding</strong><small>Calculate farrowing</small></span></a>
        <a href="<?= e(url('/care/create.php')) ?>"><?= nav_icon('care') ?><span><strong>Schedule care</strong><small>Plan the next activity</small></span></a>
        <a href="<?= e(url('/schedule/index.php')) ?>"><?= nav_icon('calendar') ?><span><strong>Open schedule</strong><small>Review gestation dates</small></span></a>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.dash-auto-photo-input').forEach(function (input) {
        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) return;
            const formId = input.getAttribute('data-form-id');
            const form = formId ? document.getElementById(formId) : null;
            if (form) {
                const labelText = form.querySelector('label span');
                if (labelText) labelText.textContent = 'Uploading...';
                form.submit();
            }
        });
    });
});
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>