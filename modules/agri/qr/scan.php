<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

$conn = db_conn();app_require('app/includes/session.php');
require_once app_path('app/config/database.php');
app_require('app/includes/app_helpers.php');
$needsLogin = !isset($_SESSION['user_id']);
if ($needsLogin) { header('Location: /harvest/modules/users/auth/login.php?redirect=' . urlencode('/harvest/modules/agri/qr/scan.php')); exit; }
$scanContext = trim((string)($_GET['context'] ?? 'lookup'));
$attendanceEventId = (int)($_GET['event_id'] ?? 0);
$attendanceRapid = isset($_GET['rapid']) ? 1 : 0;
$eventMeta = null;
if ($scanContext === 'attendance' && $attendanceEventId > 0) {
    sync_all_event_statuses($conn);
    $eventMeta = fetch_one($conn, "SELECT e.event_id,e.event_name,e.event_code,e.event_date,e.event_status,e.barangay_id,b.barangay_name FROM events e LEFT JOIN barangays b ON b.barangay_id=e.barangay_id WHERE event_id=" . $attendanceEventId . " LIMIT 1");
    if (!$eventMeta) {
        $scanContext = 'lookup';
        $attendanceEventId = 0;
        $attendanceRapid = 0;
    }
}
app_require('app/includes/header.php');
?>
<section class="rounded-[2rem] border p-6 shadow-sm max-w-6xl mx-auto app-panel">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="text-sm text-slate-500">QR center</div>
            <h2 class="text-2xl font-black"><?= $scanContext === 'attendance' ? 'Scan household QR for event attendance' : 'Scan household or crop QR' ?></h2>
            <p class="mt-2 text-sm text-slate-500">Use the camera first. If the browser blocks camera access, allow permission and reload. You can still use image upload or manual lookup.</p>
            <?php if ($scanContext === 'attendance' && $eventMeta): ?>
                <div class="mt-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    <div class="font-semibold">Attendance auto-save is ON</div>
                    <div class="mt-1">Event: <strong><?= e(($eventMeta['event_code'] ?: $eventMeta['event_name']) . ' · ' . $eventMeta['event_date']) ?></strong>. Every valid household QR scan saves attendance directly to the database, updates the family's record, and supports their checklist-based household record. Invited barangay: <strong><?= e($eventMeta['barangay_name'] ?: 'All barangays') ?></strong>.</div>
                </div>
            <?php endif; ?>
        </div>
        <a href="<?= $scanContext === 'attendance' && $attendanceEventId > 0 ? '/harvest/modules/agri/attendance/index.php?event_id=' . $attendanceEventId . ($attendanceRapid ? '&rapid=1' : '') : '/harvest/modules/agri/monitoring/index.php' ?>" class="app-btn-outline inline-flex items-center gap-2"><i data-lucide="clipboard-check" class="w-4 h-4"></i><span><?= $scanContext === 'attendance' ? 'Back to attendance' : 'Open monitoring' ?></span></a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="space-y-4">
            <div class="app-soft-card app-camera-shell p-4">
                <div class="flex flex-wrap gap-3 items-center justify-between">
                    <div>
                        <div class="font-semibold">Live camera scan</div>
                        <div id="cameraStatus" class="text-sm text-slate-500 mt-1">Ready to detect camera.</div>
                    </div>
                    <div class="flex flex-wrap gap-2 items-center">
                        <select id="cameraSelect" class="app-control text-sm min-w-[180px]"></select>
                        <button type="button" id="startScanBtn" class="app-btn-primary"><span>Start</span></button>
                        <button type="button" id="stopScanBtn" class="app-btn-outline" disabled><span>Stop</span></button>
                    </div>
                </div>
                <div id="reader" class="mt-4 overflow-hidden rounded-3xl"></div>
            </div>
            <div class="app-soft-card p-4">
                <div class="font-semibold">Scan from QR image</div>
                <p class="mt-1 text-sm text-slate-500">If the camera does not work, upload a picture or screenshot of the QR code.</p>
                <input id="qrImageInput" type="file" accept="image/*" class="app-control mt-3 block w-full">
            </div>
        </div>

        <div class="space-y-4">
            <form id="manualQrForm" class="app-soft-card p-5">
                <div class="text-sm text-slate-500">Manual fallback</div>
                <label class="block mt-3 text-sm font-semibold mb-2">QR reference</label>
                <input id="manualQrInput" class="app-control" placeholder="QR-HH-000001 or QR-CRP-000001">
                <button class="app-btn-primary mt-3 inline-flex items-center gap-2"><i data-lucide="search" class="w-4 h-4"></i><span><?= $scanContext === 'attendance' ? 'Scan and save attendance' : 'Lookup' ?></span></button>
            </form>
            <div id="scanResult" class="app-result-card p-5 min-h-[220px]"><div class="text-sm text-slate-500"><?= $scanContext === 'attendance' ? 'Waiting for household QR scan. Successful scans will auto-save attendance.' : 'No QR scanned yet.' ?></div></div>
        </div>
    </div>
</section>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
(function(){
    const result = document.getElementById('scanResult');
    const status = document.getElementById('cameraStatus');
    const cameraSelect = document.getElementById('cameraSelect');
    const startBtn = document.getElementById('startScanBtn');
    const stopBtn = document.getElementById('stopScanBtn');
    const imageInput = document.getElementById('qrImageInput');
    const contextMode = <?= json_encode($scanContext) ?>;
    const attendanceEventId = <?= (int)$attendanceEventId ?>;
    const attendanceRapid = <?= (int)$attendanceRapid ?>;
    let qrScanner = null;
    let scanning = false;
    let lastDecoded = '';
    let busySaving = false;

    function setStatus(message){ status.textContent = message; }
    function renderResult(html){ result.innerHTML = html; if (window.lucide) window.lucide.createIcons(); }
    function esc(value){ return String(value || '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch])); }

    async function autoSaveAttendance(qr) {
        if (!attendanceEventId) {
            renderResult('<div class="font-semibold text-rose-600">Pick an event first.</div><div class="mt-2 text-sm text-slate-500">Go back to Attendance, select the event, then open the scanner again.</div>');
            return;
        }
        if (busySaving) return;
        busySaving = true;
        renderResult('<div class="text-sm text-slate-500">Saving attendance for <strong>' + esc(qr) + '</strong>...</div>');
        try {
            const body = new URLSearchParams({ event_id: String(attendanceEventId), qr: qr, attendance_status: 'Present', method: 'QR Scan' });
            const res = await fetch('/harvest/modules/api/attendance_scan.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() });
            const raw = await res.text();
            let json = null;
            try { json = JSON.parse(raw); } catch (e) { throw new Error(raw || 'Server returned an unreadable response.'); }
            if (!json.ok) {
                renderResult('<div class="font-semibold text-rose-600">Attendance not saved.</div><div class="mt-2 text-sm text-slate-500">' + esc(json.message || 'Unknown error') + '</div>');
                setStatus('Scan failed. Ready for another QR.');
                return;
            }
            const d = json.data || {};
            const snap = d.snapshot || {};
            const pending = (snap.pending_actions || []).length ? snap.pending_actions.join(', ') : 'Ready';
            const completion = snap.completion || {};
            const photo = d.photo_url || '/harvest/public/assets/img/image.jpg';
            renderResult(`
                <div class="grid gap-4 md:grid-cols-[auto_1fr] md:items-start">
                    <img src="${photo}" alt="Head photo" class="h-24 w-24 rounded-[1.5rem] object-cover border border-slate-200 dark:border-slate-800 bg-slate-100">
                    <div>
                        <div class="text-sm text-emerald-700 font-semibold">Attendance saved to database</div>
                        <div class="mt-2 text-2xl font-black">${esc(d.head_name || d.household_head_name || '-')}</div>
                        <div class="mt-1 text-sm text-slate-500">${esc(d.household_code || '-')} · ${esc(d.qr_reference || qr)}</div>
                        <div class="mt-4 grid gap-3 text-sm text-slate-600">
                            <div><strong>Event:</strong> <?= e($eventMeta['event_name'] ?? '') ?></div><div><strong>Invited barangay:</strong> <?= e($eventMeta['barangay_name'] ?? 'All barangays') ?></div>
                            <div><strong>Status:</strong> Present via QR Scan</div>
                            <div><strong>Qualification:</strong> ${esc(d.qualification_status || 'For Validation')} (${esc((d.score || 0).toString())})</div>
                            <div><strong>Total attended events:</strong> ${esc((d.attendance_count || 0).toString())}</div>
                            <div><strong>Pending tasks:</strong> ${esc(pending)}</div>
                            <div><strong>Completion:</strong> ${esc((completion.overall || 0).toString())}%</div>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <a href="/harvest/modules/agri/households/view.php?id=${encodeURIComponent(d.household_id || 0)}" class="app-btn-outline">Open profile</a>
                            <a href="/harvest/modules/agri/attendance/index.php?event_id=${attendanceEventId}&household_id=${encodeURIComponent(d.household_id || 0)}${attendanceRapid ? '&rapid=1' : ''}" class="app-btn-outline">Open attendance card</a>
                        </div>
                        <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">Ready for next scan. Keep the camera pointed at the next household QR card.</div>
                    </div>
                </div>
            `);
            setStatus('Attendance saved. Ready for next scan.');
        } catch (err) {
            renderResult('<div class="font-semibold text-rose-600">Attendance save failed.</div><div class="mt-2 text-sm text-slate-500">' + esc(err && err.message ? err.message : 'Please try again.') + '</div>');
            setStatus('Could not save attendance. Ready for retry.');
        } finally {
            busySaving = false;
            setTimeout(() => { lastDecoded = ''; }, 1200);
        }
    }

    async function lookup(qr){
        if(!qr) return;
        if (qr === lastDecoded || busySaving) return;
        lastDecoded = qr;
        if (contextMode === 'attendance') {
            return autoSaveAttendance(qr);
        }
        renderResult('<div class="text-sm text-slate-500">Looking up <strong>'+esc(qr)+'</strong>...</div>');
        try {
            const res = await fetch('/harvest/modules/api/qr_lookup.php?qr=' + encodeURIComponent(qr));
            const json = await res.json();
            if(!json.ok){
                renderResult('<div class="font-semibold text-rose-600">No record found for '+esc(qr)+'</div><div class="mt-2 text-sm text-slate-500">Check the QR reference or register the household/crop first.</div>');
                setTimeout(() => { lastDecoded = ''; }, 1200);
                return;
            }
            const d = json.data || {};
            let actions = '';
            if (d.household_id) {
                actions += `<a href="/harvest/modules/agri/households/view.php?id=${d.household_id}" class="app-btn-outline">Open profile</a>`;
                actions += `<a href="/harvest/modules/agri/attendance/index.php?household_id=${d.household_id}" class="app-btn-outline">Attendance</a>`;
                actions += `<a href="/harvest/modules/agri/households/view.php?id=${d.household_id}#golden-household" class="app-btn-outline">Open program checklist</a>`;
                actions += `<a href="/harvest/modules/agri/compliance/index.php?household_id=${d.household_id}" class="app-btn-primary">Open compliance</a>`;
                actions += `<a href="/harvest/modules/agri/timeline/index.php?household_id=${d.household_id}" class="app-btn-outline">Timeline</a>`;
                actions += `<a href="/harvest/modules/agri/assistance/index.php?household_id=${d.household_id}" class="app-btn-outline">Assistance</a>`;
                actions += `<a href="/harvest/modules/agri/households/print.php?id=${d.household_id}" class="app-btn-outline">Print profile</a>`;
            }
            const photo = d.photo_url || '/harvest/public/assets/img/image.jpg';
            renderResult(`
                <div class="grid gap-4 md:grid-cols-[auto_1fr] md:items-start">
                    <img src="${photo}" alt="Head photo" class="h-24 w-24 rounded-[1.5rem] object-cover border border-slate-200 dark:border-slate-800 bg-slate-100">
                    <div>
                        <div class="text-sm text-slate-500">QR found</div>
                        <div class="mt-2 text-2xl font-black">${esc(d.qr_reference || '')}</div>
                        <div class="mt-4 grid gap-3 text-sm text-slate-600">
                            <div><strong>Type:</strong> ${esc(d.qr_type || '-')}</div>
                            <div><strong>Household:</strong> ${esc(d.head_name || d.household_head_name || '-')} ${d.household_code ? '('+esc(d.household_code)+')' : ''}</div>
                            <div><strong>Barangay:</strong> ${esc(d.barangay_name || '-')} · <strong>Members:</strong> ${esc(((d.family_members||[]).length || d.household_size || 0).toString())}</div>
                            <div><strong>Crop:</strong> ${esc(d.crop_name || '-')} ${d.crop_code ? '('+esc(d.crop_code)+')' : ''}</div>
                            <div><strong>Qualification:</strong> ${esc(d.qualification_status || 'For Validation')} (${esc((d.score || 0).toString())})</div>
                            <div><strong>Pending tasks:</strong> ${(d.pending_actions||[]).length ? esc(d.pending_actions.join(', ')) : 'Ready'}</div>
                            <div><strong>Latest monitoring:</strong> ${d.latest_monitoring ? esc(d.latest_monitoring.monitoring_date + ' · ' + d.latest_monitoring.crop_condition + ' · ' + d.latest_monitoring.fruiting_status) : '-'}</div>
                            <div><strong>Latest attendance:</strong> ${d.latest_event ? esc(d.latest_event.event_name + ' · ' + d.latest_event.event_date + ' · ' + d.latest_event.attendance_status) : '-'}</div>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-3">${actions || '<span class="text-sm text-slate-500">No quick actions available.</span>'}</div>
                    </div>
                </div>
            `);
        } catch (err) {
            renderResult('<div class="font-semibold text-rose-600">Lookup failed.</div><div class="mt-2 text-sm text-slate-500">Please try again or use manual lookup.</div>');
        } finally {
            setTimeout(() => { lastDecoded = ''; }, 1200);
        }
    }

    async function loadCameras(){
        if (!window.Html5Qrcode) {
            setStatus('QR library could not load. Check your internet connection and refresh this page.');
            startBtn.disabled = true;
            return [];
        }
        try {
            const cameras = await Html5Qrcode.getCameras();
            cameraSelect.innerHTML = '';
            if (!cameras || !cameras.length) {
                setStatus('No camera found. Use image upload or manual lookup.');
                startBtn.disabled = true;
                return [];
            }
            cameras.forEach((cam, idx) => {
                const opt = document.createElement('option');
                opt.value = cam.id;
                opt.textContent = cam.label || ('Camera ' + (idx + 1));
                cameraSelect.appendChild(opt);
            });
            setStatus(contextMode === 'attendance' ? 'Camera detected. Click Start and scan household QR cards to save attendance.' : 'Camera detected. Click Start to begin scanning.');
            return cameras;
        } catch (e) {
            setStatus('Camera access blocked. Allow camera permission or use another scan method.');
            startBtn.disabled = true;
            return [];
        }
    }

    async function startScanner(){
        if (scanning || !window.Html5Qrcode) return;
        const cameraId = cameraSelect.value;
        if (!cameraId) { setStatus('Select a camera first.'); return; }
        qrScanner = qrScanner || new Html5Qrcode('reader');
        try {
            await qrScanner.start(cameraId, { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.2 }, decodedText => lookup(decodedText), () => {});
            scanning = true;
            startBtn.disabled = true;
            stopBtn.disabled = false;
            setStatus(contextMode === 'attendance' ? 'Scanning attendance QR... every valid household QR will auto-save.' : 'Scanning... point the camera to a clear QR code.');
        } catch (e) {
            setStatus('Unable to start the selected camera. Try another camera or use image upload.');
        }
    }

    async function stopScanner(){
        if (!qrScanner || !scanning) return;
        try { await qrScanner.stop(); } catch(e) {}
        scanning = false;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        setStatus('Camera stopped.');
    }

    document.getElementById('manualQrForm').addEventListener('submit', function(e){
        e.preventDefault();
        lookup(document.getElementById('manualQrInput').value.trim());
    });
    startBtn.addEventListener('click', startScanner);
    stopBtn.addEventListener('click', stopScanner);
    imageInput.addEventListener('change', async function(){
        const file = this.files && this.files[0];
        if (!file) return;
        if (!window.Html5Qrcode) { setStatus('QR library unavailable for image scan.'); return; }
        try {
            if (scanning) await stopScanner();
            qrScanner = qrScanner || new Html5Qrcode('reader');
            setStatus('Reading QR from image...');
            const text = await qrScanner.scanFile(file, true);
            setStatus('QR read from image successfully.');
            lookup(text);
        } catch (e) {
            setStatus('Could not detect a QR code from that image. Try a clearer image.');
        }
    });

    loadCameras();
})();
</script>
<?php app_require('app/includes/footer.php'); ?>
