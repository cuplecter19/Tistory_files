var CalendarBoard = (function() {
  'use strict';
  var config = {};
  var panel, panelDate, panelList, closeBtn;

  function init(opts) {
    config = opts || {};
    panel = document.getElementById('cal-detail-panel');
    panelDate = document.getElementById('cal-detail-date');
    panelList = document.getElementById('cal-detail-list');
    closeBtn = document.getElementById('cal-detail-close');

    bindDayClicks();
    bindClosePanel();
    bindGoogleRefresh();
    bindModal();
  }

  function bindDayClicks() {
    var days = document.querySelectorAll('.cal-day:not(.cal-day-empty)');
    for (var i=0;i<days.length;i++) {
      (function(dayEl){
        dayEl.addEventListener('click', function(e){
          var day = dayEl.getAttribute('data-day');
          var raw = dayEl.getAttribute('data-events');
          var events = [];
          if(raw){ try { events = JSON.parse(decodeHtml(raw)); } catch(ex){} }
          showDetailPanel(day, events);
        });
      })(days[i]);
    }
  }

  function showDetailPanel(day, events) {
    if(!panel) return;
    panelDate.textContent = config.year+'년 '+config.month+'월 '+day+'일 일정';
    var html = '';
    if(!events || events.length===0) {
      html = '<div class="cal-detail-empty">등록된 일정이 없습니다.</div>';
    } else {
      for(var i=0;i<events.length;i++){
        var ev = events[i], color=esc(ev.color||'#3B82F6');
        html += '<div class="cal-detail-item">';
        html += '<div class="cal-detail-dot" style="background:'+color+'"></div><div class="cal-detail-info">';
        html += '<div class="cal-detail-subject">'+esc(ev.subject||'')+'</div>';
        if(ev.time_start || ev.time_end) html += '<div class="cal-detail-desc">'+esc((ev.time_start||'')+' ~ '+(ev.time_end||''))+'</div>';
        if(ev.content) html += '<div class="cal-detail-desc">'+esc(ev.content)+'</div>';
        if(ev.type==='local'){
          html += '<button type="button" class="cal-btn cal-btn-edit cal-btn-mini" data-edit-id="'+esc(ev.id)+'">수정</button>';
        }
        html += '</div></div>';
      }
    }
    panelList.innerHTML = html;
    panel.style.display='block';
    bindDetailEditButtons(events);
  }

  function bindDetailEditButtons(events){
    var btns = panelList.querySelectorAll('[data-edit-id]');
    for(var i=0;i<btns.length;i++){
      btns[i].addEventListener('click', function(){
        var id = this.getAttribute('data-edit-id');
        var target = null;
        for(var j=0;j<events.length;j++){ if(String(events[j].id)===String(id)) { target=events[j]; break; } }
        if(target) openModal('u', target);
      });
    }
  }

  function bindClosePanel() {
    if(closeBtn) closeBtn.addEventListener('click', function(){ panel.style.display='none'; });
  }

  function bindGoogleRefresh() {
    var btn = document.getElementById('btn-google-refresh');
    if(!btn) return;
    btn.addEventListener('click', function(e){
      e.preventDefault();
      var xhr = new XMLHttpRequest();
      xhr.open('GET', config.google_refresh_url + '&ajax=1', true);
      xhr.onreadystatechange = function(){
        if(xhr.readyState===4){
          try{
            var r = JSON.parse(xhr.responseText||'{}');
            if(r.need_auth && r.auth_url){ window.location.href = r.auth_url; return; }
          }catch(ex){}
          window.location.reload();
        }
      };
      xhr.send();
    });
  }

  function bindModal() {
    var openBtn = document.getElementById('btn-open-write-modal');
    var modal = document.getElementById('cal-modal');
    var close = document.getElementById('cal-modal-close');
    var back = document.getElementById('cal-modal-backdrop');
    var form = document.getElementById('cal-modal-form');

    if(openBtn){
      openBtn.addEventListener('click', function(e){
        e.preventDefault();
        openModal('', null);
      });
    }
    if(close) close.addEventListener('click', closeModal);
    if(back) back.addEventListener('click', closeModal);

    if(form){
      form.addEventListener('submit', function(e){
        e.preventDefault();
        var fd = new FormData(form);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', config.write_action_url, true);
        xhr.onreadystatechange = function(){
          if(xhr.readyState===4){
            window.location.reload();
          }
        };
        xhr.send(fd);
      });
    }

    function closeModal(){ if(modal) modal.style.display='none'; }
  }

  function openModal(mode, ev){
    var modal = document.getElementById('cal-modal');
    if(!modal) return;
    document.getElementById('modal_w').value = mode || '';
    document.getElementById('modal_wr_id').value = ev ? (ev.id||0) : 0;
    document.getElementById('modal_subject').value = ev ? (ev.subject||'') : '';
    document.getElementById('modal_content').value = ev ? (ev.content||'') : '';
    document.getElementById('modal_wr_1').value = ev ? (ev.date||'') : '';
    document.getElementById('modal_wr_2').value = ev ? (ev.end_date||ev.date||'') : '';
    document.getElementById('modal_wr_6').value = ev ? (ev.time_start||'') : '';
    document.getElementById('modal_wr_7').value = ev ? (ev.time_end||'') : '';
    document.getElementById('modal_wr_3').value = ev ? (ev.color||'#3B82F6') : '#3B82F6';
    modal.style.display='block';
  }

  function decodeHtml(s){ var t=document.createElement('textarea'); t.innerHTML=s; return t.value; }
  function esc(s){ if(s===null||s===undefined) return ''; var d=document.createElement('div'); d.appendChild(document.createTextNode(String(s))); return d.innerHTML; }

  return { init:init };
})();