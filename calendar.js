var CalendarBoard = (function() {
  'use strict';
  var config = {};
  var recentColors = [];
  var COLOR_STORAGE_KEY = 'cal_recent_colors';
  var THEME_STORAGE_KEY = 'cal_theme';
  var HIMG_STORAGE_KEY  = 'cal_header_image';
  var MAX_RECENT_COLORS = 12;
  var formsBound = false;
  var currentSelectedDay = null;

  var VALID_THEMES = ['sakura', 'ocean', 'melon', 'kuromi', 'mocha', 'lemon'];

  var pendingFileData = null;

  function q(id){ return document.getElementById(id); }

  function init(opts){
    config = opts || config || {};
    loadRecentColors();
    applyStoredTheme();
    applyStoredHeaderImage();
    bindGlobalClickDelegation();
    bindDayClicks();
    bindMonthNav();
    bindGoogleAuthTopNavigation();
    bindGoogleRefresh();
    bindImageModal();
    bindDdayTypeRadios();
    if (!formsBound) { formsBound = true; }
  }

  /* ══════════════════════════
     테마 관리
     ══════════════════════════ */
  function applyStoredTheme(){
    var stored = 'sakura';
    try { stored = localStorage.getItem(THEME_STORAGE_KEY) || 'sakura'; } catch(e){}
    if (VALID_THEMES.indexOf(stored) === -1) stored = 'sakura';
    setTheme(stored);
  }

  function setTheme(name){
    var board = document.getElementById('calendar-board');
    if (!board) return;
    for (var i = 0; i < VALID_THEMES.length; i++) board.classList.remove('theme-' + VALID_THEMES[i]);
    if (name !== 'sakura') board.classList.add('theme-' + name);
    var dots = board.querySelectorAll('.cal-theme-dot');
    for (var j = 0; j < dots.length; j++) {
      dots[j].classList.toggle('active', dots[j].getAttribute('data-theme') === name);
    }
    try { localStorage.setItem(THEME_STORAGE_KEY, name); } catch(e){}
  }

  /* ══════════════════════════
     헤더 이미지 관리
     ══════════════════════════ */
  function getHeaderImageData(){
    try {
      var raw = localStorage.getItem(HIMG_STORAGE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch(e){ return null; }
  }

  function saveHeaderImageData(data){
    try {
      if (data) localStorage.setItem(HIMG_STORAGE_KEY, JSON.stringify(data));
      else localStorage.removeItem(HIMG_STORAGE_KEY);
    } catch(e){}
  }

  function applyStoredHeaderImage(){
    var data = getHeaderImageData();
    var container = q('cal-header-image');
    var imgEl = q('cal-header-img-el');
    var placeholder = q('cal-header-placeholder');
    var removeBtn = q('btn-header-img-remove');
    if (!container || !imgEl) return;

    if (data && data.src) {
      imgEl.src = data.src;
      imgEl.style.display = 'block';
      imgEl.style.height = (data.height || 160) + 'px';
      imgEl.style.objectFit = data.fit || 'cover';
      if (removeBtn) removeBtn.style.display = '';
      container.classList.add('has-image');
    } else {
      imgEl.src = '';
      imgEl.style.display = 'none';
      if (removeBtn) removeBtn.style.display = 'none';
      container.classList.remove('has-image');
    }
  }

  function bindImageModal(){
    var tabs = document.querySelectorAll('.cal-img-tab');
    for (var i = 0; i < tabs.length; i++) {
      tabs[i].addEventListener('click', function(){
        var tabName = this.getAttribute('data-tab');
        var allTabs = document.querySelectorAll('.cal-img-tab');
        for (var j = 0; j < allTabs.length; j++) allTabs[j].classList.remove('active');
        this.classList.add('active');
        var urlPanel = q('cal-img-tab-url');
        var filePanel = q('cal-img-tab-file');
        if (urlPanel) urlPanel.style.display = tabName === 'url' ? '' : 'none';
        if (filePanel) filePanel.style.display = tabName === 'file' ? '' : 'none';
      });
    }

    var urlInput = q('cal-img-url-input');
    if (urlInput) {
      var debounce = null;
      urlInput.addEventListener('input', function(){
        clearTimeout(debounce);
        debounce = setTimeout(function(){
          var preview = q('cal-img-url-preview');
          if (!preview) return;
          var v = urlInput.value.trim();
          if (v && /^https?:\/\/.+/i.test(v)) {
            preview.innerHTML = '<img src="' + esc(v) + '" onerror="this.style.display=\'none\'" style="max-width:100%;max-height:180px;border-radius:8px;">';
          } else {
            preview.innerHTML = '';
          }
        }, 400);
      });
    }

    var dropZone = q('cal-file-drop');
    var fileInput = q('cal-img-file-input');
    if (dropZone && fileInput) {
      dropZone.addEventListener('click', function(){ fileInput.click(); });
      dropZone.addEventListener('dragover', function(e){ e.preventDefault(); dropZone.classList.add('dragover'); });
      dropZone.addEventListener('dragleave', function(){ dropZone.classList.remove('dragover'); });
      dropZone.addEventListener('drop', function(e){
        e.preventDefault(); dropZone.classList.remove('dragover');
        if (e.dataTransfer.files && e.dataTransfer.files[0]) handleFileSelect(e.dataTransfer.files[0]);
      });
      fileInput.addEventListener('change', function(){
        if (fileInput.files && fileInput.files[0]) handleFileSelect(fileInput.files[0]);
      });
    }
  }

  function handleFileSelect(file){
    if (!file || !file.type.match(/^image\//)) { alert('이미지 파일만 선택 가능합니다.'); return; }
    if (file.size > 5 * 1024 * 1024) { alert('5MB 이하의 이미지만 사용할 수 있습니다.'); return; }
    var reader = new FileReader();
    reader.onload = function(e){
      pendingFileData = e.target.result;
      var preview = q('cal-img-file-preview');
      if (preview) preview.innerHTML = '<img src="' + pendingFileData + '" style="max-width:100%;max-height:180px;border-radius:8px;">';
    };
    reader.readAsDataURL(file);
  }

  /* ══════════════════════════
     최근 사용 색상
     ══════════════════════════ */
  function loadRecentColors(){
    try {
      var stored = localStorage.getItem(COLOR_STORAGE_KEY);
      recentColors = stored ? JSON.parse(stored) : [];
      if (!Array.isArray(recentColors)) recentColors = [];
    } catch(e){ recentColors = []; }
  }
  function saveRecentColor(color){
    if (!color || !/^#[0-9a-fA-F]{6}$/.test(color)) return;
    var idx = recentColors.indexOf(color);
    if (idx > -1) recentColors.splice(idx, 1);
    recentColors.unshift(color);
    if (recentColors.length > MAX_RECENT_COLORS) recentColors = recentColors.slice(0, MAX_RECENT_COLORS);
    try { localStorage.setItem(COLOR_STORAGE_KEY, JSON.stringify(recentColors)); } catch(e){}
  }
  function renderRecentColorPalette(){
    var container = q('cal-recent-colors');
    if (!container) return;
    var html = '';
    if (recentColors.length === 0) {
      html = '<span style="color:#94a3b8;font-size:12px;">최근 사용한 색상 없음</span>';
    } else {
      for (var i = 0; i < recentColors.length; i++) {
        html += '<span class="cal-color-swatch js-color-pick" data-color="' + esc(recentColors[i]) + '" '
             + 'style="display:inline-block;width:28px;height:28px;border-radius:6px;cursor:pointer;'
             + 'border:2px solid #e5e7eb;background:' + esc(recentColors[i]) + ';" '
             + 'title="' + esc(recentColors[i]) + '"></span>';
      }
    }
    container.innerHTML = html;
  }

  function closest(el, sel){
    if (!el) return null;
    if (el.closest) return el.closest(sel);
    while(el && el !== document){ if (el.matches && el.matches(sel)) return el; el = el.parentElement; }
    return null;
  }

  function getSelectedDateStr(day){
    var y = String(config.year);
    var m = String(config.month); if (m.length < 2) m = '0' + m;
    var d = String(day); if (d.length < 2) d = '0' + d;
    return y + '-' + m + '-' + d;
  }

  /* D-day 계산 헬퍼 */
  function calcDday(dateStr) {
    if (!dateStr) return null;
    var today = new Date();
    today.setHours(0,0,0,0);
    var target = new Date(dateStr + 'T00:00:00');
    var diff = Math.round((target - today) / 86400000);
    if (diff < 0) return null; // 지난 목표
    return diff === 0 ? 'D-Day!' : 'D-' + diff;
  }

  /* D-day 기념일 계산 헬퍼 (과거도 D+N으로 표시) */
  function calcDdayAnniversary(dateStr) {
    if (!dateStr) return null;
    var today = new Date();
    today.setHours(0,0,0,0);
    var target = new Date(dateStr + 'T00:00:00');
    var diff = Math.round((target - today) / 86400000);
    if (diff === 0) return 'D-Day!';
    if (diff > 0) return 'D-' + diff;
    return 'D+' + Math.abs(diff);
  }

  function isDdayPast(dateStr) {
    if (!dateStr) return true;
    var today = new Date();
    today.setHours(0,0,0,0);
    var target = new Date(dateStr + 'T00:00:00');
    return target < today;
  }

  /* ══════════════════════════
     이벤트 위임
     ══════════════════════════ */
  function bindGlobalClickDelegation() {
    document.removeEventListener('click', globalClickHandler, true);
    document.addEventListener('click', globalClickHandler, true);
  }

  function globalClickHandler(e){
    var t = e.target;
    if (!t) return;

    // 테마 도트
    var themeDot = closest(t, '.cal-theme-dot');
    if (themeDot) {
      e.preventDefault(); e.stopPropagation();
      var themeName = themeDot.getAttribute('data-theme');
      if (themeName && VALID_THEMES.indexOf(themeName) > -1) setTheme(themeName);
      return;
    }

    // 헤더 이미지 설정 버튼
    if (closest(t, '#btn-header-img')) {
      e.preventDefault(); e.stopPropagation();
      openImageModal(); return;
    }

    if (closest(t, '#cal-header-placeholder')) {
      e.preventDefault(); e.stopPropagation();
      openImageModal(); return;
    }

    if (closest(t, '#btn-header-img-remove')) {
      e.preventDefault(); e.stopPropagation();
      saveHeaderImageData(null);
      applyStoredHeaderImage();
      return;
    }

    if (closest(t, '#btn-img-save')) {
      e.preventDefault(); e.stopPropagation();
      handleImageSave(); return;
    }

    if (closest(t, '#cal-img-modal-close') || closest(t, '#cal-img-backdrop')) {
      e.preventDefault(); closeImageModal(); return;
    }

    // Goal 모달 열기
    if (closest(t, '#btn-open-goals')) {
      e.preventDefault(); e.stopPropagation();
      openDdayModal('goal'); return;
    }

    // Goal 모달 탭 전환
    var ddayTab = closest(t, '.cal-dday-tab');
    if (ddayTab) {
      e.preventDefault(); e.stopPropagation();
      var tabName = ddayTab.getAttribute('data-dday-tab');
      if (tabName) openDdayModal(tabName);
      return;
    }

    // Goal 모달 닫기
    if (closest(t, '#cal-goal-modal-close') || closest(t, '#cal-goal-backdrop')) {
      e.preventDefault(); closeGoalModal(); return;
    }

    var colorPick = closest(t, '.js-color-pick');
    if (colorPick) {
      e.preventDefault(); e.stopPropagation();
      var c = colorPick.getAttribute('data-color');
      if (c) { var ci = q('modal_wr_3'); if (ci) ci.value = c; }
      return;
    }

    var copyBtn = closest(t, '.js-copy');
    if (copyBtn) {
      e.preventDefault(); e.stopPropagation();
      var copyId = copyBtn.getAttribute('data-id');
      if (copyId && copyId !== '0' && copyId !== '') openCopyModal(copyId);
      return;
    }

    var editBtn = closest(t, '.js-edit');
    if (editBtn) {
      e.preventDefault(); e.stopPropagation();
      openWriteModal('u', {
        id: editBtn.getAttribute('data-wr-id') || '',
        subject: editBtn.getAttribute('data-subject') || '',
        content: editBtn.getAttribute('data-content') || '',
        date: editBtn.getAttribute('data-date') || '',
        end_date: editBtn.getAttribute('data-end-date') || '',
        color: editBtn.getAttribute('data-color') || '#3B82F6',
        time_start: editBtn.getAttribute('data-time-start') || '',
        time_end: editBtn.getAttribute('data-time-end') || '',
        is_goal: editBtn.getAttribute('data-is-goal') === 'true' || editBtn.getAttribute('data-is-goal') === '1',
        is_dday: editBtn.getAttribute('data-is-dday') === 'true' || editBtn.getAttribute('data-is-dday') === '1',
        is_widget: editBtn.getAttribute('data-is-widget') === 'true' || editBtn.getAttribute('data-is-widget') === '1'
      });
      return;
    }

    var delBtn = closest(t, '.js-delete');
    if (delBtn) {
      e.preventDefault(); e.stopPropagation();
      var delId = delBtn.getAttribute('data-id');
      if (delId && confirm('이 일정을 삭제하시겠습니까?')) handleDelete(delId);
      return;
    }

    var viewBtn = closest(t, '.js-view');
    if (viewBtn) {
      e.preventDefault(); e.stopPropagation();
      openViewModal({
        id: viewBtn.getAttribute('data-wr-id') || '',
        subject: viewBtn.getAttribute('data-subject') || '',
        content: viewBtn.getAttribute('data-content') || '',
        date: viewBtn.getAttribute('data-date') || '',
        end_date: viewBtn.getAttribute('data-end-date') || '',
        color: viewBtn.getAttribute('data-color') || '#3B82F6',
        time_start: viewBtn.getAttribute('data-time-start') || '',
        time_end: viewBtn.getAttribute('data-time-end') || '',
        is_goal: viewBtn.getAttribute('data-is-goal') === 'true' || viewBtn.getAttribute('data-is-goal') === '1',
        is_dday: viewBtn.getAttribute('data-is-dday') === 'true' || viewBtn.getAttribute('data-is-dday') === '1',
        is_widget: viewBtn.getAttribute('data-is-widget') === 'true' || viewBtn.getAttribute('data-is-widget') === '1'
      });
      return;
    }

    var saveBtn = closest(t, '#btn-save-event');
    if (saveBtn) { e.preventDefault(); e.stopPropagation(); handleEventSave(); return; }
    var copyExecBtn = closest(t, '#btn-exec-copy');
    if (copyExecBtn) { e.preventDefault(); e.stopPropagation(); handleCopyExec(); return; }
    if (closest(t, '#cal-modal-close') || closest(t, '#cal-modal-backdrop')) { e.preventDefault(); closeWriteModal(); return; }
    if (closest(t, '#cal-copy-close') || closest(t, '#cal-copy-backdrop')) { e.preventDefault(); closeCopyModal(); return; }
    if (closest(t, '#cal-view-close') || closest(t, '#cal-view-backdrop')) { e.preventDefault(); closeViewModal(); return; }
    if (closest(t, '#cal-detail-close')) {
      e.preventDefault();
      var panel = q('cal-detail-panel'); if (panel) panel.style.display = 'none';
      currentSelectedDay = null; return;
    }
    var openBtn = closest(t, '#btn-open-write-modal');
    if (openBtn) { e.preventDefault(); e.stopPropagation(); openWriteModal('', null); return; }
    var addForDay = closest(t, '#btn-add-for-day');
    if (addForDay) {
      e.preventDefault(); e.stopPropagation();
      var dateStr = currentSelectedDay ? getSelectedDateStr(currentSelectedDay) : '';
      openWriteModal('', { date: dateStr, end_date: dateStr }); return;
    }
    var viewEditBtn = closest(t, '#btn-view-edit');
    if (viewEditBtn) {
      e.preventDefault(); e.stopPropagation();
      var vd = getViewModalData(); closeViewModal(); openWriteModal('u', vd); return;
    }
    var viewDelBtn = closest(t, '#btn-view-delete');
    if (viewDelBtn) {
      e.preventDefault(); e.stopPropagation();
      var vid = viewDelBtn.getAttribute('data-id');
      if (vid && confirm('이 일정을 삭제하시겠습니까?')) { closeViewModal(); handleDelete(vid); }
      return;
    }
  }

  /* ══════════════════════════
     이미지 모달
     ══════════════════════════ */
  function openImageModal(){
    var m = q('cal-img-modal'); if (!m) return;
    pendingFileData = null;
    var data = getHeaderImageData();
    var urlInput = q('cal-img-url-input');
    var heightInput = q('cal-img-height-input');
    var fitSelect = q('cal-img-fit-select');
    if (urlInput) urlInput.value = (data && data.type === 'url' && data.src) ? data.src : '';
    if (heightInput) heightInput.value = (data && data.height) ? data.height : 160;
    if (fitSelect) fitSelect.value = (data && data.fit) ? data.fit : 'cover';
    var urlPreview = q('cal-img-url-preview');
    var filePreview = q('cal-img-file-preview');
    if (urlPreview) urlPreview.innerHTML = '';
    if (filePreview) filePreview.innerHTML = '';
    var allTabs = document.querySelectorAll('.cal-img-tab');
    for (var i = 0; i < allTabs.length; i++) allTabs[i].classList.toggle('active', allTabs[i].getAttribute('data-tab') === 'url');
    var urlPanel = q('cal-img-tab-url');
    var filePanel = q('cal-img-tab-file');
    if (urlPanel) urlPanel.style.display = '';
    if (filePanel) filePanel.style.display = 'none';
    m.style.display = 'block';
    document.body.classList.add('modal-open');
  }

  function closeImageModal(){
    var m = q('cal-img-modal');
    if (m) m.style.display = 'none';
    document.body.classList.remove('modal-open');
    pendingFileData = null;
  }

  function handleImageSave(){
    var heightInput = q('cal-img-height-input');
    var fitSelect = q('cal-img-fit-select');
    var height = heightInput ? parseInt(heightInput.value, 10) || 160 : 160;
    if (height < 60) height = 60;
    if (height > 400) height = 400;
    var fit = fitSelect ? fitSelect.value : 'cover';

    var activeTab = document.querySelector('.cal-img-tab.active');
    var tabName = activeTab ? activeTab.getAttribute('data-tab') : 'url';

    var src = '';
    var type = '';
    if (tabName === 'file' && pendingFileData) {
      src = pendingFileData;
      type = 'file';
    } else {
      var urlInput = q('cal-img-url-input');
      src = urlInput ? urlInput.value.trim() : '';
      type = 'url';
    }

    if (!src) {
      saveHeaderImageData(null);
      applyStoredHeaderImage();
      closeImageModal();
      return;
    }

    saveHeaderImageData({ src: src, type: type, height: height, fit: fit });
    applyStoredHeaderImage();
    closeImageModal();
  }

  /* ══════════════════════════
     D-day 통합 모달 (Goal + D-day 탭)
     ══════════════════════════ */
  function openDdayModal(tabName){
    var m = q('cal-goal-modal'); if (!m) return;
    // 탭 전환
    var tabs = m.querySelectorAll('.cal-dday-tab');
    var panels = m.querySelectorAll('.cal-dday-tab-panel');
    var targetTab = tabName || 'goal';
    for (var i = 0; i < tabs.length; i++) {
      tabs[i].classList.toggle('active', tabs[i].getAttribute('data-dday-tab') === targetTab);
    }
    var goalPanel = q('cal-dday-tab-goal');
    var ddayPanel = q('cal-dday-tab-dday');
    if (goalPanel) goalPanel.style.display = targetTab === 'goal' ? '' : 'none';
    if (ddayPanel) ddayPanel.style.display = targetTab === 'dday' ? '' : 'none';
    m.style.display = 'block';
    document.body.classList.add('modal-open');
  }

  function openGoalModal(){ openDdayModal('goal'); }

  function closeGoalModal(){
    var m = q('cal-goal-modal');
    if (m) m.style.display = 'none';
    document.body.classList.remove('modal-open');
  }

  /* ══════════════════════════
     날짜 클릭
     ══════════════════════════ */
  function bindDayClicks(){
    var days = document.querySelectorAll('.cal-day:not(.cal-day-empty)');
    for (var i=0;i<days.length;i++){
      (function(dayEl){
        dayEl.onclick = function(){
          var day = dayEl.getAttribute('data-day');
          var raw = dayEl.getAttribute('data-events') || '[]';
          var events = [];
          try { var tx = document.createElement('textarea'); tx.innerHTML = raw; events = JSON.parse(tx.value); } catch(ex){ events = []; }
          currentSelectedDay = parseInt(day, 10);
          renderDetail(day, events);
        };
      })(days[i]);
    }
  }

  function renderDetail(day, events){
    var panel = q('cal-detail-panel');
    var dateEl = q('cal-detail-date');
    var list = q('cal-detail-list');
    if (!panel || !dateEl || !list) return;
    dateEl.textContent = config.year + '년 ' + config.month + '월 ' + day + '일 일정';
    var html = '';
    if (!events || !events.length) {
      html = '<div class="cal-detail-empty">등록된 일정이 없습니다.</div>';
    } else {
      for (var i=0;i<events.length;i++){
        var e = events[i];
        var timeStr = '';
        if (e.time_start) { timeStr = e.time_start; if (e.time_end) timeStr += ' ~ ' + e.time_end; }

        // D-day 배지
        var goalBadge = '';
        if (e.is_goal) {
          var ddayText = calcDday(e.date);
          if (ddayText) {
            var ddayClass = ddayText === 'D-Day!' ? 'cal-detail-goal-badge dday-today' : 'cal-detail-goal-badge';
            goalBadge = '<span class="' + ddayClass + '">' + esc(ddayText) + '</span>';
          }
        } else if (e.is_dday) {
          var ddayAnnText = calcDdayAnniversary(e.date);
          if (ddayAnnText) {
            var ddayAnnClass = 'cal-detail-dday-badge' + (ddayAnnText === 'D-Day!' ? ' dday-today' : '');
            goalBadge = '<span class="' + ddayAnnClass + '">' + esc(ddayAnnText) + '</span>';
          }
        }

        html += '<div class="cal-detail-item">';
        html += '<div class="cal-detail-dot" style="background:' + esc(e.color || '#3B82F6') + '"></div>';
        html += '<div class="cal-detail-info">';
        html += '<div class="cal-detail-subject">' + esc(e.subject || '') + goalBadge + '</div>';
        if (timeStr) html += '<div class="cal-detail-desc">' + esc(timeStr) + '</div>';
        if (e.preview) html += '<div class="cal-detail-desc">' + esc(e.preview) + '</div>';
        html += '<div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap;">';
        html += '<button type="button" class="cal-btn cal-btn-mini js-edit" data-wr-id="'+esc(String(e.id))+'" data-subject="'+esc(e.subject||'')+'" data-content="'+esc(e.content||'')+'" data-date="'+esc(e.date||'')+'" data-end-date="'+esc(e.end_date||'')+'" data-color="'+esc(e.color||'#3B82F6')+'" data-time-start="'+esc(e.time_start||'')+'" data-time-end="'+esc(e.time_end||'')+'" data-is-goal="'+esc(String(!!e.is_goal))+'" data-is-dday="'+esc(String(!!e.is_dday))+'" data-is-widget="'+esc(String(!!e.is_widget))+'">수정</button>';
        html += '<button type="button" class="cal-btn cal-btn-mini js-copy" data-id="'+esc(String(e.id))+'">복사</button>';
        html += '<button type="button" class="cal-btn cal-btn-mini cal-btn-danger js-delete" data-id="'+esc(String(e.id))+'">삭제</button>';
        html += '<button type="button" class="cal-btn cal-btn-mini js-view" data-wr-id="'+esc(String(e.id))+'" data-subject="'+esc(e.subject||'')+'" data-content="'+esc(e.content||'')+'" data-date="'+esc(e.date||'')+'" data-end-date="'+esc(e.end_date||'')+'" data-color="'+esc(e.color||'#3B82F6')+'" data-time-start="'+esc(e.time_start||'')+'" data-time-end="'+esc(e.time_end||'')+'" data-is-goal="'+esc(String(!!e.is_goal))+'" data-is-dday="'+esc(String(!!e.is_dday))+'" data-is-widget="'+esc(String(!!e.is_widget))+'">상세</button>';
        html += '</div></div></div>';
      }
    }
    html += '<div class="cal-detail-add"><button type="button" class="cal-btn cal-btn-add" id="btn-add-for-day">+ '+config.year+'.'+config.month+'.'+day+' 일정 추가</button></div>';
    list.innerHTML = html;
    panel.style.display = 'block';
  }

  /* ── 상세 보기 모달 ── */
  function openViewModal(ev){
    var m = q('cal-view-modal'); if(!m) return;
    var colorBar = m.querySelector('.cal-view-color-bar'); if (colorBar) colorBar.style.background = ev.color || '#3B82F6';
    var titleEl = m.querySelector('#cal-view-title'); if (titleEl) titleEl.textContent = ev.subject || '(제목없음)';
    var dateEl = m.querySelector('#cal-view-date-info');
    if (dateEl) {
      var dateText = ev.date || '';
      if (ev.end_date && ev.end_date !== ev.date) dateText += ' ~ ' + ev.end_date;
      var timeText = '';
      if (ev.time_start) { timeText = ev.time_start; if (ev.time_end) timeText += ' ~ ' + ev.time_end; }
      dateEl.textContent = dateText + (timeText ? '  ' + timeText : '');
    }

    // Goal/D-day badge in view modal
    var goalBadgeEl = q('cal-view-goal-badge');
    if (goalBadgeEl) {
      if (ev.is_goal) {
        var ddayText = calcDday(ev.date);
        if (ddayText) {
          goalBadgeEl.textContent = '⚑ ' + ddayText;
          goalBadgeEl.className = 'cal-view-goal-badge' + (ddayText === 'D-Day!' ? ' dday-today' : '');
          goalBadgeEl.style.display = '';
        } else {
          goalBadgeEl.style.display = 'none';
        }
      } else if (ev.is_dday) {
        var ddayAnnText = calcDdayAnniversary(ev.date);
        if (ddayAnnText) {
          goalBadgeEl.textContent = '◈ ' + ddayAnnText;
          goalBadgeEl.className = 'cal-view-dday-badge' + (ddayAnnText === 'D-Day!' ? ' dday-today' : '');
          goalBadgeEl.style.display = '';
        } else {
          goalBadgeEl.style.display = 'none';
        }
      } else {
        goalBadgeEl.style.display = 'none';
      }
    }

    var contentEl = m.querySelector('#cal-view-content');
    if (contentEl) contentEl.innerHTML = ev.content || '<span style="color:var(--cal-text-muted);">내용 없음</span>';
    var editBtn = m.querySelector('#btn-view-edit');
    if (editBtn) {
      editBtn.setAttribute('data-wr-id',ev.id||'');
      editBtn.setAttribute('data-subject',ev.subject||'');
      editBtn.setAttribute('data-content',ev.content||'');
      editBtn.setAttribute('data-date',ev.date||'');
      editBtn.setAttribute('data-end-date',ev.end_date||'');
      editBtn.setAttribute('data-color',ev.color||'#3B82F6');
      editBtn.setAttribute('data-time-start',ev.time_start||'');
      editBtn.setAttribute('data-time-end',ev.time_end||'');
      editBtn.setAttribute('data-is-goal', ev.is_goal ? 'true' : 'false');
      editBtn.setAttribute('data-is-dday', ev.is_dday ? 'true' : 'false');
      editBtn.setAttribute('data-is-widget', ev.is_widget ? 'true' : 'false');
    }
    var delBtn = m.querySelector('#btn-view-delete'); if (delBtn) delBtn.setAttribute('data-id', ev.id || '');
    m._viewData = ev;
    m.style.display = 'block'; document.body.classList.add('modal-open');
  }
  function closeViewModal(){ var m = q('cal-view-modal'); if(m) m.style.display = 'none'; document.body.classList.remove('modal-open'); }
  function getViewModalData(){ var m = q('cal-view-modal'); return m && m._viewData ? m._viewData : {}; }

  /* ── 일정 추가/수정 모달 ── */
  function openWriteModal(mode, ev){
    var m = q('cal-modal'); if(!m) return;
    q('modal_w').value = mode || '';
    q('modal_wr_id').value = (ev && ev.id) ? ev.id : 0;
    q('modal_subject').value = (ev && ev.subject) ? ev.subject : '';
    q('modal_content').value = (ev && ev.content) ? ev.content : '';
    q('modal_wr_1').value = (ev && ev.date) ? ev.date : '';
    q('modal_wr_2').value = (ev && ev.end_date) ? ev.end_date : ((ev && ev.date) ? ev.date : '');
    q('modal_wr_6').value = (ev && ev.time_start) ? ev.time_start : '';
    q('modal_wr_7').value = (ev && ev.time_end) ? ev.time_end : '';
    q('modal_wr_3').value = (ev && ev.color) ? ev.color : '#3B82F6';

    // D-day 타입 라디오 버튼
    var radioNone = q('modal_dday_type_none');
    var radioGoal = q('modal_dday_type_goal');
    var radioDday = q('modal_dday_type_dday');
    if (radioNone && radioGoal && radioDday) {
      if (ev && ev.is_goal) {
        radioGoal.checked = true;
      } else if (ev && ev.is_dday) {
        radioDday.checked = true;
      } else {
        radioNone.checked = true;
      }
    }

    // 위젯 체크박스
    var widgetChk = q('modal_cal_widget');
    if (widgetChk) widgetChk.checked = (ev && ev.is_widget) ? true : false;

    // 위젯 행 표시 여부
    updateWidgetRowVisibility();

    var repeatChk = q('modal_cal_repeat'); if (repeatChk) repeatChk.checked = false;
    var titleEl = m.querySelector('.cal-modal-header h3'); if (titleEl) titleEl.textContent = mode === 'u' ? '일정 수정' : '일정 추가';
    var saveBtn = q('btn-save-event'); if (saveBtn) { saveBtn.textContent = mode === 'u' ? '수정' : '저장'; saveBtn.disabled = false; }
    renderRecentColorPalette();
    m.style.display = 'block'; document.body.classList.add('modal-open');
  }
  function closeWriteModal(){ var m = q('cal-modal'); if(m) m.style.display = 'none'; document.body.classList.remove('modal-open'); }

  function updateWidgetRowVisibility(){
    var radioNone = q('modal_dday_type_none');
    var widgetRow = q('modal_widget_row');
    if (!widgetRow) return;
    var isNone = radioNone ? radioNone.checked : true;
    widgetRow.style.display = isNone ? 'none' : '';
    if (isNone) {
      var widgetChk = q('modal_cal_widget');
      if (widgetChk) widgetChk.checked = false;
    }
  }

  function bindDdayTypeRadios(){
    var radios = document.querySelectorAll('input[name="cal_dday_type"]');
    for (var i = 0; i < radios.length; i++) {
      radios[i].addEventListener('change', updateWidgetRowVisibility);
    }
  }

  function openCopyModal(id){
    var m = q('cal-copy-modal'); if(!m) return;
    q('copy_src_wr_id').value = id;
    if (currentSelectedDay) q('copy_target_date').value = getSelectedDateStr(currentSelectedDay);
    else q('copy_target_date').value = '';
    var execBtn = q('btn-exec-copy'); if (execBtn) { execBtn.disabled = false; execBtn.textContent = '복사 실행'; }
    m.style.display = 'block'; document.body.classList.add('modal-open');
  }
  function closeCopyModal(){ var m = q('cal-copy-modal'); if(m) m.style.display = 'none'; document.body.classList.remove('modal-open'); }

  /* ── 저장/복사/삭제 ── */
  function handleEventSave(){
    var subj = q('modal_subject');
    if (!subj || !subj.value.trim()) { alert('제목을 입력하세요.'); if (subj) subj.focus(); return; }
    var d1 = q('modal_wr_1');
    if (!d1 || !d1.value) { alert('시작 날짜를 선택하세요.'); if (d1) d1.focus(); return; }
    var colorVal = q('modal_wr_3') ? q('modal_wr_3').value : '#3B82F6';
    saveRecentColor(colorVal);
    var saveBtn = q('btn-save-event'); var origText = saveBtn ? saveBtn.textContent : '저장';
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = '저장 중...'; }

    // 라디오 버튼 값을 cal_goal/cal_dday 파라미터로 변환
    var radioGoal = q('modal_dday_type_goal');
    var radioDday = q('modal_dday_type_dday');
    var calGoalHidden = q('_hidden_cal_goal');
    var calDdayHidden = q('_hidden_cal_dday');
    var form = q('cal-modal-form');
    if (form && !calGoalHidden) {
      calGoalHidden = document.createElement('input');
      calGoalHidden.type = 'hidden'; calGoalHidden.id = '_hidden_cal_goal'; calGoalHidden.name = 'cal_goal';
      form.appendChild(calGoalHidden);
    }
    if (form && !calDdayHidden) {
      calDdayHidden = document.createElement('input');
      calDdayHidden.type = 'hidden'; calDdayHidden.id = '_hidden_cal_dday'; calDdayHidden.name = 'cal_dday';
      form.appendChild(calDdayHidden);
    }
    if (calGoalHidden) calGoalHidden.value = (radioGoal && radioGoal.checked) ? '1' : '';
    if (calDdayHidden) calDdayHidden.value = (radioDday && radioDday.checked) ? '1' : '';

    var params = serializeForm(q('cal-modal-form'));
    ajaxPost(config.save_action_url, params, function(ok, r){
      if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = origText; }
      if (ok && r.success) { closeWriteModal(); refreshCalendarAjax(); }
      else alert('저장 실패: ' + (r && r.error ? r.error : '알 수 없는 오류'));
    });
  }
  function handleCopyExec(){
    var td = q('copy_target_date');
    if (!td || !td.value) { alert('복사할 날짜를 선택하세요.'); if (td) td.focus(); return; }
    var srcId = q('copy_src_wr_id');
    if (!srcId || !srcId.value || srcId.value === '0') { alert('복사할 원본 일정이 없습니다.'); return; }
    var execBtn = q('btn-exec-copy'); if (execBtn) { execBtn.disabled = true; execBtn.textContent = '복사 중...'; }
    var params = serializeForm(q('cal-copy-form'));
    ajaxPost(config.copy_action_url, params, function(ok, r){
      if (execBtn) { execBtn.disabled = false; execBtn.textContent = '복사 실행'; }
      if (ok && r.success) { closeCopyModal(); refreshCalendarAjax(); }
      else alert('복사 실패: ' + (r && r.error ? r.error : '알 수 없는 오류'));
    });
  }
  function handleDelete(wrId){
    var params = 'bo_table=' + encodeURIComponent(config.bo_table || '') + '&wr_id=' + encodeURIComponent(wrId);
    ajaxPost(config.delete_action_url, params, function(ok, r){
      if (ok && r.success) refreshCalendarAjax();
      else alert('삭제 실패: ' + (r && r.error ? r.error : '알 수 없는 오류'));
    });
  }

  function ajaxPost(url, params, callback){
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function(){
      if (xhr.readyState === 4) {
        if (xhr.status === 200) { try { callback(true, JSON.parse(xhr.responseText)); } catch(ex){ callback(false, {error:'서버 응답 파싱 오류'}); } }
        else callback(false, {error:'HTTP ' + xhr.status});
      }
    };
    xhr.send(params);
  }

  function serializeForm(container){
    var parts = [];
    var els = container.querySelectorAll('input, textarea, select');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (!el.name || el.disabled) continue;
      if (el.type === 'checkbox' && !el.checked) continue;
      if (el.type === 'radio' && !el.checked) continue;
      parts.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(el.value));
    }
    return parts.join('&');
  }

  /* ── AJAX 리프레시 ── */
  function refreshCalendarAjax(){
    var url = './board.php?bo_table=' + encodeURIComponent(config.bo_table || '');
    if (config.year) url += '&cal_year=' + config.year;
    if (config.month) url += '&cal_month=' + config.month;
    url += '&ajax=1&_t=' + Date.now();
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function(){
      if (xhr.readyState === 4 && xhr.status === 200) {
        var app = q('calendar-app');
        if (!app) return;
        app.innerHTML = xhr.responseText;
        applyStoredTheme();
        applyStoredHeaderImage();
        bindDayClicks();
        bindMonthNav();
        bindGoogleAuthTopNavigation();
        bindGoogleRefresh();
        bindImageModal();
        if (currentSelectedDay) {
          var dayEl = app.querySelector('.cal-day[data-day="' + currentSelectedDay + '"]');
          if (dayEl) {
            var raw = dayEl.getAttribute('data-events') || '[]';
            var events = [];
            try { var tx = document.createElement('textarea'); tx.innerHTML = raw; events = JSON.parse(tx.value); } catch(ex){ events = []; }
            renderDetail(currentSelectedDay, events);
          }
        }
      }
    };
    xhr.send();
  }

  function bindMonthNav(){
    var navs = document.querySelectorAll('.js-cal-nav');
    for (var i=0;i<navs.length;i++){
      navs[i].onclick = function(e){
        e.preventDefault();
        var u = this.getAttribute('href');
        var yMatch = u.match(/cal_year=(\d+)/); var mMatch = u.match(/cal_month=(\d+)/);
        if (yMatch) config.year = parseInt(yMatch[1], 10);
        if (mMatch) config.month = parseInt(mMatch[1], 10);
        currentSelectedDay = null;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', u + (u.indexOf('?')>-1?'&':'?') + 'ajax=1', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function(){
          if (xhr.readyState===4 && xhr.status===200){
            var app = q('calendar-app');
            if (app) { app.innerHTML = xhr.responseText; applyStoredTheme(); applyStoredHeaderImage(); bindDayClicks(); bindMonthNav(); bindGoogleAuthTopNavigation(); bindGoogleRefresh(); bindImageModal(); }
            if (history.pushState) history.pushState({}, '', u);
          }
        };
        xhr.send();
      };
    }
  }

  function bindGoogleAuthTopNavigation(){
    var b = q('btn-google-auth'); if (!b) return;
    b.onclick = function(e){
      e.preventDefault(); var h = b.getAttribute('href');
      try { if (window.top !== window.self) window.top.location.href = h; else window.location.href = h; }
      catch(ex){ window.location.href = h; }
    };
  }
  function bindGoogleRefresh(){
    var b = q('btn-google-refresh'); if (!b) return;
    b.onclick = function(e){
      e.preventDefault(); b.textContent = '동기화 중...'; b.style.pointerEvents = 'none';
      var x = new XMLHttpRequest();
      x.open('GET', config.google_refresh_url + '&ajax=1', true);
      x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      x.onreadystatechange = function(){ if (x.readyState===4) { b.textContent = '동기화'; b.style.pointerEvents = ''; refreshCalendarAjax(); } };
      x.send();
    };
  }

  function esc(s){
    if (s===null||s===undefined) return '';
    var d=document.createElement('div'); d.appendChild(document.createTextNode(String(s))); return d.innerHTML;
  }

  return { init: init };
})();