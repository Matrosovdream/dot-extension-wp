(function(){
  const $  = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  const root = $('#faip');
  if (!root) return;

  const statusbar = $('#faipStatusbar');
  const setStatus = (type, text) => {
    if (!statusbar) return;
    if (!text) { statusbar.innerHTML = ''; return; }
    statusbar.innerHTML = '<span class="ffda-inline-msg ' + (type || '') + '">' + String(text) + '</span>';
  };

  // Filters form auto-submit
  const form = $('#faipFiltersForm');
  const statusSel = $('#faipFilterStatus');
  const qInput = $('#faipFilterQ');

  if (statusSel && form) statusSel.addEventListener('change', () => form.submit());

  if (qInput && form) {
    qInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); form.submit(); }
    });
  }

  // Bulk selection UI
  const elTbody   = $('#faipTbody');
  const elCheckAll= $('#faipCheckAll');
  const elBulkFix = $('#faipBulkFix');
  const elBulkApprove = $('#faipBulkApprove');

  // Prompts (select left of AI fix button)
  const elPromptSelect = $('#faipPromptSelect');

  function selectedIds(){
    return $$('#faipTbody input[type="checkbox"][data-id]:checked')
      .map(cb => parseInt(cb.dataset.id,10))
      .filter(Boolean);
  }

  function updateBulkButtons(){
    const any = selectedIds().length > 0;
    if (elBulkFix) elBulkFix.disabled = !any;
    if (elBulkApprove) elBulkApprove.disabled = !any;
  }

  // return selected prompt values (strings)
  function selectedPrompts(){
    if (!elPromptSelect) return [];
    return Array.from(elPromptSelect.selectedOptions || [])
      .map(o => (o && o.value) ? String(o.value).trim() : '')
      .filter(Boolean);
  }

  if (elCheckAll && elTbody) {
    elCheckAll.addEventListener('change', () => {
      const checked = elCheckAll.checked;
      $$('#faipTbody input[type="checkbox"][data-id]').forEach(cb => cb.checked = checked);
      updateBulkButtons();
    });

    elTbody.addEventListener('change', (e) => {
      if (e.target && e.target.matches('input[type="checkbox"][data-id]')) {
        const all = $$('#faipTbody input[type="checkbox"][data-id]');
        const on  = all.filter(x=>x.checked);
        elCheckAll.checked = all.length > 0 && on.length === all.length;
        updateBulkButtons();
      }
    });
  }

  // ---------- Status helpers (text + class) ----------
  function statusClassFor(status){
    const s = String(status || '').trim();
    if (s === 'Approved')   return 'mrf-st--complete';
    if (s === 'Denied')     return 'mrf-st--issue';
    if (s === 'Processing') return 'mrf-st--processing';
    if (s === 'On Hold')    return 'mrf-st--hold';
    return 'mrf-st--default';
  }

  function setRowStatus(entryId, status){
    const tr = elTbody ? elTbody.querySelector('tr[data-row="' + entryId + '"]') : null;
    if (!tr) return;
    const el = tr.querySelector('.mrf-status-text');
    if (!el) return;

    const st = String(status || '').trim();
    el.textContent = st;

    el.classList.remove('mrf-st--complete','mrf-st--issue','mrf-st--processing','mrf-st--hold','mrf-st--default');
    el.classList.add(statusClassFor(st));
  }

  function getRowStatusText(entryId){
    const tr = elTbody ? elTbody.querySelector('tr[data-row="' + entryId + '"]') : null;
    if (!tr) return '';
    const el = tr.querySelector('.mrf-status-text');
    return el ? String(el.textContent || '').trim() : '';
  }

  // ---------- Final image loader (wait for img.onload) ----------
  function ensureFinalImgLoader(tr){
    if (!tr) return null;
    const fixedBlock = tr.querySelector('.fixed-image-block');
    if (!fixedBlock) return null;

    let loader = fixedBlock.querySelector('.faip-img-loader');
    if (!loader) {
      loader = document.createElement('div');
      loader.className = 'faip-img-loader';
      loader.style.display = 'none';
      loader.style.marginTop = '6px';
      loader.innerHTML = '<span class="faip-spinner"></span> <span class="faip-ai-label">Loading image…</span>';
      fixedBlock.appendChild(loader);
    }
    return loader;
  }

  function setFinalImgLoading(entryId, on, msg){
    const tr = elTbody ? elTbody.querySelector('tr[data-row="' + entryId + '"]') : null;
    if (!tr) return;

    const loader = ensureFinalImgLoader(tr);
    if (!loader) return;

    if (on) {
      const label = loader.querySelector('.faip-ai-label');
      if (label) label.textContent = msg ? String(msg) : 'Loading image…';
      loader.style.display = 'block';
    } else {
      loader.style.display = 'none';
    }
  }

  // ---------------- Deny modal (multi-select with Select2 + Custom input) ----------------
  const denyModal = $('#faipDenyModal');
  const elDenyReason = $('#faipDenyReason'); // multiple
  const elDenyCancel = $('#faipDenyCancel');
  const elDenyConfirm = $('#faipDenyConfirm');

  let elDenyOtherWrap = $('#faipDenyOtherWrap');
  let elDenyOther = $('#faipDenyOther');
  let denyTargetId = null;

  function ensureCustomDenyUI(){
    if (!denyModal) return;

    if (!elDenyOtherWrap) {
      elDenyOtherWrap = document.createElement('div');
      elDenyOtherWrap.id = 'faipDenyOtherWrap';
      elDenyOtherWrap.style.display = 'none';
      elDenyOtherWrap.style.marginTop = '10px';

      if (elDenyReason && elDenyReason.parentNode) {
        elDenyReason.parentNode.appendChild(elDenyOtherWrap);
      } else {
        denyModal.appendChild(elDenyOtherWrap);
      }
    }

    if (!elDenyOther) {
      elDenyOther = document.createElement('input');
      elDenyOther.id = 'faipDenyOther';
      elDenyOther.type = 'text';
      elDenyOther.placeholder = 'Type custom message…';
      elDenyOther.style.width = '100%';
      elDenyOther.style.border = '1px solid #d0d7de';
      elDenyOther.style.borderRadius = '8px';
      elDenyOther.style.padding = '8px 10px';
      elDenyOther.style.fontSize = '14px';
      elDenyOtherWrap.appendChild(elDenyOther);
    }
  }

  function getSelectedReasons(){
    if (!elDenyReason) return [];
    const opts = elDenyReason.selectedOptions ? Array.from(elDenyReason.selectedOptions) : [];
    return opts
      .map(o => (o && o.value) ? String(o.value).trim() : '')
      .filter(Boolean);
  }

  function isCustomSelected(){
    return getSelectedReasons().includes('__custom__');
  }

  function refreshCustomVisibility(){
    ensureCustomDenyUI();
    if (elDenyOtherWrap) elDenyOtherWrap.style.display = isCustomSelected() ? 'block' : 'none';
  }

  function recomputeDenyConfirmState(){
    if (!elDenyConfirm) return;

    const reasons = getSelectedReasons();
    if (!reasons.length) { elDenyConfirm.disabled = true; return; }

    if (reasons.includes('__custom__')) {
      const msg = (elDenyOther && elDenyOther.value) ? String(elDenyOther.value).trim() : '';
      elDenyConfirm.disabled = !msg;
      return;
    }

    elDenyConfirm.disabled = false;
  }

  function openDenyModal(entryId){
    denyTargetId = entryId;

    ensureCustomDenyUI();

    // clear select
    if (window.jQuery && elDenyReason && jQuery.fn && jQuery.fn.select2) {
      jQuery(elDenyReason).val(null).trigger('change');
    } else if (elDenyReason) {
      Array.from(elDenyReason.options || []).forEach(o => o.selected = false);
    }

    if (elDenyOther) elDenyOther.value = '';
    if (elDenyConfirm) elDenyConfirm.disabled = true;

    refreshCustomVisibility();
    if (denyModal) denyModal.style.display = 'flex';
  }

  function closeDenyModal(){
    if (denyModal) denyModal.style.display = 'none';
    denyTargetId = null;
  }

  // Select2 init for deny reasons
  if (window.jQuery && elDenyReason && jQuery.fn && jQuery.fn.select2) {
    jQuery(elDenyReason).select2({
      width: '100%',
      placeholder: 'Select reasons…',
      closeOnSelect: false,
      allowClear: true
    });
  }

  if (denyModal) {
    denyModal.addEventListener('click', (e) => { if (e.target === denyModal) closeDenyModal(); });
  }
  if (elDenyCancel) elDenyCancel.addEventListener('click', closeDenyModal);

  if (elDenyReason) {
    elDenyReason.addEventListener('change', () => {
      refreshCustomVisibility();
      recomputeDenyConfirmState();
    });
  }

  document.addEventListener('input', (e) => {
    if (e.target === elDenyOther) recomputeDenyConfirmState();
  });

  // ---------------- Compare modal ----------------
  const cmpModal = $('#faipCompareModal');
  const cmpClose = $('#faipCompareClose');
  const cmpImgOriginal = $('#faipCompareOriginal');
  const cmpImgFinal = $('#faipCompareFinal');

  function openCompareModal(originalUrl, finalUrl){
    if (!cmpModal) return;
    if (cmpImgOriginal) cmpImgOriginal.src = originalUrl || '';
    if (cmpImgFinal) cmpImgFinal.src = finalUrl || '';
    cmpModal.style.display = 'flex';
  }

  function closeCompareModal(){
    if (!cmpModal) return;
    cmpModal.style.display = 'none';
    if (cmpImgOriginal) cmpImgOriginal.src = '';
    if (cmpImgFinal) cmpImgFinal.src = '';
  }

  if (cmpModal) {
    cmpModal.addEventListener('click', (e) => { if (e.target === cmpModal) closeCompareModal(); });
  }
  if (cmpClose) cmpClose.addEventListener('click', closeCompareModal);

  // ---------------- Upload Final modal ----------------
  const upModal = $('#faipUploadModal');
  const upClose = $('#faipUploadClose');
  const upCancel = $('#faipUploadCancel');
  const upConfirm = $('#faipUploadConfirm');
  const upFile = $('#faipUploadFile');
  const upStatusbar = $('#faipUploadStatusbar');

  let uploadTargetId = null;

  function setUploadStatus(type, text){
    if (!upStatusbar) return;
    if (!text) { upStatusbar.innerHTML = ''; return; }
    upStatusbar.innerHTML = '<span class="ffda-inline-msg ' + (type || '') + '">' + String(text) + '</span>';
  }

  function openUploadModal(entryId){
    uploadTargetId = entryId;
    if (upFile) upFile.value = '';
    if (upConfirm) upConfirm.disabled = true;
    setUploadStatus('', '');
    if (upModal) upModal.style.display = 'flex';
  }

  function closeUploadModal(){
    if (upModal) upModal.style.display = 'none';
    setUploadStatus('', '');
    uploadTargetId = null;
  }

  if (upModal) upModal.addEventListener('click', (e) => { if (e.target === upModal) closeUploadModal(); });
  if (upClose) upClose.addEventListener('click', closeUploadModal);
  if (upCancel) upCancel.addEventListener('click', closeUploadModal);

  if (upFile) {
    upFile.addEventListener('change', () => {
      const has = upFile.files && upFile.files.length > 0;
      if (upConfirm) upConfirm.disabled = !has;
      if (has) setUploadStatus('', '');
    });
  }

  async function ajaxUploadFinal(entryId, file){
    const fd = new FormData();
    fd.append('action', (window.FAIP_AJAX && FAIP_AJAX.action_upload_final) ? FAIP_AJAX.action_upload_final : 'dot_frm_ai_upload_final_photo');
    fd.append('nonce', (window.FAIP_AJAX && FAIP_AJAX.nonce) ? FAIP_AJAX.nonce : '');
    fd.append('entry_id', String(entryId));
    fd.append('photo', file);

    const res = await fetch((window.FAIP_AJAX && FAIP_AJAX.ajax_url) ? FAIP_AJAX.ajax_url : window.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json || json.ok !== true) {
      throw new Error((json && json.error) ? json.error : 'Upload failed');
    }
    return json;
  }

  if (upConfirm) {
    upConfirm.addEventListener('click', async () => {
      if (!uploadTargetId) return;
      const file = (upFile && upFile.files && upFile.files[0]) ? upFile.files[0] : null;
      if (!file) return;

      try {
        upConfirm.disabled = true;
        setUploadStatus('', 'Uploading…');
        setStatus('', 'Uploading final photo for #' + uploadTargetId + '…');

        const json = await ajaxUploadFinal(uploadTargetId, file);

        if (json.final_url) {
          setRowFinalImage(uploadTargetId, json.final_url, 'Uploaded');
          setRowTmpUrl(uploadTargetId, '');
        }

        setUploadStatus('ok', 'Uploaded.');
        setStatus('', 'Uploaded final photo for #' + uploadTargetId);
        closeUploadModal();
      } catch (err) {
        console.error(err);

        const msg = (err && err.message) ? err.message : String(err);
        setUploadStatus('err', msg);
        setStatus('err', 'Upload error on #' + uploadTargetId + ': ' + msg);

        const has = upFile && upFile.files && upFile.files.length > 0;
        if (upConfirm) upConfirm.disabled = !has;
      }
    });
  }

  // ---------------- Edit Status/Notes modal ----------------
  const emModal = $('#faipEditMetaModal');
  const emClose = $('#faipEditMetaClose');
  const emCancel = $('#faipEditMetaCancel');
  const emConfirm = $('#faipEditMetaConfirm');
  const emStatus = $('#faipEditMetaStatus');
  const emNotes = $('#faipEditMetaNotes');

  let editMetaTargetId = null;

  function openEditMetaModal(entryId){
    editMetaTargetId = entryId;

    if (emNotes) emNotes.value = '';

    const curStatus = getRowStatusText(entryId);
    if (emStatus) emStatus.value = curStatus || '';

    if (emModal) emModal.style.display = 'flex';
  }

  function closeEditMetaModal(){
    if (emModal) emModal.style.display = 'none';
    editMetaTargetId = null;
  }

  if (emModal) emModal.addEventListener('click', (e) => { if (e.target === emModal) closeEditMetaModal(); });
  if (emClose) emClose.addEventListener('click', closeEditMetaModal);
  if (emCancel) emCancel.addEventListener('click', closeEditMetaModal);

  async function ajaxEditMeta(entryId, statusVal, notesVal){
    const fd = new FormData();
    fd.append('action', (window.FAIP_AJAX && FAIP_AJAX.action_edit_meta) ? FAIP_AJAX.action_edit_meta : 'dot_frm_ai_edit_status_notes');
    fd.append('nonce', (window.FAIP_AJAX && FAIP_AJAX.nonce) ? FAIP_AJAX.nonce : '');
    fd.append('entry_id', String(entryId));
    fd.append('status', String(statusVal || ''));
    fd.append('notes', String(notesVal || ''));

    const res = await fetch((window.FAIP_AJAX && FAIP_AJAX.ajax_url) ? FAIP_AJAX.ajax_url : window.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json || json.ok !== true) {
      throw new Error((json && json.error) ? json.error : 'Update failed');
    }
    return json;
  }

  if (emConfirm) {
    emConfirm.addEventListener('click', async () => {
      if (!editMetaTargetId) return;

      const statusVal = emStatus ? String(emStatus.value || '').trim() : '';
      const notesVal = emNotes ? String(emNotes.value || '').trim() : '';

      try {
        emConfirm.disabled = true;
        setStatus('', 'Updating #' + editMetaTargetId + '…');

        await ajaxEditMeta(editMetaTargetId, statusVal, notesVal);

        if (statusVal) setRowStatus(editMetaTargetId, statusVal);

        setStatus('', 'Updated #' + editMetaTargetId);
        closeEditMetaModal();
      } catch (err) {
        console.error(err);
        setStatus('err', 'Edit error on #' + editMetaTargetId + ': ' + (err && err.message ? err.message : String(err)));
      } finally {
        emConfirm.disabled = false;
      }
    });
  }

  // ESC closes compare, upload, deny, edit (priority order)
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;

    if (cmpModal && cmpModal.style.display === 'flex') { closeCompareModal(); return; }
    if (upModal && upModal.style.display === 'flex') { closeUploadModal(); return; }
    if (denyModal && denyModal.style.display === 'flex') { closeDenyModal(); return; }
    if (emModal && emModal.style.display === 'flex') { closeEditMetaModal(); return; }
  });

  // ---------- Row action buttons ----------
  if (elTbody) {
    elTbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;

      const action = btn.dataset.action;
      const id = parseInt(btn.dataset.id, 10);

      if (action === 'deny') { openDenyModal(id); return; }

      if (action === 'compare') {
        const originalUrl = (btn.dataset.original || '').trim();
        const finalUrl = (btn.dataset.final || '').trim();
        if (!originalUrl || !finalUrl) {
          setStatus('err', 'Compare: missing image urls');
          return;
        }
        openCompareModal(originalUrl, finalUrl);
        return;
      }

      if (action === 'upload_final') { openUploadModal(id); return; }
      if (action === 'edit_meta') { openEditMetaModal(id); return; }
      if (action === 'approve_row') { runRowApprove(id); return; }
    });
  }

  // ---------- DENY submit ----------
  if (elDenyConfirm) {
    elDenyConfirm.addEventListener('click', async () => {
      if (!denyTargetId) return;

      const reasons = getSelectedReasons();
      if (!reasons.length) return;

      const tr = document.querySelector('tr[data-row="' + denyTargetId + '"]');
      const orderId = tr ? (tr.dataset.orderId || '').trim() : '';
      if (!orderId) { setStatus('err', 'Missing order_id for #' + denyTargetId); return; }

      const isCustom = reasons.includes('__custom__');
      const customMsg = isCustom ? ((elDenyOther && elDenyOther.value) ? String(elDenyOther.value).trim() : '') : '';
      if (isCustom && !customMsg) return;

      try {
        elDenyConfirm.disabled = true;

        const fd = new FormData();
        fd.append('action', (window.FAIP_AJAX && FAIP_AJAX.action_deny) ? FAIP_AJAX.action_deny : 'dot_frm_ai_deny_photo');
        fd.append('nonce',  (window.FAIP_AJAX && FAIP_AJAX.nonce) ? FAIP_AJAX.nonce : '');
        fd.append('entry_id', String(denyTargetId));
        fd.append('order_id', String(orderId));

        reasons.forEach(r => fd.append('reasons[]', String(r)));
        if (isCustom) fd.append('custom_message', String(customMsg));

        const res = await fetch((window.FAIP_AJAX && FAIP_AJAX.ajax_url) ? FAIP_AJAX.ajax_url : window.ajaxurl, {
          method: 'POST',
          credentials: 'same-origin',
          body: fd
        });

        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json || json.ok !== true) {
          throw new Error((json && json.error) ? json.error : 'Deny request failed');
        }

        // update row status on success
        setRowStatus(denyTargetId, 'Denied');

        setStatus('', 'Denied #' + denyTargetId);
        closeDenyModal();
      } catch (err) {
        console.error(err);
        setStatus('err', 'Deny error on #' + denyTargetId + ': ' + (err && err.message ? err.message : String(err)));
      } finally {
        if (denyModal && denyModal.style.display === 'flex') recomputeDenyConfirmState();
      }
    });
  }

  // ---------- AI FIX / APPROVE ----------
  function setRowProcessing(entryId, on){
    const tr = elTbody ? elTbody.querySelector('tr[data-row="' + entryId + '"]') : null;
    if (!tr) return;

    const fixedBlock = tr.querySelector('.fixed-image-block');
    if (!fixedBlock) return;

    if (on) {
      fixedBlock.classList.add('faip-processing');
      if (!fixedBlock.querySelector('.faip-spinner')) {
        const sp = document.createElement('div');
        sp.className = 'faip-spinner';
        sp.style.margin = '6px 0';
        fixedBlock.appendChild(sp);
      }
    } else {
      fixedBlock.classList.remove('faip-processing');
      const sp = fixedBlock.querySelector('.faip-spinner');
      if (sp) sp.remove();
    }
  }

  function setRowFinalImage(entryId, finalUrl, labelText){
    const tr = elTbody ? elTbody.querySelector('tr[data-row="' + entryId + '"]') : null;
    if (!tr) return;

    const img = tr.querySelector('.fixed-image-block img.final-image');

    if (img && finalUrl) {
      // show loader until image is actually loaded
      setFinalImgLoading(entryId, true, 'Loading image…');

      img.onload = null;
      img.onerror = null;

      img.onload = () => {
        setFinalImgLoading(entryId, false);
      };

      img.onerror = () => {
        setFinalImgLoading(entryId, false);
        const wrap = tr.querySelector('.faip-ai-label-wrap');
        if (wrap) wrap.innerHTML = '<div class="faip-ai-label" style="color:#b42318;">Failed to load image</div>';
      };

      // cache bust so browser doesn't show old cached image
      const bust = (finalUrl.indexOf('?') >= 0) ? '&' : '?';
      img.src = String(finalUrl) + bust + 't=' + Date.now();
    }

    // label
    const wrap = tr.querySelector('.faip-ai-label-wrap');
    if (wrap) wrap.innerHTML = labelText ? '<div class="faip-ai-label">' + String(labelText) + '</div>' : '';

    // keep Compare button in sync with latest final image
    const originalImg = tr.querySelector('td img.original-image');
    const originalUrl = originalImg ? (originalImg.getAttribute('src') || '').trim() : '';

    let compareBtn = tr.querySelector('button[data-action="compare"]');

    if (originalUrl && finalUrl) {
      if (!compareBtn) {
        const finalTd = img ? img.closest('td') : null;
        if (finalTd) {
          const holder = document.createElement('div');
          holder.style.marginTop = '6px';
          holder.style.display = 'flex';
          holder.style.gap = '8px';
          holder.style.flexWrap = 'wrap';

          compareBtn = document.createElement('button');
          compareBtn.type = 'button';
          compareBtn.className = 'faip-btn faip-btn-compare';
          compareBtn.dataset.action = 'compare';
          compareBtn.dataset.id = String(entryId);
          compareBtn.textContent = 'Compare';

          const uploadBtn = document.createElement('button');
          uploadBtn.type = 'button';
          uploadBtn.className = 'faip-btn faip-btn-compare';
          uploadBtn.dataset.action = 'upload_final';
          uploadBtn.dataset.id = String(entryId);
          uploadBtn.textContent = 'Upload';

          holder.appendChild(compareBtn);
          holder.appendChild(uploadBtn);
          finalTd.appendChild(holder);
        }
      }

      if (compareBtn) {
        compareBtn.dataset.original = originalUrl;
        compareBtn.dataset.final = String(finalUrl);
      }
    } else {
      if (compareBtn) compareBtn.dataset.final = finalUrl ? String(finalUrl) : '';
    }
  }

  function setRowTmpUrl(entryId, tmpUrl){
    const tr = elTbody ? elTbody.querySelector('tr[data-row="' + entryId + '"]') : null;
    if (!tr) return;
    tr.dataset.aiTmpUrl = tmpUrl ? String(tmpUrl) : '';
  }

  function getRowTmpUrl(entryId){
    const tr = elTbody ? elTbody.querySelector('tr[data-row="' + entryId + '"]') : null;
    if (!tr) return '';
    return (tr.dataset.aiTmpUrl || '').trim();
  }

  async function ajaxFixOne(entryId){
    const fd = new FormData();
    fd.append('action', (window.FAIP_AJAX && FAIP_AJAX.action) ? FAIP_AJAX.action : 'dot_frm_ai_fix_photo');
    fd.append('nonce',  (window.FAIP_AJAX && FAIP_AJAX.nonce) ? FAIP_AJAX.nonce : '');
    fd.append('entry_id', String(entryId));

    const prompts = selectedPrompts();
    prompts.forEach(p => fd.append('prompts[]', p));

    const res = await fetch((window.FAIP_AJAX && FAIP_AJAX.ajax_url) ? FAIP_AJAX.ajax_url : window.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json || json.ok !== true) {
      const msg = (json && json.error) ? json.error : ('Request failed for #' + entryId);
      throw new Error(msg);
    }
    return json;
  }

  async function ajaxApproveOne(entryId, tmpUrl){
    const fd = new FormData();
    fd.append('action', (window.FAIP_AJAX && FAIP_AJAX.action_approve) ? FAIP_AJAX.action_approve : 'dot_frm_ai_approve_photo');
    fd.append('nonce',  (window.FAIP_AJAX && FAIP_AJAX.nonce) ? FAIP_AJAX.nonce : '');
    fd.append('entry_id', String(entryId));
    fd.append('tmp_url', String(tmpUrl || ''));

    const res = await fetch((window.FAIP_AJAX && FAIP_AJAX.ajax_url) ? FAIP_AJAX.ajax_url : window.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json || json.ok !== true) {
      const msg = (json && json.error) ? json.error : ('Approve failed for #' + entryId);
      throw new Error(msg);
    }
    return json;
  }

  // ✅ NEW: row approve handler (new backend action)
  async function ajaxApproveRow(entryId, orderId){
    const fd = new FormData();
    fd.append('action', (window.FAIP_AJAX && FAIP_AJAX.action_approve_row) ? FAIP_AJAX.action_approve_row : 'dot_frm_ai_approve_row');
    fd.append('nonce',  (window.FAIP_AJAX && FAIP_AJAX.nonce) ? FAIP_AJAX.nonce : '');
    fd.append('entry_id', String(entryId));
    fd.append('order_id', String(orderId || ''));

    const res = await fetch((window.FAIP_AJAX && FAIP_AJAX.ajax_url) ? FAIP_AJAX.ajax_url : window.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json || json.ok !== true) {
      const msg = (json && json.error) ? json.error : ('Approve request failed for #' + entryId);
      throw new Error(msg);
    }
    return json;
  }

  async function runRowApprove(entryId){
    const tr = document.querySelector('tr[data-row="' + entryId + '"]');
    const orderId = tr ? (tr.dataset.orderId || '').trim() : '';
    if (!orderId) { setStatus('err', 'Missing order_id for #' + entryId); return; }

    const btn = tr ? tr.querySelector('button[data-action="approve_row"][data-id="' + entryId + '"]') : null;

    try {
      if (btn) btn.disabled = true;
      setStatus('', 'Approving #' + entryId + '…');

      await ajaxApproveRow(entryId, orderId);

      setRowStatus(entryId, 'Approved');

      setStatus('', 'Approved #' + entryId);
    } catch (err) {
      console.error(err);
      setStatus('err', 'Approve error on #' + entryId + ': ' + (err && err.message ? err.message : String(err)));
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  async function runBulkFix(){
    if (!elTbody) return;

    const ids = selectedIds();
    if (!ids.length) { setStatus('err', 'Select at least one entry.'); return; }

    if (elBulkFix) elBulkFix.disabled = true;
    if (elBulkApprove) elBulkApprove.disabled = true;
    if (elCheckAll) elCheckAll.disabled = true;
    $$('#faipTbody input[type="checkbox"][data-id]').forEach(cb => cb.disabled = true);

    setStatus('', 'Processing ' + ids.length + ' entr' + (ids.length === 1 ? 'y' : 'ies') + '…');

    let okCount = 0;
    for (let i = 0; i < ids.length; i++) {
      const entryId = ids[i];
      try {
        setRowProcessing(entryId, true);

        const json = await ajaxFixOne(entryId);

        if (json.final_url) {
          setRowFinalImage(entryId, json.final_url, json.label || 'Edit by AI');
          setRowTmpUrl(entryId, json.final_url);
        } else {
          setRowTmpUrl(entryId, '');
        }

        okCount++;
        setStatus('', 'Processed ' + okCount + '/' + ids.length + '…');
      } catch (err) {
        console.error(err);
        setStatus('err', 'Error on #' + entryId + ': ' + (err && err.message ? err.message : String(err)));
      } finally {
        setRowProcessing(entryId, false);
      }
    }

    setStatus('', 'Done. Updated ' + okCount + '/' + ids.length + '.');

    if (elCheckAll) elCheckAll.disabled = false;
    $$('#faipTbody input[type="checkbox"][data-id]').forEach(cb => cb.disabled = false);

    updateBulkButtons();
  }

  async function runBulkApprove(){
    if (!elTbody) return;

    const ids = selectedIds();
    if (!ids.length) { setStatus('err', 'Select at least one entry.'); return; }

    const idsToApprove = ids.filter(id => !!getRowTmpUrl(id));
    if (!idsToApprove.length) {
      setStatus('', 'Nothing to approve: no “Edit by AI” images on selected rows.');
      return;
    }

    if (elBulkFix) elBulkFix.disabled = true;
    if (elBulkApprove) elBulkApprove.disabled = true;
    if (elCheckAll) elCheckAll.disabled = true;
    $$('#faipTbody input[type="checkbox"][data-id]').forEach(cb => cb.disabled = true);

    setStatus('', 'Approving ' + idsToApprove.length + ' entr' + (idsToApprove.length === 1 ? 'y' : 'ies') + '…');

    let okCount = 0;
    for (let i = 0; i < idsToApprove.length; i++) {
      const entryId = idsToApprove[i];
      const tmpUrl = getRowTmpUrl(entryId);

      try {
        setRowProcessing(entryId, true);

        await ajaxApproveOne(entryId, tmpUrl);

        okCount++;
        setStatus('', 'Approved ' + okCount + '/' + idsToApprove.length + '…');

        setRowFinalImage(entryId, tmpUrl, 'Approved');
        setRowTmpUrl(entryId, '');

        setRowStatus(entryId, 'Approved');
      } catch (err) {
        console.error(err);
        setStatus('err', 'Approve error on #' + entryId + ': ' + (err && err.message ? err.message : String(err)));
      } finally {
        setRowProcessing(entryId, false);
      }
    }

    setStatus('', 'Approve done. Updated ' + okCount + '/' + idsToApprove.length + '.');

    if (elCheckAll) elCheckAll.disabled = false;
    $$('#faipTbody input[type="checkbox"][data-id]').forEach(cb => cb.disabled = false);

    updateBulkButtons();
  }

  if (elBulkFix) elBulkFix.addEventListener('click', (e) => { e.preventDefault(); runBulkFix(); });
  if (elBulkApprove) elBulkApprove.addEventListener('click', (e) => { e.preventDefault(); runBulkApprove(); });

  updateBulkButtons();
})();