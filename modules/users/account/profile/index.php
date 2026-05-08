
<?php
require_once dirname(__DIR__, 4) . '/app/bootstrap.php';
app_require('app/includes/auth.php');
require_login();
$conn = db_conn();
ensure_user_account_schema($conn);
$user = current_user();
$openRequest = user_profile_request_open($conn, (int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string)post('full_name'));
    $email = trim((string)post('email'));
    $contact = trim((string)post('contact_number'));
    $position = trim((string)post('position_title'));
    $bio = trim((string)post('bio'));
    $avatarPath = $user['avatar_path'] ?? null;
    if (!empty($_FILES['avatar']['name'])) {
        $uploaded = upload_user_avatar($_FILES['avatar']);
        if ($uploaded) {
            $avatarPath = $uploaded;
        }
    }
    $payload = [
        'full_name' => $fullName !== '' ? $fullName : ($user['name'] ?? 'User'),
        'email' => $email,
        'contact_number' => $contact,
        'position_title' => $position,
        'bio' => $bio,
        'avatar_path' => $avatarPath,
    ];
    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, contact_number=?, position_title=?, bio=?, avatar_path=?, profile_status='approved', profile_reviewed_by=NULL, profile_reviewed_at=NULL, updated_at=NOW() WHERE user_id=?");
    if ($stmt) {
        $stmt->bind_param('ssssssi', $payload['full_name'], $payload['email'], $payload['contact_number'], $payload['position_title'], $payload['bio'], $payload['avatar_path'], $user['id']);
        $stmt->execute();
        $stmt->close();
        refresh_session_user($conn, (int)$user['id']);
        log_account_activity($conn, (int)$user['id'], (int)$user['id'], 'profile_updated', 'Updated own account profile details directly.');
        set_flash('success', 'Profile updated successfully.');
    } else {
        set_flash('error', 'Unable to update profile right now.');
    }
    header('Location: ' . app_url('modules/users/account/profile/index.php'));
    exit;
}

app_require('app/includes/header.php');
$user = current_user();
$openRequest = user_profile_request_open($conn, (int)$user['id']);
$avatar = user_avatar_url($user['avatar_path'] ?? null);
?>
<section class="grid gap-6 xl:grid-cols-[0.74fr_0.26fr]">
    <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
        <div class="text-sm text-slate-500">Account profile</div>
        <h2 class="text-3xl font-black">Manage your account details</h2>
        <p class="mt-2 text-sm text-slate-500">Update your name, contact details, job title, short bio, and profile photo. Changes save directly to your account.</p>
        <div class="mb-4 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 flex items-center gap-4">
            <img src="<?= e(user_avatar_url($user['avatar_path'] ?? null)) ?>" alt="Avatar preview" id="profileAvatarPreview" class="h-24 w-24 rounded-[1.75rem] object-cover border border-slate-200">
            <div>
                <div class="font-bold text-lg">Live photo preview</div>
                <div class="text-sm text-slate-500">When you choose a new picture, the preview updates before you save.</div>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data"  class="mt-6 grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2 flex items-center gap-4 rounded-3xl border border-slate-200 dark:border-slate-800 p-4">
                <img src="<?= e($avatar) ?>" alt="Profile photo" class="h-20 w-20 rounded-[1.5rem] object-cover border border-slate-200 dark:border-slate-800">
                <div>
                    <div class="font-black text-xl"><?= e($user['name']) ?></div>
                    <div class="text-sm text-slate-500"><?= e(role_label($user['role'])) ?> · <?= e($user['username']) ?></div>
                    <label class="mt-3 inline-flex items-center gap-2 rounded-2xl border border-slate-200 dark:border-slate-800 px-4 py-2 font-semibold cursor-pointer">
                        <i data-lucide="camera" class="w-4 h-4"></i><span>Upload photo</span>
                        <input type="file" name="avatar" accept="image/*" id="profileAvatarInput" class="hidden">
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Full name</label>
                <input name="full_name" value="<?= e($openRequest['full_name'] ?? $user['name']) ?>" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Username</label>
                <input value="<?= e($user['username']) ?>" class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-4 py-3" disabled>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Email</label>
                <input name="email" value="<?= e($openRequest['email'] ?? $user['email']) ?>" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Contact number</label>
                <input name="contact_number" value="<?= e($openRequest['contact_number'] ?? $user['contact_number']) ?>" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold mb-2">Position title</label>
                <input name="position_title" value="<?= e($openRequest['position_title'] ?? $user['position_title']) ?>" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold mb-2">Short bio</label>
                <textarea name="bio" rows="4" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-4 py-3"><?= e($openRequest['bio'] ?? ($user['bio'] ?? '')) ?></textarea>
            </div>
            <div class="md:col-span-2 flex items-center gap-3 flex-wrap">
                <button type="submit" class="app-btn-primary">Save profile</button>
            </div>
        </form>
    </div>
    <aside class="space-y-4">
        <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
            <div class="text-sm text-slate-500">Approval status</div>
            <div class="mt-3"><?= format_status_badge($openRequest['request_status'] ?? ($user['profile_status'] ?? 'approved')) ?></div>
            <div class="mt-3 text-sm text-slate-500">Your profile details update directly and are currently active in the system.</div>
        </div>
        <div class="rounded-[2rem] border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-sm">
            <div class="text-sm text-slate-500">Quick actions</div>
            <div class="mt-4 space-y-3">
                <a href="<?= e(app_url('modules/users/auth/logout.php')) ?>" class="app-btn-outline w-full justify-center">Log out</a>
                <?php if (($user['role'] ?? '') === 'developer'): ?>
                    <a href="<?= e(app_url('modules/admin/profile_requests/index.php')) ?>" class="app-btn-outline w-full justify-center">Profile approvals</a>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</section>
<script>
(function(){
    const input = document.getElementById('profileAvatarInput');
    const targets = [document.getElementById('profileAvatarPreview'), document.getElementById('profileAvatarHeroPreview')].filter(Boolean);
    if (!input || !targets.length) return;
    input.addEventListener('change', function(){
        const file = input.files && input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e){
            targets.forEach(function(img){ img.src = e.target.result; });
        };
        reader.readAsDataURL(file);
    });
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
