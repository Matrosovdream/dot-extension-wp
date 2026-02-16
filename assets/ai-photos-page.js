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

  if (statusSel && form) {
    statusSel.addEventListener('change', () => form.submit());
  }

  if (qInput && form) {
    qInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        form.submit();
      }
    });
  }

  // Bulk selection UI
  const elTbody   = $('#faipTbody');
  const elCheckAll= $('#faipCheckAll');
  const elBulkFix = $('#faipBulkFix');
  const elBulkApprove = $('#faipBulkApprove');

  function selectedIds(){
    return $$('#faipTbody input[type="checkbox"][data-id]:checked').map(cb => parseInt(cb.dataset.id,10)).filter(Boolean);
  }

  function allRowIds(){
    return $$('#faipTbody tr[data-row]').map(tr => parseInt(tr.dataset.row,10)).filter(Boolean);
  }

  function updateBulkButtons(){
    const any = selectedIds().length > 0;
    if (elBulkFix) elBulkFix.disabled = !any;
    if (elBulkApprove) elBulkApprove.disabled = !any;
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

  // Deny modal (prototype UI only)
  const modal = $('#faipDenyModal');
  const elDenyReason = $('#faipDenyReason');
  const elDenyOtherWrap = $('#faipDenyOtherWrap');
  const elDenyOther = $('#faipDenyOther');
  const elDenyCancel = $('#faipDenyCancel');
  const elDenyConfirm = $('#faipDenyConfirm');
  let denyTargetId = null;

  function openDenyModal(entryId){
    denyTargetId = entryId;
    if (elDenyReason) elDenyReason.value = '';
    if (elDenyOther) elDenyOther.value = '';
    if (elDenyOtherWrap) elDenyOtherWrap.style.display = 'none';
    if (elDenyConfirm) elDenyConfirm.disabled = true;
    if (modal) modal.style.display = 'flex';
  }

  function closeDenyModal(){
    if (modal) modal.style.display = 'none';
    denyTargetId = null;
  }

  if (elTbody) {
    elTbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;

      const action = btn.dataset.action;
      const id = parseInt(btn.dataset.id, 10);

      if (action === 'deny') {
        openDenyModal(id);
        return;
      }

      if (action === 'view') {
        alert('Open entry #' + id);
      }
    });
  }

  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeDenyModal();
    });
  }
  if (elDenyCancel) elDenyCancel.addEventListener('click', closeDenyModal);

  if (elDenyReason && elDenyOtherWrap && elDenyOther && elDenyConfirm) {
    elDenyReason.addEventListener('change', () => {
      const v = elDenyReason.value;
      elDenyOtherWrap.style.display = (v === 'Other') ? 'block' : 'none';
      const ok = v && (v !== 'Other' || (elDenyOther.value || '').trim());
      elDenyConfirm.disabled = !ok;
    });

    elDenyOther.addEventListener('input', () => {
      const ok = elDenyReason.value === 'Other' && (elDenyOther.value || '').trim();
      elDenyConfirm.disabled = !ok;
    });

    elDenyConfirm.addEventListener('click', () => {
      if (!denyTargetId) return;
      let reason = elDenyReason.value;
      if (reason === 'Other') reason = (elDenyOther.value || '').trim();
      if (!reason) return;

      alert('Deny entry #' + denyTargetId + ': ' + reason);
      closeDenyModal();
    });
  }

  // ---------- AI FIX (Bulk) ----------
  function setRowProcessing(entryId, on){
    const tr = elTbody ? elTbody.querySelector('tr[data-row="' + entryId + '"]') : null;
    if (!tr) return;

    const fixedBlock = tr.querySelector('.fixed-image-block');
    if (!fixedBlock) return;

    if (on) {
      fixedBlock.classList.add('faip-processing');
      // Avoid stacking multiple spinners
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
      img.src = finalUrl;
    }

    const wrap = tr.querySelector('.faip-ai-label-wrap');
    if (wrap) {
      wrap.innerHTML = labelText ? '<div class="faip-ai-label">' + String(labelText) + '</div>' : '';
    }
  }

  // Store tmp_url on the row so Approve knows what to send
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

  async function runBulkFix(){
    if (!elTbody) return;

    const ids = selectedIds(); // process selected rows (button enabled only when selected)
    if (!ids.length) {
      setStatus('err', 'Select at least one entry.');
      return;
    }

    // UI lock
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
          setRowTmpUrl(entryId, json.final_url); // <-- remember tmp url for Approve
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

    // Unlock
    if (elCheckAll) elCheckAll.disabled = false;
    $$('#faipTbody input[type="checkbox"][data-id]').forEach(cb => cb.disabled = false);

    updateBulkButtons();
  }

  async function runBulkApprove(){
    if (!elTbody) return;

    const ids = selectedIds();
    if (!ids.length) {
      setStatus('err', 'Select at least one entry.');
      return;
    }

    // Only approve those with tmp url (means AI edited)
    const idsToApprove = ids.filter(id => !!getRowTmpUrl(id));
    if (!idsToApprove.length) {
      setStatus('', 'Nothing to approve: no “Edit by AI” images on selected rows.');
      return;
    }

    // UI lock
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

        // Visual mark + prevent re-approve with same tmp
        setRowFinalImage(entryId, tmpUrl, 'Approved');
        setRowTmpUrl(entryId, '');
      } catch (err) {
        console.error(err);
        setStatus('err', 'Approve error on #' + entryId + ': ' + (err && err.message ? err.message : String(err)));
      } finally {
        setRowProcessing(entryId, false);
      }
    }

    setStatus('', 'Approve done. Updated ' + okCount + '/' + idsToApprove.length + '.');

    // Unlock
    if (elCheckAll) elCheckAll.disabled = false;
    $$('#faipTbody input[type="checkbox"][data-id]').forEach(cb => cb.disabled = false);

    updateBulkButtons();
  }

  if (elBulkFix) {
    elBulkFix.addEventListener('click', (e) => {
      e.preventDefault();
      runBulkFix();
    });
  }

  if (elBulkApprove) {
    elBulkApprove.addEventListener('click', (e) => {
      e.preventDefault();
      runBulkApprove();
    });
  }

  updateBulkButtons();
})();
