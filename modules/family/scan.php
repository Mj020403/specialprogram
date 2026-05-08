<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/session.php');
require_once app_path('app/config/database.php');
app_require('modules/family/portal_helpers.php');
ensure_family_portal_schema($conn);
require_family_scan_enabled($conn);

$logoUrl = system_logo_url($conn);
$appName = system_title($conn);
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qrInput = trim((string)post('qr_reference'));
    if ($qrInput === '') {
        $error = 'Please scan the family QR code first.';
    } else {
        $safe = $conn->real_escape_string($qrInput);
        $row = fetch_one($conn, "SELECT q.household_id, h.household_head_name, h.household_code
                                 FROM qr_codes q
                                 JOIN households h ON h.household_id=q.household_id
                                 WHERE q.qr_type='HOUSEHOLD' AND q.is_active=1 AND (
                                     q.qr_reference='{$safe}' OR q.qr_payload='{$safe}' OR h.household_code='{$safe}'
                                 )
                                 LIMIT 1");
        if (!$row && preg_match('/HOUSEHOLD:(\d+)/', $qrInput, $m)) {
            $hid = (int)$m[1];
            $row = fetch_one($conn, "SELECT household_id, household_head_name, household_code FROM households WHERE household_id={$hid} LIMIT 1");
        }
        if ($row && family_portal_enabled($conn, (int)$row['household_id'])) {
            family_portal_login($conn, (int)$row['household_id']);
            header('Location: ' . app_url('modules/family/dashboard.php'));
            exit;
        }
        $error = 'No active family QR matched that scan.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family QR Access - <?= e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/html5-qrcode" defer></script>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
<div class="min-h-screen flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-6xl grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <section class="rounded-[2rem] border border-slate-200 bg-white p-8 shadow-sm">
            <div class="flex items-center gap-4">
                <img src="<?= e($logoUrl) ?>" alt="Logo" class="h-16 w-16 rounded-2xl object-cover" onerror="this.onerror=null;this.src='<?= e(app_url('assets/img/image.jpg')) ?>';">
                <div>
                    <div class="text-sm text-slate-500"><?= e($appName) ?></div>
                    <h1 class="text-4xl font-black text-slate-900">Family QR access</h1>
                </div>
            </div>
            <p class="mt-5 text-lg leading-8 text-slate-600">The family portal opens only through the family QR code. Once scanned, the dashboard opens automatically.</p>

            <div class="mt-8 rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <div class="font-semibold text-slate-900">What families can do</div>
                <ul class="mt-3 space-y-2 text-sm text-slate-500">
                    <li>• View household progress and qualification</li>
                    <li>• See crop and monitoring updates</li>
                    <li>• Track attendance and assistance history</li>
                    <li>• Upload harvest photos for review</li>
                </ul>
            </div>

            <div class="mt-8 rounded-3xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <div class="font-semibold">Important</div>
                <div class="mt-2">Only the family QR code should be used here. Staff accounts should continue using the normal login page.</div>
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <div class="text-sm text-slate-500">Camera scanner</div>
                    <h2 class="text-3xl font-black text-slate-900">Scan family QR</h2>
                </div>
                <a href="<?= e(app_url('modules/users/auth/login.php')) ?>" class="app-btn-outline">Back to staff login</a>
            </div>

            <?php if ($error): ?><div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900"><?= e($error) ?></div><?php endif; ?>

            <div class="mt-6 grid gap-5">
                <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm text-slate-500 mb-3">Scanner preview</div>
                    <div id="familyQrReader" class="overflow-hidden rounded-[1.5rem] bg-black min-h-[320px]"></div>
                </div>

                <div class="rounded-[1.5rem] border border-dashed border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-semibold text-slate-900">Need to restart camera?</div>
                            <div class="text-sm text-slate-500 mt-1">Tap restart if camera permission was denied or you want to scan again.</div>
                        </div>
                        <button type="button" id="restartScannerBtn" class="app-btn-outline">Restart scanner</button>
                    </div>
                </div>

                <form method="POST" id="familyQrForm" class="grid gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Detected QR reference</label>
                        <input type="text" name="qr_reference" id="familyQrInput" readonly placeholder="The scanned QR reference will appear here automatically." class="w-full rounded-2xl border border-slate-300 px-4 py-3 bg-slate-50">
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" id="openFamilyDashboardBtn" class="app-btn-primary" disabled>Open family dashboard</button>
                    </div>
                </form>

                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                    If your browser blocks the camera, allow camera access and reload this page. On localhost, camera access should work normally.
                </div>
            </div>
        </section>
    </div>
</div>

<script>
(function(){
    const input = document.getElementById('familyQrInput');
    const form = document.getElementById('familyQrForm');
    const openBtn = document.getElementById('openFamilyDashboardBtn');
    const restartBtn = document.getElementById('restartScannerBtn');
    const readerId = "familyQrReader";
    let html5QrCode = null;
    let scannerStarted = false;

    function setDetected(value) {
        if (!value) return;
        input.value = value;
        openBtn.disabled = false;
    }

    function submitAfterScan(value) {
        setDetected(value);
        setTimeout(function(){ form.submit(); }, 250);
    }

    async function stopScanner() {
        if (html5QrCode && scannerStarted) {
            try { await html5QrCode.stop(); } catch (e) {}
            scannerStarted = false;
        }
    }

    async function startScanner() {
        if (typeof Html5Qrcode === "undefined") {
            return;
        }
        await stopScanner();
        html5QrCode = new Html5Qrcode(readerId);
        try {
            await html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 240, height: 240 } },
                async function(decodedText) {
                    await stopScanner();
                    submitAfterScan(decodedText);
                },
                function() {}
            );
            scannerStarted = true;
        } catch (err) {
            const wrap = document.getElementById(readerId);
            if (wrap) {
                wrap.innerHTML = '<div class="flex min-h-[320px] items-center justify-center px-6 text-center text-sm text-slate-500 bg-white">Camera could not start. Please allow camera permission, then tap Restart scanner.</div>';
            }
        }
    }

    restartBtn.addEventListener('click', function(){
        const wrap = document.getElementById(readerId);
        if (wrap) wrap.innerHTML = '';
        startScanner();
    });

    window.addEventListener('load', function(){
        startScanner();
    });
})();
</script>
</body>
</html>
