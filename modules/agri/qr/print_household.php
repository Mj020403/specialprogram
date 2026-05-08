<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/auth.php');
require_role(['task_force','admin','mayor']);
require_once app_path('app/config/database.php');
app_require('app/includes/app_helpers.php');

$id = isset($_GET['household_id']) ? (int)$_GET['household_id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid household.';
    exit;
}

$stmt = $conn->prepare("SELECT h.household_id,h.household_code,h.household_head_name,h.profile_photo_path,h.household_size,h.full_address,h.purok_sitio,h.contact_number,b.barangay_name,(SELECT qr_reference FROM qr_codes q WHERE q.household_id=h.household_id AND q.qr_type='HOUSEHOLD' ORDER BY q.qr_id DESC LIMIT 1) AS qr_reference FROM households h LEFT JOIN barangays b ON b.barangay_id=h.barangay_id WHERE h.household_id=? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$house = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$house) {
    http_response_code(404);
    echo 'Household not found.';
    exit;
}

$qrRef = $house['qr_reference'] ?: ('QR-HH-' . str_pad((string)$house['household_id'], 6, '0', STR_PAD_LEFT));
$baseName = preg_replace('/[^a-zA-Z0-9-_]+/', '-', ($house['household_code'] ?: 'household') . '-' . $qrRef);
$baseName = trim((string)$baseName, '-');
if ($baseName === '') {
    $baseName = 'household-qr';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Print Household QR</title>
<style>
    :root {
        color-scheme: light;
        --ink: #243042;
        --muted: #5f6b7c;
        --line: #cfd7c9;
        --panel: #ffffff;
        --soft: #f6f8f3;
        --brand: #214d2f;
        --paper: #eef2ea;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: var(--paper); color: var(--ink); font-family: Arial, Helvetica, sans-serif; }
    .screen-tools { max-width: 960px; margin: 20px auto 0; padding: 0 16px; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    .btn {
        appearance: none; border: 1px solid var(--line); background: var(--panel); color: var(--brand); border-radius: 999px;
        padding: 10px 16px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none;
    }
    .btn-primary { background: var(--brand); color: #fff; border-color: var(--brand); }
    .sheet-wrap { max-width: 960px; margin: 12px auto 32px; padding: 0 16px; }
    .sheet {
        background: var(--panel); border: 2px solid var(--line); border-radius: 0; padding: 26px 28px 18px; box-shadow: 0 12px 30px rgba(0,0,0,.06);
    }
    .title { color: var(--brand); font-size: 14px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .name { margin-top: 18px; font-size: 42px; line-height: 1.05; font-weight: 800; color: var(--brand); }
    .meta { margin-top: 34px; font-size: 24px; color: #5b6575; font-weight: 700; }
    .card-row { margin-top: 34px; display: grid; grid-template-columns: 290px 1fr; gap: 34px; align-items: center; }
    .person { display:flex; align-items:center; gap:16px; margin-bottom:18px; }
    .person img { width:72px; height:72px; border-radius:20px; object-fit:cover; border:2px solid var(--line); }
    .qr-box {
        width: 280px; height: 280px; display: flex; align-items: center; justify-content: center;
        border: 2px solid var(--line); background: #fff; padding: 14px;
    }
    .details { min-width: 0; }
    .ref { margin: 0 0 26px; font-size: 32px; line-height: 1.1; font-weight: 800; color: var(--ink); }
    .details-grid { display: grid; grid-template-columns: 120px 1fr; gap: 12px 20px; font-size: 20px; }
    .details-grid .k { color: var(--brand); font-weight: 800; }
    .details-grid .v { color: var(--ink); font-weight: 700; word-break: break-word; }
    .address { margin-top: 20px; font-size: 16px; color: var(--muted); }
    @media (max-width: 760px) {
        .sheet { padding: 22px 20px 16px; }
        .name { font-size: 32px; }
        .meta { margin-top: 24px; font-size: 20px; }
        .card-row { grid-template-columns: 1fr; gap: 24px; }
        .qr-box { width: 240px; height: 240px; margin: 0 auto; }
        .ref { font-size: 28px; }
        .details-grid { grid-template-columns: 110px 1fr; font-size: 18px; }
    }
    @media print {
        @page { size: auto; margin: 10mm; }
        html, body { background: #fff; }
        .screen-tools { display: none !important; }
        .sheet-wrap { max-width: none; margin: 0; padding: 0; }
        .sheet { box-shadow: none; }
    }
</style>
</head>
<body>
    <div class="screen-tools">
        <a class="btn" href="/harvest/modules/agri/households/view.php?id=<?= (int)$house['household_id'] ?>">Back</a>
        <button class="btn" type="button" id="downloadQrOnlyBtn">Download QR Only</button>
        <button class="btn" type="button" id="downloadCardBtn">Download QR Card</button>
        <button class="btn btn-primary" onclick="window.print()">Print QR Label</button>
    </div>
    <div class="sheet-wrap">
        <section class="sheet" id="downloadCardArea">
            <div class="title">Matag-ob Smart Agro Household QR</div>
            <div class="name"><?= e($house['household_head_name']) ?></div>
            <div class="meta"><?= e($house['household_code'] ?: '-') ?> • <?= e($house['barangay_name'] ?: '-') ?></div>

            <div class="card-row">
                <div class="qr-box" id="printQr"></div>
                <div class="details">
                    <h2 class="ref"><?= e($qrRef) ?></h2>
                    <div class="details-grid">
                        <div class="k">Family</div><div class="v"><?= e($house['household_head_name']) ?></div>
                        <div class="k">Code</div><div class="v"><?= e($house['household_code'] ?: '-') ?></div>
                        <div class="k">Barangay</div><div class="v"><?= e($house['barangay_name'] ?: '-') ?></div>
                        <div class="k">Purok/Sitio</div><div class="v"><?= e($house['purok_sitio'] ?: '-') ?></div>
                        <div class="k">Contact</div><div class="v"><?= e($house['contact_number'] ?: '-') ?></div>
                    </div>
                </div>
            </div>

            <div class="address">Address: <?= e($house['full_address'] ?: '-') ?></div>
        </section>
    </div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
(function () {
    const qrText = <?= json_encode($qrRef) ?>;
    const baseName = <?= json_encode($baseName) ?>;
    const qrTarget = document.getElementById('printQr');

    if (window.QRCode && qrTarget) {
        new QRCode(qrTarget, {
            text: qrText,
            width: 248,
            height: 248,
            correctLevel: QRCode.CorrectLevel.H
        });
    }

    function saveDataUrl(dataUrl, filename) {
        const link = document.createElement('a');
        link.download = filename;
        link.href = dataUrl;
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    function downloadRenderedQrOnly() {
        const canvas = qrTarget ? qrTarget.querySelector('canvas') : null;
        const img = qrTarget ? qrTarget.querySelector('img') : null;
        if (canvas && canvas.toDataURL) {
            saveDataUrl(canvas.toDataURL('image/png'), baseName + '-qr.png');
            return;
        }
        if (img && img.src) {
            saveDataUrl(img.src, baseName + '-qr.png');
        }
    }

    function downloadQrCard() {
        const card = document.getElementById('downloadCardArea');
        if (!card || !window.html2canvas) {
            return;
        }
        html2canvas(card, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true
        }).then(function (canvas) {
            saveDataUrl(canvas.toDataURL('image/png'), baseName + '-card.png');
        });
    }

    const qrOnlyBtn = document.getElementById('downloadQrOnlyBtn');
    const cardBtn = document.getElementById('downloadCardBtn');
    if (qrOnlyBtn) qrOnlyBtn.addEventListener('click', downloadRenderedQrOnly);
    if (cardBtn) cardBtn.addEventListener('click', downloadQrCard);

    window.addEventListener('load', function () {
        const params = new URLSearchParams(window.location.search);
        const auto = params.get('auto');
        const download = params.get('download');
        const mode = params.get('mode');

        if (download === '1') {
            setTimeout(function () {
                if (mode === 'qr') {
                    downloadRenderedQrOnly();
                } else {
                    downloadQrCard();
                }
            }, 500);
        }
        if (auto === '1') {
            setTimeout(function () { window.print(); }, 650);
        }
    });
})();
</script>
</body>
</html>
