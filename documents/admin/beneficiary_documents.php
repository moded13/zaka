<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$beneficiary_id = (int)($_GET['beneficiary_id'] ?? 0);

$types = $pdo->query("SELECT id, name_ar FROM beneficiary_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$prefName = '';
if ($beneficiary_id > 0) {
    $st = $pdo->prepare("SELECT full_name FROM beneficiaries WHERE id=?");
    $st->execute([$beneficiary_id]);
    $prefName = (string)($st->fetchColumn() ?: '');
}

$docTypes = [
    'id_card' => 'هوية',
    'birth_cert' => 'شهادة ميلاد',
    'father_death_cert' => 'شهادة وفاة الأب',
];

$sides = ['front' => 'وجه', 'back' => 'ظهر'];

$docs = ($beneficiary_id > 0)
    ? (function() use ($pdo, $beneficiary_id) {
        $st = $pdo->prepare("
            SELECT bd.*, b.full_name, b.file_number, bt.name_ar AS beneficiary_type_name
            FROM beneficiary_documents bd
            JOIN beneficiaries b ON b.id = bd.beneficiary_id
            JOIN beneficiary_types bt ON bt.id = b.beneficiary_type_id
            WHERE bd.beneficiary_id=?
            ORDER BY bd.id DESC
            LIMIT 300
        ");
        $st->execute([$beneficiary_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
      })()
    : $pdo->query("
        SELECT bd.*, b.full_name, b.file_number, bt.name_ar AS beneficiary_type_name
        FROM beneficiary_documents bd
        JOIN beneficiaries b ON b.id = bd.beneficiary_id
        JOIN beneficiary_types bt ON bt.id = b.beneficiary_type_id
        ORDER BY bd.id DESC
        LIMIT 200
      ")->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>وثائق المنتفعين</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= ORPHAN_PUBLIC_BASE ?>/assets/style.css">

  <style>
    .uploader{border:2px dashed #c9d6ee;border-radius:16px;padding:18px;background:#fbfdff;text-align:center;transition:.2s}
    .uploader.drag{border-color:#2d6cdf;background:#f1f7ff}
    .uploader h3{margin:0 0 6px;color:#163d7a}
    .uploader p{margin:0;color:#6c7a92;font-size:13px}
    .uploader input[type=file]{display:none}
    .queue{margin-top:12px;display:grid;gap:10px}
    .q-item{border:1px solid #e6ebf2;border-radius:14px;padding:10px;background:#fff;display:grid;grid-template-columns:92px 1fr;gap:10px}
    .thumb{width:92px;height:92px;border-radius:12px;border:1px solid #edf1f6;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#f8fafc}
    .thumb img{width:100%;height:100%;object-fit:cover}
    .thumb .pdf{font-weight:800;color:#b02a37}
    .meta{display:grid;gap:8px}
    .meta .row{display:grid;grid-template-columns:repeat(12,1fr);gap:10px}
    .meta .row>div{grid-column:span 6}
    .progress{height:10px;background:#eef3fb;border-radius:999px;overflow:hidden;border:1px solid #dbe5f3}
    .bar{height:100%;width:0%;background:linear-gradient(90deg,#2d6cdf,#1d5ac8);transition:.15s}
    .q-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
    .danger{color:#b02a37}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid #dbe5f3;background:#eef3fb;color:#163d7a;font-size:13px;font-weight:800}
    .hint{color:#6c7a92;font-size:13px;margin-top:6px}
    @media(max-width:900px){.q-item{grid-template-columns:72px 1fr}.thumb{width:72px;height:72px}.meta .row>div{grid-column:span 12}}
  </style>
</head>
<body>
<div class="container">

  <div class="card">
    <h2>وثائق المنتفعين</h2>
    <p class="muted">رفع احترافي: سحب وإ��لات + معاينة + رفع متعدد + تقدّم. (الهوية: كل جهة ملف مستقل)</p>
    <div class="actions">
      <a class="btn btn-light" href="<?= ORPHAN_PUBLIC_BASE ?>/admin/index.php">رجوع</a>
      <?php if ($beneficiary_id > 0): ?>
        <span class="pill">المنتفع المحدد: <?= orphan_e($prefName) ?> (ID: <?= (int)$beneficiary_id ?>)</span>
        <a class="btn btn-light" href="document_viewer.php?beneficiary_id=<?= (int)$beneficiary_id ?>">استعراض (سلايدر)</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h3>تحديد المنتفع</h3>

    <div class="grid">
      <?php if ($beneficiary_id > 0): ?>
        <div class="col-12">
          <div class="hint">تم فتح الصفحة من زر "وثائق" داخل النظام، لذلك المنتفع محدد تلقائيًا.</div>
        </div>
      <?php else: ?>
        <div class="col-6">
          <label>نوع المستفيد *</label>
          <select id="benefType" required>
            <option value="">— اختر —</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= orphan_e((string)$t['name_ar']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">إذا اخترت "الكفالات" ستظهر لك قائمة أسماء للكفالات للاختيار.</div>
        </div>

        <div class="col-6">
          <label>رقم الملف داخل النوع *</label>
          <input id="fileNo" type="number" min="1" required>
          <div class="hint">هذا الرقم قد يتكرر بين الأنواع، لذلك النوع مطلوب.</div>
        </div>

        <div class="col-12" id="kafalaPickWrap" style="display:none;">
          <label>اختر اسم الكفالة (اختياري)</label>
          <input id="kafalaSearch" class="form-control" placeholder="اكتب للبحث بالاسم أو رقم الملف..." style="margin-bottom:8px;">
          <select id="kafalaPick" class="form-select">
            <option value="">— اختر من النتائج —</option>
          </select>
          <div class="hint">عند اختيار الاسم سيتم تعبئة رقم الملف تلقائيًا.</div>
        </div>
      <?php endif; ?>
    </div>

    <div class="divider"></div>

    <div id="drop" class="uploader">
      <h3>اسحب الملفات هنا أو اضغط للاختيار</h3>
      <p>المسموح: JPG/PNG/PDF — حجم أقصى: <?= (int)(ORPHAN_MAX_UPLOAD_BYTES / 1024 / 1024) ?>MB</p>
      <input id="fileInput" type="file" multiple accept="image/*,application/pdf" capture="environment">
      <div class="actions" style="justify-content:center;margin-top:10px">
        <button class="btn" id="pickBtn" type="button">اختيار ملفات</button>
        <button class="btn btn-light" id="clearBtn" type="button">مسح القائمة</button>
      </div>
      <div class="hint">للـ Scan: استخدم تطبيق Scan على الهاتف واحفظ PDF ثم ارفعه هنا.</div>
    </div>

    <div class="queue" id="queue"></div>

    <div class="actions" style="justify-content:flex-start;margin-top:12px">
      <button class="btn" id="uploadAllBtn" type="button">رفع الكل</button>
      <span class="muted" id="summary"></span>
    </div>
  </div>

  <div class="card">
    <h3>قائمة الوثائق الحالية</h3>
    <div class="table-wrap">
      <table>
        <tr>
          <th>#</th>
          <th>المنتفع</th>
          <th>رقم الملف</th>
          <th>نوع المستفيد</th>
          <th>نوع الوثيقة</th>
          <th>الجهة</th>
          <th>قابل للمشاركة</th>
          <th>الاسم الأصلي</th>
          <th>الحجم</th>
          <th>تاريخ الرفع</th>
          <th>إجراءات</th>
        </tr>
        <?php foreach ($docs as $d): ?>
          <tr>
            <td><?= (int)$d['id'] ?></td>
            <td style="text-align:right;white-space:normal"><?= orphan_e((string)$d['full_name']) ?></td>
            <td><?= (int)$d['file_number'] ?></td>
            <td><?= orphan_e((string)$d['beneficiary_type_name']) ?></td>
            <td><?= orphan_e($docTypes[$d['doc_type']] ?? (string)$d['doc_type']) ?></td>
            <td><?= orphan_e($sides[$d['doc_side']] ?? '—') ?></td>
            <td><?= ((int)($d['is_shareable'] ?? 0) === 1) ? 'نعم' : 'لا' ?></td>
            <td><?= orphan_e((string)$d['original_name']) ?></td>
            <td><?= number_format(((int)$d['size_bytes']) / 1024, 1) ?> KB</td>
            <td><?= orphan_e((string)$d['created_at']) ?></td>
            <td>
              <div class="actions" style="justify-content:center">
                <a class="btn btn-light" href="document_download.php?id=<?= (int)$d['id'] ?>" target="_blank">تحميل/عرض</a>
                <form method="post" action="document_delete.php" style="margin:0">
                  <?= orphan_csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                  <button class="btn btn-light" type="submit" onclick="return confirm('حذف الوثيقة؟')">حذف</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$docs): ?>
          <tr><td colspan="11" class="muted">لا توجد وثائق.</td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>

</div>

<script>
(function(){
  const BENEFICIARY_ID_LOCKED = <?= (int)$beneficiary_id ?>;
  const csrf = <?= json_encode(orphan_csrf_token(), JSON_UNESCAPED_UNICODE) ?>;

  const drop = document.getElementById('drop');
  const fileInput = document.getElementById('fileInput');
  const pickBtn = document.getElementById('pickBtn');
  const clearBtn = document.getElementById('clearBtn');
  const queueEl = document.getElementById('queue');
  const uploadAllBtn = document.getElementById('uploadAllBtn');
  const summary = document.getElementById('summary');

  const benType = document.getElementById('benefType');
  const fileNo = document.getElementById('fileNo');

  // kafala picker
  const kafalaWrap = document.getElementById('kafalaPickWrap');
  const kafalaSearch = document.getElementById('kafalaSearch');
  const kafalaPick = document.getElementById('kafalaPick');
  const KAFALAT_TYPE_ID = 3; // from your DB

  const DOC_TYPES = <?= json_encode($docTypes, JSON_UNESCAPED_UNICODE) ?>;

  const state = { items: [] };

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function bytesToKB(b){ return (b/1024).toFixed(1) + ' KB'; }
  function isPdf(file){ return (file.type || '').toLowerCase().includes('pdf') || file.name.toLowerCase().endsWith('.pdf'); }

  function ensureTargetSelected(){
    if (BENEFICIARY_ID_LOCKED > 0) return true;
    if (!benType || !fileNo) return false;
    if (!benType.value || !fileNo.value) {
      alert('اختر نوع المستفيد ثم أدخل رقم ا��ملف أولاً.');
      return false;
    }
    return true;
  }

  function defaultDocType(file){
    const n = (file.name || '').toLowerCase();
    if (n.includes('birth')) return 'birth_cert';
    if (n.includes('death')) return 'father_death_cert';
    if (n.includes('id')) return 'id_card';
    return 'id_card';
  }

  function addFiles(files){
    if (!ensureTargetSelected()) return;

    [...files].forEach(file => {
      const max = <?= (int)ORPHAN_MAX_UPLOAD_BYTES ?>;
      if (file.size > max) {
        state.items.push({file, status:'error', error:'حجم الملف أكبر من المسموح', progress:0});
        return;
      }

      const item = {
        file,
        previewUrl: '',
        doc_type: defaultDocType(file),
        doc_side: '',
        is_shareable: 0,
        title: '',
        progress: 0,
        status: 'queued',
        error: ''
      };

      if (!isPdf(file) && (file.type || '').startsWith('image/')) {
        item.previewUrl = URL.createObjectURL(file);
      }

      state.items.push(item);
    });

    render();
  }

  function render(){
    queueEl.innerHTML = '';
    let ok=0, err=0, queued=0, uploading=0;

    state.items.forEach((it) => {
      if (it.status === 'done') ok++;
      else if (it.status === 'error') err++;
      else if (it.status === 'uploading') uploading++;
      else queued++;

      const wrap = document.createElement('div');
      wrap.className = 'q-item';

      const thumb = document.createElement('div');
      thumb.className = 'thumb';
      if (isPdf(it.file)) {
        const t = document.createElement('div');
        t.className = 'pdf';
        t.textContent = 'PDF';
        thumb.appendChild(t);
      } else if (it.previewUrl) {
        const img = document.createElement('img');
        img.src = it.previewUrl;
        thumb.appendChild(img);
      } else {
        thumb.textContent = 'FILE';
      }

      const meta = document.createElement('div');
      meta.className = 'meta';

      const top = document.createElement('div');
      top.innerHTML = `<strong>${escapeHtml(it.file.name)}</strong><br><small>${bytesToKB(it.file.size)} — ${escapeHtml(it.file.type || 'unknown')}</small>`;
      meta.appendChild(top);

      const row = document.createElement('div');
      row.className = 'row';

      const col1 = document.createElement('div');
      col1.innerHTML = `<label>نوع الوثيقة</label>`;
      const sel = document.createElement('select');
      Object.keys(DOC_TYPES).forEach(k=>{
        const o = document.createElement('option');
        o.value = k; o.textContent = DOC_TYPES[k];
        if (k === it.doc_type) o.selected = true;
        sel.appendChild(o);
      });
      sel.onchange = ()=>{ it.doc_type = sel.value; render(); };
      col1.appendChild(sel);

      const col2 = document.createElement('div');
      col2.innerHTML = `<label>الجهة (للهوية فقط)</label>`;
      const side = document.createElement('select');
      side.innerHTML = `<option value="">—</option><option value="front">وجه</option><option value="back">ظهر</option>`;
      side.value = it.doc_side || '';
      side.onchange = ()=>{ it.doc_side = side.value; };
      col2.appendChild(side);

      const col3 = document.createElement('div');
      col3.innerHTML = `<label>قابل للمشاركة</label>`;
      const share = document.createElement('select');
      share.innerHTML = `<option value="0">لا</option><option value="1">نعم</option>`;
      share.value = String(it.is_shareable || 0);
      share.onchange = ()=>{ it.is_shareable = parseInt(share.value,10)||0; };
      col3.appendChild(share);

      const col4 = document.createElement('div');
      col4.innerHTML = `<label>عنوان (اختياري)</label>`;
      const title = document.createElement('input');
      title.type = 'text';
      title.value = it.title || '';
      title.oninput = ()=>{ it.title = title.value; };
      col4.appendChild(title);

      row.appendChild(col1);
      row.appendChild(col2);
      row.appendChild(col3);
      row.appendChild(col4);
      meta.appendChild(row);

      const prog = document.createElement('div');
      prog.className = 'progress';
      const bar = document.createElement('div');
      bar.className = 'bar';
      bar.style.width = (it.progress || 0) + '%';
      prog.appendChild(bar);
      meta.appendChild(prog);

      const actions = document.createElement('div');
      actions.className = 'q-actions';

      const st = document.createElement('div');
      let stText = '';
      if (it.status === 'done') stText = 'تم الرفع';
      else if (it.status === 'uploading') stText = 'جاري الرفع...';
      else if (it.status === 'error') stText = 'خطأ: ' + it.error;
      else stText = 'جاهز';
      st.innerHTML = `<small class="${it.status==='error'?'danger':''}">${escapeHtml(stText)}</small>`;
      actions.appendChild(st);

      const upBtn = document.createElement('button');
      upBtn.type = 'button';
      upBtn.className = 'btn btn-light';
      upBtn.textContent = 'رفع';
      upBtn.disabled = (it.status === 'uploading' || it.status === 'done');
      upBtn.onclick = ()=> uploadOne(it);
      actions.appendChild(upBtn);

      const rmBtn = document.createElement('button');
      rmBtn.type = 'button';
      rmBtn.className = 'btn btn-light';
      rmBtn.textContent = 'إزالة';
      rmBtn.disabled = (it.status === 'uploading');
      rmBtn.onclick = ()=>{
        if (it.previewUrl) URL.revokeObjectURL(it.previewUrl);
        state.items = state.items.filter(x=>x!==it);
        render();
      };
      actions.appendChild(rmBtn);

      meta.appendChild(actions);

      wrap.appendChild(thumb);
      wrap.appendChild(meta);
      queueEl.appendChild(wrap);
    });

    summary.textContent = `الإجمالي: ${state.items.length} — جاهز: ${queued} — جارٍ: ${uploading} — تم: ${ok} — أخطاء: ${err}`;
  }

  function uploadOne(it){
    if (!ensureTargetSelected()) return;

    if (it.doc_type === 'id_card' && !it.doc_side) {
      alert('اختر جهة الهوية (وجه/ظهر) قبل الرفع.');
      return;
    }

    it.status = 'uploading';
    it.error = '';
    it.progress = 0;
    render();

    const fd = new FormData();
    fd.append('_orphan_csrf', csrf);

    if (BENEFICIARY_ID_LOCKED > 0) {
      fd.append('beneficiary_id', String(BENEFICIARY_ID_LOCKED));
    } else {
      fd.append('beneficiary_type_id', String(benType.value));
      fd.append('file_number', String(fileNo.value));
    }

    fd.append('doc_type', it.doc_type);
    fd.append('doc_side', it.doc_side || '');
    fd.append('is_shareable', String(it.is_shareable || 0));
    fd.append('title', it.title || '');
    fd.append('file', it.file, it.file.name);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'document_upload_ajax.php', true);

    xhr.upload.onprogress = (e)=>{
      if (!e.lengthComputable) return;
      it.progress = Math.round((e.loaded / e.total) * 100);
      render();
    };

    xhr.onreadystatechange = ()=>{
      if (xhr.readyState !== 4) return;
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          const res = JSON.parse(xhr.responseText || '{}');
          if (res.ok) {
            it.status = 'done';
            it.progress = 100;
          } else {
            it.status = 'error';
            it.error = res.error || 'فشل غير معروف';
          }
        } catch (e) {
          it.status = 'error';
          it.error = 'رد غير صالح من السيرفر';
        }
      } else {
        it.status = 'error';
        it.error = (xhr.responseText || ('HTTP ' + xhr.status)).slice(0, 160);
      }
      render();
    };

    xhr.send(fd);
  }

  async function uploadAll(){
    for (const it of state.items) {
      if (it.status === 'queued' || it.status === 'error') {
        uploadOne(it);
        await waitUntilDone(it);
      }
    }
    setTimeout(()=> location.reload(), 700);
  }

  function waitUntilDone(it){
    return new Promise(resolve=>{
      const t = setInterval(()=>{
        if (it.status !== 'uploading') { clearInterval(t); resolve(true); }
      }, 250);
    });
  }

  // drag drop
  ;['dragenter','dragover'].forEach(evt=>{
    drop.addEventListener(evt, (e)=>{ e.preventDefault(); e.stopPropagation(); drop.classList.add('drag'); });
  });
  ;['dragleave','drop'].forEach(evt=>{
    drop.addEventListener(evt, (e)=>{ e.preventDefault(); e.stopPropagation(); drop.classList.remove('drag'); });
  });
  drop.addEventListener('drop', (e)=>{
    const files = e.dataTransfer.files;
    if (files && files.length) addFiles(files);
  });

  pickBtn.addEventListener('click', ()=> fileInput.click());
  fileInput.addEventListener('change', ()=>{ if (fileInput.files) addFiles(fileInput.files); fileInput.value=''; });

  clearBtn.addEventListener('click', ()=>{
    state.items.forEach(it=>{ if (it.previewUrl) URL.revokeObjectURL(it.previewUrl); });
    state.items = [];
    render();
  });

  uploadAllBtn.addEventListener('click', uploadAll);

  // ✅ Kafala picker behavior
  async function fetchKafalat(q){
    const url = 'ajax_beneficiaries.php?type_id=' + encodeURIComponent(String(KAFALAT_TYPE_ID)) + '&q=' + encodeURIComponent(q||'');
    const res = await fetch(url, {credentials:'same-origin'});
    return res.json();
  }

  let kafalaTimer = null;
  async function refreshKafalaList(){
    const q = (kafalaSearch?.value || '').trim();
    const data = await fetchKafalat(q);
    if (!data.ok) return;

    kafalaPick.innerHTML = '<option value="">— اختر من النتائج —</option>';
    data.data.forEach(r=>{
      const opt = document.createElement('option');
      opt.value = String(r.id);
      opt.textContent = `(${r.file_number}) ${r.full_name}`;
      opt.setAttribute('data-file', String(r.file_number));
      kafalaPick.appendChild(opt);
    });
  }

  if (benType && kafalaWrap) {
    benType.addEventListener('change', ()=>{
      const tid = parseInt(benType.value||'0',10);
      const show = (tid === KAFALAT_TYPE_ID);
      kafalaWrap.style.display = show ? '' : 'none';
      if (show) refreshKafalaList();
    });
  }

  if (kafalaSearch) {
    kafalaSearch.addEventListener('input', ()=>{
      clearTimeout(kafalaTimer);
      kafalaTimer = setTimeout(refreshKafalaList, 250);
    });
  }

  if (kafalaPick && fileNo) {
    kafalaPick.addEventListener('change', ()=>{
      const opt = kafalaPick.options[kafalaPick.selectedIndex];
      const f = opt ? (opt.getAttribute('data-file') || '') : '';
      if (f) fileNo.value = f;
    });
  }

  render();
})();
</script>

</body>
</html>