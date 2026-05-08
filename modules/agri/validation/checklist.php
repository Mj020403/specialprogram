<?php
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
$conn = db_conn();
app_require('app/includes/auth.php');
require_role(['task_force','admin','developer','mayor']);
$id = (int)($_GET['id'] ?? 0);
$hh = mysqli_fetch_assoc(mysqli_query($conn, "SELECT household_head_name, household_code FROM households WHERE id={$id} LIMIT 1"));
if (!$hh) die('Household not found');
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $decision = $_POST['decision'] ?? 'Declined';
  $event_date = $_POST['event_date'] ?? null;
  $event_type = $_POST['event_type'] ?? 'Orientation';
  mysqli_query($conn, "UPDATE household_special_programs SET application_status='".mysqli_real_escape_string($conn,$decision==='Approve'?'Pending Orientation':'For Compliance')."' WHERE household_id={$id}");
  $msg = $decision==='Approve' ? 'Validation approved and next stage unlocked.' : 'Validation declined.';
}
app_require('app/includes/header.php');
?>
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm mt-6">
<h1 class="text-2xl font-black">Validation Decision</h1>
<p class="text-slate-500 mt-2">Simple field validation for actual task-force operations.</p>
<?php if (!empty($msg)): ?><div class="mt-4 p-4 rounded-xl bg-green-100 text-green-800"><?= e($msg) ?></div><?php endif; ?>
<div class="mt-6 grid md:grid-cols-2 gap-6">
<div class="rounded-2xl border p-5">
<h2 class="font-bold text-lg">Household</h2>
<div class="mt-3 text-sm">
<div><strong>Name:</strong> <?= e($hh['household_head_name']) ?></div>
<div><strong>Code:</strong> <?= e($hh['household_code']) ?></div>
</div>
<div class="mt-6">
<h3 class="font-semibold">Inspection Guide</h3>
<ul class="mt-3 space-y-2 text-sm text-slate-600">
<li>✔ Area accessible</li>
<li>✔ Land visible and verified</li>
<li>✔ Ownership confirmed</li>
<li>✔ Program suitable</li>
<li>✔ Applicant willing to participate</li>
</ul>
</div>
</div>
<form method="POST" class="rounded-2xl border p-5 space-y-4">
<div>
<label class="font-semibold">Orientation / Seminar Schedule</label>
<select name="existing_event" class="w-full border rounded-xl p-3 mt-2">
<option value="">Select Existing Event</option>
<option>Gamefowl Orientation - May 15</option>
<option>Gamefowl Seminar - May 20</option>
</select>
</div>
<div>
<label class="font-semibold">Or Create New Schedule</label>
<input type="date" name="event_date" class="w-full border rounded-xl p-3 mt-2">
</div>
<div>
<label class="font-semibold">Event Type</label>
<select name="event_type" class="w-full border rounded-xl p-3 mt-2">
<option>Orientation</option>
<option>Seminar</option>
<option>Final Briefing</option>
</select>
</div>
<div>
<label class="font-semibold">Remarks</label>
<textarea name="remarks" class="w-full border rounded-xl p-3 mt-2" rows="4" placeholder="Optional remarks..."></textarea>
</div>
<div class="flex gap-3 pt-4">
<button name="decision" value="Approve" class="app-btn-primary">Approve</button>
<button name="decision" value="Decline" class="app-btn-outline">Decline</button>
</div>
</form>
</div>
</section>
<?php app_require('app/includes/footer.php'); ?>
