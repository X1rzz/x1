
<?php
require __DIR__ . '/supabase.php';
require __DIR__ . '/csrf.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function clean_amount($s) {
  $s = preg_replace('/[^0-9.\-]/', '', $s ?? '');
  return $s === '' ? null : (float)$s;
}

$errors = [];
$info = [];

// Demo user id (optional)
$DEMO_USER_ID = getenv('DEMO_USER_ID') ?: null;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $token = $_POST['csrf'] ?? '';
  if (!csrf_check($token)) {
    $errors[] = "CSRF token ไม่ถูกต้อง";
  } else {
    try {
      if ($action === 'create' || $action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $tx_date = $_POST['tx_date'] ?? '';
        $type = $_POST['type'] ?? '';
        $amount = clean_amount($_POST['amount'] ?? '');
        $note = trim($_POST['note'] ?? '');

        if (!$tx_date) $errors[] = "กรุณาเลือกวันที่";
        if (!in_array($type, ['income','expense'], true)) $errors[] = "กรุณาเลือกประเภท";
        if ($amount === null || $amount < 0) $errors[] = "กรุณากรอกจำนวนเงินให้ถูกต้อง";

        if (!$errors) {
          $payload = [
            'tx_date' => $tx_date,
            'type' => $type,
            'amount' => $amount,
            'note' => $note !== '' ? $note : null,
          ];
          if ($DEMO_USER_ID) $payload['user_id'] = $DEMO_USER_ID;

          if ($action === 'create') {
            tx_create($payload);
            $info[] = "บันทึกรายการเรียบร้อยแล้ว";
          } else {
            tx_update($id, $payload);
            $info[] = "แก้ไขรายการเรียบร้อยแล้ว";
          }
        }
      } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        tx_delete($id);
        $info[] = "ลบรายการเรียบร้อยแล้ว";
      }
    } catch (Throwable $e) {
      $errors[] = "เกิดข้อผิดพลาด: " . h($e->getMessage());
    }
  }
}

// Filters
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
try {
  $rows = tx_list(['start' => $start, 'end' => $end]);
} catch (Throwable $e) {
  $errors.append("โหลดข้อมูลไม่สำเร็จ: " . h($e->getMessage()));
  $rows = [];
}

// Summary
$sum_income = 0.0; $sum_expense = 0.0;
foreach ($rows as $r) {
  if (($r['type'] ?? '') === 'income') $sum_income += (float)$r['amount'];
  if (($r['type'] ?? '') === 'expense') $sum_expense += (float)$r['amount'];
}
$balance = $sum_income - $sum_expense;

// Editing
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$edit_row = null;
if ($edit_id) {
  foreach ($rows as $r) if ((int)$r['id'] === $edit_id) { $edit_row = $r; break; }
}

?><!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>รายรับ-รายจ่าย (Supabase)</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="max-w-4xl mx-auto p-4 md:p-6">
    <h1 class="text-2xl md:text-3xl font-bold mb-4">ระบบบันทึกรายรับ-รายจ่าย (Supabase)</h1>

    <?php if ($errors): ?>
      <div class="mb-4 p-3 rounded border border-red-200 bg-red-50">
        <ul class="list-disc list-inside text-red-700">
          <?php foreach ($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <?php if ($info): ?>
      <div class="mb-4 p-3 rounded border border-green-200 bg-green-50">
        <ul class="list-disc list-inside text-green-700">
          <?php foreach ($info as $m): ?><li><?=h($m)?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Filters -->
    <form class="mb-6 grid grid-cols-1 md:grid-cols-5 gap-3" method="get">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">วันที่เริ่ม</label>
        <input type="date" name="start" value="<?=h($start)?>" class="w-full border rounded px-3 py-2">
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">วันที่สิ้นสุด</label>
        <input type="date" name="end" value="<?=h($end)?>" class="w-full border rounded px-3 py-2">
      </div>
      <div class="flex items-end">
        <button class="w-full border rounded px-3 py-2">กรองข้อมูล</button>
      </div>
    </form>

    <!-- Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
      <div class="rounded-xl border p-4 bg-white">
        <div class="text-sm text-gray-500">รวมรายรับ</div>
        <div class="text-2xl font-semibold mt-1"><?=number_format($sum_income,2)?></div>
      </div>
      <div class="rounded-xl border p-4 bg-white">
        <div class="text-sm text-gray-500">รวมรายจ่าย</div>
        <div class="text-2xl font-semibold mt-1"><?=number_format($sum_expense,2)?></div>
      </div>
      <div class="rounded-xl border p-4 bg-white">
        <div class="text-sm text-gray-500">คงเหลือ</div>
        <div class="text-2xl font-semibold mt-1"><?=number_format($balance,2)?></div>
      </div>
    </div>

    <!-- Add/Edit Form -->
    <div class="rounded-xl border bg-white p-4 mb-6">
      <h2 class="font-semibold mb-3"><?= $edit_row ? "แก้ไขรายการ #".(int)$edit_row['id'] : "เพิ่มรายการใหม่" ?></h2>
      <form method="post" class="grid grid-cols-1 md:grid-cols-6 gap-3">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <?php if ($edit_row): ?>
          <input type="hidden" name="id" value="<?= (int)$edit_row['id'] ?>">
        <?php endif; ?>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">วันที่</label>
          <input type="date" name="tx_date" required value="<?=h($edit_row['tx_date'] ?? '')?>" class="w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">ประเภท</label>
          <select name="type" required class="w-full border rounded px-3 py-2">
            <?php $cur = $edit_row['type'] ?? ''; ?>
            <option value="">-- เลือก --</option>
            <option value="income" <?= $cur==='income'?'selected':'' ?>>รายรับ</option>
            <option value="expense" <?= $cur==='expense'?'selected':'' ?>>รายจ่าย</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">จำนวนเงิน</label>
          <input type="number" step="0.01" name="amount" required value="<?=h($edit_row['amount'] ?? '')?>" class="w-full border rounded px-3 py-2">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">หมายเหตุ</label>
          <input type="text" name="note" maxlength="255" value="<?=h($edit_row['note'] ?? '')?>" class="w-full border rounded px-3 py-2" placeholder="เช่น ค่าอาหาร, เงินเดือน ...">
        </div>
        <div class="md:col-span-6 flex gap-2">
          <button name="action" value="<?= $edit_row ? 'update' : 'create' ?>" class="border rounded px-4 py-2"><?= $edit_row ? 'บันทึกการแก้ไข' : 'เพิ่มรายการ' ?></button>
          <?php if ($edit_row): ?>
            <a href="index.php" class="border rounded px-4 py-2">ยกเลิก</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Table -->
    <div class="rounded-xl border bg-white overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="border-b bg-gray-100">
          <tr>
            <th class="text-left px-3 py-2">วันที่</th>
            <th class="text-left px-3 py-2">ประเภท</th>
            <th class="text-right px-3 py-2">จำนวนเงิน</th>
            <th class="text-left px-3 py-2">หมายเหตุ</th>
            <th class="px-3 py-2">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">ไม่พบข้อมูล</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr class="border-b">
              <td class="px-3 py-2"><?=h($r['tx_date'])?></td>
              <td class="px-3 py-2"><?= ($r['type']==='income' ? 'รายรับ' : 'รายจ่าย') ?></td>
              <td class="px-3 py-2 text-right"><?=number_format((float)$r['amount'],2)?></td>
              <td class="px-3 py-2"><?=h($r['note'])?></td>
              <td class="px-3 py-2">
                <div class="flex gap-2 justify-center">
                  <a href="?<?= http_build_query(array_merge($_GET, ['edit'=>$r['id']])) ?>" class="border rounded px-2 py-1 text-xs">แก้ไข</a>
                  <form method="post" onsubmit="return confirm('ยืนยันการลบรายการนี้?');">
                    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                    <input type="hidden" name="id" value="<?=$r['id']?>">
                    <button name="action" value="delete" class="border rounded px-2 py-1 text-xs">ลบ</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <footer class="text-center text-xs text-gray-500 mt-6">
      ทำด้วย ❤️ ด้วย PHP, Supabase (PostgREST) และ TailwindCSS
    </footer>
  </div>
</body>
</html>
