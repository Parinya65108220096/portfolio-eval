(function () {
  const form = document.getElementById('evalForm');
  const KEYS = Object.keys(WEIGHTS);
  const $ = id => document.getElementById(id);
  let timer, dirty = false, saving = false, activeKey = null;

  function getScores() {
    const s = {};
    KEYS.forEach(k => { const el = form.querySelector(`input[name="${k}"]:checked`); s[k] = el ? +el.value : null; });
    return s;
  }
  function getTotal(s) { return KEYS.reduce((t, k) => t + (s[k] ? (s[k] / 5) * WEIGHTS[k] : 0), 0); }

  function updateLive() {
    const s = getScores(); const done = KEYS.filter(k => s[k]).length;
    $('liveScore').textContent = getTotal(s).toFixed(2);
    $('progressBar').style.width = (done / KEYS.length * 100) + '%';
    $('progressText').textContent = `ตอบแล้ว ${done}/${KEYS.length}`;
    if ($('btnSubmit')) $('btnSubmit').disabled = done < KEYS.length;
  }

  function setStatus(txt, cls) { const el = $('saveStatus'); el.textContent = txt; el.className = 'ms-auto save-status ' + (cls || ''); }

  async function save(submit) {
    if (saving) { if (!submit) return null; while (saving) await new Promise(r => setTimeout(r, 100)); }
    saving = true;
    const comments = {};
    [...KEYS, 'overall'].forEach(k => comments[k] = form.querySelector(`[name="c_${k}"]`).value);
    setStatus('กำลังบันทึก...');
    try {
      const res = await fetch(SAVE_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF, assignment_id: ASSIGNMENT_ID, scores: getScores(), comments, submit: !!submit }) });
      const data = await res.json();
      if (data.ok) { dirty = false; setStatus('บันทึกร่างแล้ว ' + data.saved_at, 'text-success'); if (submit) { dirty = false; location.href = DASH_URL; } }
      else setStatus(data.msg || 'บันทึกไม่สำเร็จ', 'text-danger');
      return data;
    } catch (e) { setStatus('บันทึกไม่สำเร็จ (เชื่อมต่อไม่ได้) กรุณาลองใหม่', 'text-danger'); return null; }
    finally { saving = false; }
  }

  // แสดงเกณฑ์ตามคะแนนที่เลือก
  function showRubric(k, v) { $('rubric_' + k).textContent = (RUBRIC[k] && RUBRIC[k][v]) || ''; }

  function setActive(k) {
    activeKey = k;
    document.querySelectorAll('.crit-card').forEach(c => c.classList.toggle('active', c.dataset.key === k));
  }

  // แท็บ เอกสาร/ให้คะแนน สำหรับมือถือ
  document.querySelectorAll('.pane-tabs [data-pane]').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('.pane-tabs .btn').forEach(x => { x.classList.remove('btn-primary'); x.classList.add('btn-outline-primary'); });
    b.classList.add('btn-primary'); b.classList.remove('btn-outline-primary');
    document.querySelectorAll('.pane').forEach(p => p.classList.toggle('active', p.id === b.dataset.pane));
  }));

  updateLive();
  if (READONLY) return;

  form.addEventListener('input', () => {
    updateLive(); dirty = true; setStatus('มีการแก้ไข...');
    clearTimeout(timer); timer = setTimeout(() => save(false), 2000);   // auto-save
  });
  form.addEventListener('change', e => {
    if (e.target.type === 'radio') { showRubric(e.target.name, e.target.value); setActive(e.target.name); e.target.closest('.crit-card').classList.remove('missing'); }
  });
  form.querySelectorAll('.crit-card').forEach(c => {
    c.addEventListener('focusin', () => setActive(c.dataset.key));
    c.addEventListener('click', () => setActive(c.dataset.key));
  });

  // ปุ่มลัด: กด 1-5 เพื่อให้คะแนนหัวข้อที่กำลังเลือกอยู่
  document.addEventListener('keydown', e => {
    if (!activeKey || e.ctrlKey || e.metaKey || e.altKey) return;
    if (/^(TEXTAREA|INPUT|SELECT)$/.test(document.activeElement.tagName) && document.activeElement.type !== 'radio') return;
    if (/^[1-5]$/.test(e.key)) {
      const r = $(activeKey + '_' + e.key);
      if (r) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); form.dispatchEvent(new Event('input')); }
    }
  });

  $('btnDraft').addEventListener('click', () => { clearTimeout(timer); save(false); });

  $('btnSubmit').addEventListener('click', () => {
    const s = getScores(); const missing = KEYS.filter(k => !s[k]);
    if (missing.length) {
      missing.forEach(k => { const c = $('card_' + k); c.classList.add('missing'); });
      $('card_' + missing[0]).scrollIntoView({ behavior: 'smooth', block: 'center' }); return;
    }
    let html = '<table class="table table-sm mb-0"><thead><tr><th>หัวข้อ</th><th class="text-center">คะแนน</th><th class="text-end">ถ่วงน้ำหนัก</th></tr></thead><tbody>';
    KEYS.forEach(k => { html += `<tr><td>${LABELS[k]} <small class="text-muted">(${WEIGHTS[k]}%)</small></td><td class="text-center">${s[k]}/5</td><td class="text-end">${((s[k] / 5) * WEIGHTS[k]).toFixed(2)}</td></tr>`; });
    html += `</tbody><tfoot><tr class="fw-bold"><td colspan="2">รวม</td><td class="text-end">${getTotal(s).toFixed(2)} / 100</td></tr></tfoot></table>`;
    $('summary').innerHTML = html;
    new bootstrap.Modal($('confirmModal')).show();
  });

  $('btnConfirm').addEventListener('click', async () => {
    $('btnConfirm').disabled = true; clearTimeout(timer);
    const d = await save(true);
    if (!d || !d.ok) { $('btnConfirm').disabled = false; bootstrap.Modal.getInstance($('confirmModal')).hide(); if (d && d.msg) alert(d.msg); }
  });

  window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
