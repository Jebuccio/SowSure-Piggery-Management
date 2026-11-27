<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_auth();

$id = (string) ($_GET['id'] ?? '');
try {
    $sow = find_owned_sow($id);
} catch (Throwable $exception) {
    error_log('Sow detail failed: ' . $exception->getMessage());
    $sow = null;
}
if (!$sow) {
    flash('error', 'That sow record was not found.');
    redirect('/sows/index.php');
}

if (method_is('POST')) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $userId = auth_user_id();
    $token = access_token();

    try {
        if ($action === 'upload_photo') {
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
            $objectPath = $userId . '/' . $id . '_' . time() . '.' . $ext;
            $publicUrl = $supabase->uploadStorageFile(
                'sow-photos',
                $objectPath,
                $file['tmp_name'],
                $mimeType,
                $token
            );

            $supabase->update('sows', [
                'id' => 'eq.' . $id,
                'owner_id' => 'eq.' . $userId,
            ], [
                'photo_url' => $publicUrl,
            ], $token);

            flash('success', 'Sow photo updated successfully.');
            redirect('/sows/view.php?id=' . rawurlencode($id));
        }

        if ($action === 'archive') {
            $supabase->update('sows', [
                'id' => 'eq.' . $id,
                'owner_id' => 'eq.' . $userId,
            ], [
                'archived_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            ], $token);

            flash('success', $sow['name'] . ' was archived. Breeding and care history was kept.');
            redirect('/sows/index.php');
        }

        if ($action === 'delete_permanently') {
            $supabase->delete('sows', [
                'id' => 'eq.' . $id,
                'owner_id' => 'eq.' . $userId,
            ], $token);

            flash('success', $sow['name'] . ' was permanently deleted.');
            redirect('/sows/index.php');
        }
    } catch (Throwable $exception) {
        error_log('Sow view action failed (' . $action . '): ' . $exception->getMessage());
        flash('error', $exception->getMessage());
        redirect('/sows/view.php?id=' . rawurlencode($id));
    }
}

try {
    $breeding = $supabase->select('breeding_records', [
        'select' => 'id,breeding_date,expected_farrowing_date,notes',
        'sow_id' => 'eq.' . $id,
        'owner_id' => 'eq.' . auth_user_id(),
        'order' => 'breeding_date.desc',
    ], access_token());
    $care = $supabase->select('care_activities', [
        'select' => 'id,activity_type,scheduled_date,completed_at',
        'sow_id' => 'eq.' . $id,
        'owner_id' => 'eq.' . auth_user_id(),
        'order' => 'scheduled_date.desc',
        'limit' => '5',
    ], access_token());
} catch (Throwable $exception) {
    error_log('Sow related records failed: ' . $exception->getMessage());
    $breeding = $care = [];
}
$currentBreeding = $breeding[0] ?? null;

$pageTitle = $sow['name'];
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading">
    <div><a class="back-link" href="<?= e(url('/sows/index.php')) ?>">Back to sows</a><span class="eyebrow">Sow record · <?= e($sow['tag_number']) ?></span><h1><?= e($sow['name']) ?></h1><p>Added <?= e(format_date($sow['created_at'])) ?></p></div>
    <div class="button-row"><a class="button button-secondary" href="<?= e(url('/sows/edit.php?id=' . rawurlencode($id))) ?>">Edit record</a><a class="button" href="<?= e(url('/breeding/create.php?sow_id=' . rawurlencode($id))) ?>">Add breeding</a></div>
</div>

<section class="detail-grid">
    <article class="card profile-card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">
        <!-- Large Hero Photo Container -->
        <div style="position:relative;width:100%;height:230px;background:linear-gradient(135deg,#e9e4d8 0%,#d8d0bf 100%);display:flex;align-items:center;justify-content:center;overflow:hidden;">
            <?php if (!empty($sow['photo_url'])): ?>
                <img id="sow-hero-preview" src="<?= e($sow['photo_url']) ?>" alt="<?= e($sow['name']) ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
            <?php else: ?>
                <span id="sow-hero-placeholder" class="animal-avatar large" style="width:88px;height:88px;font-size:2.2rem;"><?= e(strtoupper(mb_substr($sow['name'], 0, 1))) ?></span>
                <img id="sow-hero-preview" src="" alt="<?= e($sow['name']) ?>" style="width:100%;height:100%;object-fit:cover;display:none;">
            <?php endif; ?>

            <!-- Status Pill Overlay (Top Right) -->
            <span class="status status-<?= e($sow['status']) ?>" style="position:absolute;top:14px;right:14px;box-shadow:0 2px 8px rgba(0,0,0,0.12);">
                <?= e(ucfirst($sow['status'])) ?>
            </span>

            <!-- Sleek Auto-Submit Photo Upload Button (Bottom Right) -->
            <form id="sow-photo-form" method="post" enctype="multipart/form-data" style="position:absolute;bottom:14px;right:14px;margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_photo">
                <input id="sow-photo-input" type="file" name="sow_photo" accept="image/jpeg,image/png,image/webp" required style="display:none;">
                <label for="sow-photo-input" class="button button-secondary" style="cursor:pointer;padding:0.45rem 0.9rem;font-size:0.82rem;background:rgba(255,255,255,0.94);backdrop-filter:blur(6px);border:1px solid rgba(0,0,0,0.12);box-shadow:0 2px 10px rgba(0,0,0,0.14);display:inline-flex;align-items:center;gap:0.4rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    <span id="sow-photo-btn-text"><?= !empty($sow['photo_url']) ? 'Change photo' : 'Upload photo' ?></span>
                </label>
            </form>
        </div>

        <!-- Card Body -->
        <div style="padding:1.35rem 1.5rem;display:flex;flex-direction:column;gap:1.15rem;">
            <!-- Compact 3-Column Metadata Strip -->
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0.75rem;padding-bottom:1rem;border-bottom:1px solid rgba(0,0,0,0.08);">
                <div>
                    <small style="display:block;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.04em;opacity:0.65;margin-bottom:0.2rem;">Tag number</small>
                    <strong style="font-size:0.95rem;font-weight:600;"><?= e($sow['tag_number']) ?></strong>
                </div>
                <div>
                    <small style="display:block;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.04em;opacity:0.65;margin-bottom:0.2rem;">Status</small>
                    <strong style="font-size:0.95rem;font-weight:600;"><?= e(ucfirst($sow['status'])) ?></strong>
                </div>
                <div>
                    <small style="display:block;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.04em;opacity:0.65;margin-bottom:0.2rem;">Last updated</small>
                    <strong style="font-size:0.95rem;font-weight:600;"><?= e(format_date($sow['updated_at'])) ?></strong>
                </div>
            </div>

            <!-- Care Notes -->
            <div class="notes-block" style="margin:0;padding:0;border:none;">
                <h2 style="font-size:0.95rem;margin-bottom:0.35rem;">Care notes</h2>
                <p style="font-size:0.9rem;opacity:0.85;line-height:1.5;margin:0;"><?= nl2br(e($sow['notes'] ?: 'No care notes have been added.')) ?></p>
            </div>
        </div>
    </article>

    <article class="card highlight-card">
        <span class="eyebrow">Current gestation</span>
        <?php if ($currentBreeding): ?>
            <h2><?= e(format_date($currentBreeding['expected_farrowing_date'])) ?></h2>
            <p>Expected farrowing from breeding on <?= e(format_date($currentBreeding['breeding_date'])) ?>.</p>
            <span class="calculation-note">Breeding date + 114 calendar days</span>
        <?php else: ?>
            <h2>No breeding recorded</h2><p>Add a breeding record to calculate the expected farrowing date.</p>
        <?php endif; ?>
        <a class="text-link" href="<?= e(url('/breeding/create.php?sow_id=' . rawurlencode($id))) ?>">Add breeding record</a>
    </article>
</section>

<section class="section-block">
    <div class="section-heading"><div><span class="eyebrow">History</span><h2>Breeding records</h2></div></div>
    <div class="card table-card">
        <?php if (!$breeding): ?><div class="mini-empty">No breeding history yet.</div>
        <?php else: ?><div class="responsive-table"><table><thead><tr><th>Breeding date</th><th>Expected farrowing</th><th>Notes</th><th></th></tr></thead><tbody>
        <?php foreach ($breeding as $record): ?><tr><td><?= e(format_date($record['breeding_date'])) ?></td><td><strong><?= e(format_date($record['expected_farrowing_date'])) ?></strong></td><td><?= e($record['notes'] ?: '—') ?></td><td><a href="<?= e(url('/breeding/edit.php?id=' . rawurlencode($record['id']))) ?>">Edit</a></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </div>
</section>

<section class="section-block">
    <div class="section-heading"><div><span class="eyebrow">Care</span><h2>Recent activities</h2></div><a class="text-link" href="<?= e(url('/care/create.php?sow_id=' . rawurlencode($id))) ?>">Schedule care</a></div>
    <div class="card table-card">
        <?php if (!$care): ?><div class="mini-empty">No care activities yet.</div>
        <?php else: ?><div class="responsive-table"><table><thead><tr><th>Activity</th><th>Scheduled</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($care as $activity): ?><tr><td><?= e(ucwords(str_replace('_', ' ', $activity['activity_type']))) ?></td><td><?= e(format_date($activity['scheduled_date'])) ?></td><td><span class="status <?= $activity['completed_at'] ? 'status-complete' : 'status-pending' ?>"><?= $activity['completed_at'] ? 'Completed' : 'Pending' ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </div>
</section>

<section class="danger-zone card" style="display:flex;flex-direction:column;gap:1rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div><h2>Archive this sow</h2><p>Remove the sow from active lists without deleting breeding or care history.</p></div>
        <form method="post" onsubmit="return confirm('Archive this sow? Her breeding and care history will be kept.');"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><button class="button button-secondary" type="submit">Archive sow</button></form>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;border-top:1px solid rgba(0,0,0,0.08);padding-top:1rem;">
        <div><h2>Delete this sow permanently</h2><p>Permanently remove this sow and all linked breeding and care records. This cannot be undone.</p></div>
        <form method="post" onsubmit="return confirm('Permanently delete <?= e(addslashes((string) $sow['name'])) ?> and all linked records? This cannot be undone.');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_permanently"><button class="button button-danger" type="submit">Delete permanently</button></form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('sow-photo-input');
    const photoForm = document.getElementById('sow-photo-form');
    const previewImg = document.getElementById('sow-hero-preview');
    const placeholder = document.getElementById('sow-hero-placeholder');
    const btnText = document.getElementById('sow-photo-btn-text');

    if (fileInput && photoForm) {
        fileInput.addEventListener('change', function () {
            if (!fileInput.files || !fileInput.files[0]) return;
            const file = fileInput.files[0];

            // Show immediate local preview while uploading
            if (previewImg) {
                previewImg.src = URL.createObjectURL(file);
                previewImg.style.display = 'block';
            }
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            if (btnText) {
                btnText.textContent = 'Uploading...';
            }
            photoForm.submit();
        });
    }
});
</script>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>