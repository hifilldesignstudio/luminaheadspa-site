<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin · Lumina Head Spa &amp; Beauty</title>
<link rel="icon" href="../favicon-32.png">
<style>
  :root{--ink:#2b2620;--muted:#8a7d6b;--line:#e5ddcd;--brass:#b08d4f;--brass-d:#926f35;--card:#fffdf9;--green:#2e7d4f;--red:#b3402e;--bg:#f7f2e9}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--ink)}
  header{background:#171410;color:#f3ead8;padding:16px 20px;display:flex;justify-content:space-between;align-items:center}
  header b{font-size:17px;letter-spacing:.02em}
  header span{font-size:12.5px;color:#cbbfa4}
  header button{background:none;border:1px solid #57503f;color:#f3ead8;border-radius:8px;padding:6px 12px;cursor:pointer}
  .wrap{max-width:980px;margin:0 auto;padding:20px 16px 60px}
  .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
  .tab{border:1.5px solid var(--line);background:#fff;border-radius:99px;padding:8px 18px;cursor:pointer;font-size:14.5px}
  .tab.on{background:#171410;color:#fdf6e3;border-color:#171410}
  .tab .n{background:var(--red);color:#fff;border-radius:99px;font-size:11px;padding:1px 7px;margin-left:6px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 18px;margin-bottom:12px}
  .booking{display:flex;gap:16px;align-items:flex-start}
  .booking .time{font-weight:700;font-size:16px;min-width:92px}
  .booking .time small{display:block;font-weight:400;color:var(--muted);font-size:12px;margin-top:2px}
  .booking .info{flex:1;font-size:14.5px;line-height:1.5}
  .booking .info .note{color:var(--brass-d)}
  .badge{font-size:11px;border-radius:99px;padding:2px 9px;letter-spacing:.04em;vertical-align:2px}
  .badge.pending{background:#fdf0d5;color:#8a6d1f}.badge.confirmed{background:#e2f2e6;color:var(--green)}
  .badge.cancelled{background:#f6e3e0;color:var(--red)}.badge.done{background:#e8e4da;color:var(--muted)}
  .badge.phone{background:#e6ecf7;color:#33518a}
  .act{border:1.5px solid var(--line);background:#fff;border-radius:8px;padding:7px 14px;cursor:pointer;font-size:13.5px;margin-left:6px}
  .act.confirm{border-color:var(--green);color:var(--green)} .act.cancel{border-color:var(--red);color:var(--red)}
  h3.sec{font-size:15px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin:22px 0 10px}
  input,select,textarea{width:100%;padding:10px 12px;border:1px solid #d8cdb8;border-radius:9px;font:inherit;font-size:15px;background:#fff}
  label{font-size:12.5px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:5px;font-weight:600}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media(max-width:640px){.grid{grid-template-columns:1fr}.booking{flex-wrap:wrap}}
  .btn{background:var(--brass);color:#fff;border:none;border-radius:10px;padding:11px 22px;font-size:15px;cursor:pointer;font-weight:600}
  .btn.dark{background:#171410}
  table{width:100%;border-collapse:collapse;font-size:14.5px}
  th,td{padding:8px 6px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}
  td input{padding:7px 9px;font-size:14px}
  td input.mini{width:74px}
  .cal{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
  .cal .dow{font-size:11px;color:var(--muted);text-align:center;letter-spacing:.08em}
  .cal .day{border:1px solid var(--line);background:#fff;border-radius:9px;padding:7px 4px;text-align:center;cursor:pointer;font-size:13.5px}
  .cal .day small{display:block;color:var(--muted);font-size:11px}
  .cal .day.sel{border-color:var(--brass-d);background:#fdf6ea}
  .cal .day.off{opacity:.35;cursor:default}
  .cal .day .pend{color:var(--red);font-weight:700}
  .msg{min-height:20px;color:var(--green);font-size:14px;margin-top:10px}
  .err{color:var(--red)}
  #login{max-width:380px;margin:70px auto;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:28px}
  .hidden{display:none}
  .row{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
  .row>*{flex:1;min-width:130px}
</style>
</head>
<body>
<div id="login" class="hidden">
  <h2 style="margin-bottom:6px">Lumina Admin</h2>
  <p style="color:var(--muted);font-size:14px;margin-bottom:16px">Bookings for Lumina Head Spa &amp; Beauty</p>
  <label>Password</label>
  <input type="password" id="pw" autocomplete="current-password">
  <p class="err" id="pwErr" style="min-height:18px;font-size:13.5px;margin-top:6px"></p>
  <button class="btn" id="loginBtn" style="width:100%;margin-top:6px">Log in</button>
</div>

<div id="app" class="hidden">
  <header><div><b>Lumina Head Spa &amp; Beauty</b><br><span>Booking admin</span></div><button id="logoutBtn">Log out</button></header>
  <div class="wrap">
    <div class="tabs">
      <button class="tab on" data-tab="bookings">Bookings<span class="n hidden" id="pendCount"></span></button>
      <button class="tab" data-tab="messages">Messages<span class="n hidden" id="msgCount"></span></button>
      <button class="tab" data-tab="phone">Phone Booking</button>
      <button class="tab" data-tab="treatments">Treatments</button>
      <button class="tab" data-tab="techs">Technicians</button>
      <button class="tab" data-tab="settings">Hours &amp; Settings</button>
    </div>

    <section id="tab-bookings">
      <div class="row" style="margin-bottom:6px">
        <div><label>Search</label><input id="q" placeholder="Name, phone or reference…"></div>
        <div><label>Date</label><input type="date" id="dayPick"></div>
        <div style="flex:0"><button class="btn dark" id="todayBtn">Today</button></div>
      </div>
      <h3 class="sec">Waiting for confirmation</h3>
      <div id="pendingList"></div>
      <h3 class="sec">Bookings for the day</h3>
      <div id="dayList"></div>
      <h3 class="sec">Calendar</h3>
      <div class="card">
        <div class="row" style="margin-bottom:12px"><button class="act" id="calPrev">‹ Prev</button><b id="calTitle" style="text-align:center"></b><button class="act" id="calNext">Next ›</button></div>
        <div class="cal" id="calGrid"></div>
        <p style="color:var(--muted);font-size:12.5px;margin-top:10px">Numbers show bookings that day · <span style="color:var(--red);font-weight:700">red</span> = waiting for confirmation. Tap a day to see its bookings.</p>
      </div>
    </section>

    <section id="tab-messages" class="hidden">
      <div id="msgList"></div>
    </section>

    <section id="tab-phone" class="hidden">
      <div class="card">
        <div class="grid">
          <div><label>Guest name</label><input id="mName"></div>
          <div><label>Phone</label><input id="mPhone"></div>
          <div><label>Email (optional)</label><input id="mEmail" type="email"></div>
          <div><label>Note (optional)</label><input id="mNote"></div>
          <div><label>Treatment 1</label><select id="mT1"></select></div>
          <div><label>Treatment 2 (optional)</label><select id="mT2"></select></div>
          <div><label>Date</label><input type="date" id="mDate"></div>
          <div><label>Start time</label><select id="mTime"></select></div>
        </div>
        <p class="err" id="mErr" style="min-height:18px;font-size:13.5px;margin-top:8px"></p>
        <button class="btn" id="mAdd">Add Booking (confirmed)</button>
        <p class="msg" id="mMsg"></p>
      </div>
    </section>

    <section id="tab-treatments" class="hidden">
      <div class="card" style="overflow-x:auto">
        <table><thead><tr><th>Name</th><th>Group</th><th>Min</th><th>Price $</th><th>Spots</th><th>On</th><th></th></tr></thead><tbody id="treatBody"></tbody></table>
        <button class="btn" id="addTreat" style="margin-top:14px">+ Add treatment</button>
        <p class="msg" id="treatMsg"></p>
        <p style="color:var(--muted);font-size:13px;margin-top:6px">Changes here update the booking system right away. "Spots" = how many technicians the treatment needs at once (2 for a couples treatment). Turning a treatment Off hides it from online booking.</p>
      </div>
    </section>

    <section id="tab-techs" class="hidden">
      <div class="card">
        <div id="techList"></div>
        <div class="row" style="margin-top:12px"><div><label>New technician name</label><input id="newTech"></div><div style="flex:0"><button class="btn" id="addTech">Add</button></div></div>
        <p class="msg" id="techMsg"></p>
        <p style="color:var(--muted);font-size:13px">Guests never pick a technician — bookings are shared across the team. How many guests you can serve at the same time is set by "Simultaneous guests" in Hours &amp; Settings.</p>
      </div>
    </section>

    <section id="tab-settings" class="hidden">
      <div class="card">
        <div class="grid">
          <div><label>Opens</label><select id="sOpen"></select></div>
          <div><label>Closes</label><select id="sClose"></select></div>
          <div><label>Simultaneous guests (capacity)</label><input id="sCap" type="number" min="1" max="10"></div>
          <div><label>Book ahead (days)</label><input id="sWin" type="number" min="1" max="90"></div>
          <div><label>Online cancel cutoff (hours before)</label><input id="sCut" type="number" min="0" max="48"></div>
        </div>
        <button class="btn" id="saveSettings" style="margin-top:14px">Save settings</button>
        <p class="msg" id="setMsg"></p>
      </div>
      <div class="card">
        <h3 style="margin-bottom:10px">Blocked dates (shop closed)</h3>
        <div id="blockedList" style="margin-bottom:12px"></div>
        <div class="row"><div><label>Date</label><input type="date" id="bDate"></div><div><label>Note</label><input id="bNote" placeholder="e.g. Holiday"></div><div style="flex:0"><button class="btn dark" id="bAdd">Block date</button></div></div>
      </div>
    </section>
  </div>
</div>

<script>
var API = '../api/admin.php', PUB = '../api/public.php';
function $(id){ return document.getElementById(id); }
function api(action, opts){
  opts = opts || {};
  var url = API + '?action=' + action + (opts.qs || '');
  return fetch(url, {method: opts.method || 'GET', headers:{'Content-Type':'application/json'}, body: opts.body? JSON.stringify(opts.body): undefined})
    .then(function(r){ return r.json().then(function(j){ if(!r.ok && !j.error) j.error='Error '+r.status; j._status=r.status; return j; }); });
}
function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;'); }
function todayStr(){ var d=new Date(); return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }

/* ---------- login ---------- */
function showApp(){ $('login').classList.add('hidden'); $('app').classList.remove('hidden'); loadAll(); }
$('loginBtn').addEventListener('click', function(){
  api('login', {method:'POST', body:{password: $('pw').value}}).then(function(j){
    if(j.ok) showApp(); else $('pwErr').textContent = 'Wrong password — try again.';
  });
});
$('pw').addEventListener('keydown', function(e){ if(e.key==='Enter') $('loginBtn').click(); });
$('logoutBtn').addEventListener('click', function(){ api('logout',{method:'POST'}).then(function(){ location.reload(); }); });
api('me').then(function(j){ if(j.loggedIn) showApp(); else $('login').classList.remove('hidden'); });

/* ---------- tabs ---------- */
document.querySelectorAll('.tab').forEach(function(t){
  t.addEventListener('click', function(){
    document.querySelectorAll('.tab').forEach(function(x){x.classList.remove('on')});
    t.classList.add('on');
    ['bookings','messages','phone','treatments','techs','settings'].forEach(function(k){ $('tab-'+k).classList.toggle('hidden', k!==t.dataset.tab); });
  });
});

/* ---------- bookings ---------- */
var viewDay = todayStr(), calY, calM;
(function(){ var d=new Date(); calY=d.getFullYear(); calM=d.getMonth(); })();
function badge(st){ return '<span class="badge '+st+'">'+st+'</span>'; }
function cardHTML(b){
  var items = b.items.map(function(i){ return esc(i.name_snapshot)+' <small style="color:var(--muted)">'+i.startLabel+'</small>'; }).join('<br>');
  var h = '<div class="card booking"><div class="time">'+esc(b.startLabel)+'<small>to '+esc(b.endLabel)+'</small><small>'+esc(b.dayLabel)+'</small></div>'
    + '<div class="info"><b>'+esc(b.name)+'</b> '+badge(b.status)+(b.source==='phone'?' <span class="badge phone">Phone</span>':'')
    + ' <small style="color:var(--muted)">Ref '+esc(b.ref)+'</small><div>'+items+'</div>'
    + '<div>Phone: '+esc(b.phone)+(b.email?' · '+esc(b.email):'')+'</div>'
    + (b.note? '<div class="note">Note: '+esc(b.note)+'</div>':'')
    + '<div style="color:var(--muted)">Total $'+b.total_price+' · '+b.total_min+' min</div></div>';
  if(b.status==='pending') h += '<div><button class="act confirm" data-id="'+b.id+'" data-st="confirmed">Confirm</button><button class="act cancel" data-id="'+b.id+'" data-st="cancelled">Cancel</button></div>';
  else if(b.status==='confirmed') h += '<div><button class="act" data-id="'+b.id+'" data-st="done">Done</button><button class="act cancel" data-id="'+b.id+'" data-st="cancelled">Cancel</button></div>';
  return h + '</div>';
}
document.body.addEventListener('click', function(e){
  var btn = e.target.closest ? e.target.closest('button[data-st]') : null;
  if(!btn) return;
  api('booking_status', {method:'POST', body:{id: parseInt(btn.dataset.id,10), status: btn.dataset.st}}).then(loadBookings);
});
function loadBookings(){
  var q = $('q').value.trim();
  api('bookings', {qs: '&status=pending'}).then(function(j){
    var pend = (j.bookings||[]);
    $('pendCount').textContent = pend.length; $('pendCount').classList.toggle('hidden', !pend.length);
    $('pendingList').innerHTML = pend.length? pend.map(cardHTML).join('') : '<p style="color:var(--muted)">All caught up — nothing waiting. ✓</p>';
  });
  var qs = q? '&q='+encodeURIComponent(q) : '&date='+viewDay;
  api('bookings', {qs: qs}).then(function(j){
    $('dayList').innerHTML = (j.bookings||[]).length? j.bookings.map(cardHTML).join('') : '<p style="color:var(--muted)">No bookings found.</p>';
  });
  loadCal();
}
$('q').addEventListener('input', loadBookings);
$('dayPick').addEventListener('change', function(){ viewDay = this.value || todayStr(); loadBookings(); });
$('todayBtn').addEventListener('click', function(){ viewDay = todayStr(); $('dayPick').value = viewDay; $('q').value=''; loadBookings(); });
function loadCal(){
  var ym = calY+'-'+String(calM+1).padStart(2,'0');
  $('calTitle').textContent = new Date(calY, calM, 1).toLocaleDateString('en-CA',{month:'long', year:'numeric'});
  api('month_counts', {qs:'&ym='+ym}).then(function(j){
    var counts = j.counts||{};
    var first = new Date(calY, calM, 1), lead = (first.getDay()+6)%7, dim = new Date(calY, calM+1, 0).getDate();
    var h = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].map(function(d){return '<div class="dow">'+d+'</div>'}).join('');
    for(var i=0;i<lead;i++) h += '<div></div>';
    for(var dnum=1; dnum<=dim; dnum++){
      var ds = ym+'-'+String(dnum).padStart(2,'0'), c = counts[ds];
      h += '<div class="day'+(ds===viewDay?' sel':'')+(ds<todayStr()?' off':'')+'" data-day="'+ds+'">'+dnum
         + '<small>'+(c? c.total+' booking'+(c.total>1?'s':'') : '—')+'</small>'
         + (c && c.pending? '<small class="pend">'+c.pending+' waiting</small>':'') + '</div>';
    }
    $('calGrid').innerHTML = h;
    $('calGrid').querySelectorAll('.day[data-day]').forEach(function(el){
      el.addEventListener('click', function(){ viewDay = el.dataset.day; $('dayPick').value = viewDay; $('q').value=''; loadBookings(); });
    });
  });
}
$('calPrev').addEventListener('click', function(){ calM--; if(calM<0){calM=11;calY--;} loadCal(); });
$('calNext').addEventListener('click', function(){ calM++; if(calM>11){calM=0;calY++;} loadCal(); });

/* ---------- phone booking ---------- */
var TREATS = [];
function fillTreatSelects(){
  var opts = TREATS.map(function(t){ return '<option value="'+t.id+'">'+esc(t.name)+' · '+t.minutes+' min · $'+t.price+'</option>'; }).join('');
  $('mT1').innerHTML = opts;
  $('mT2').innerHTML = '<option value="">— none —</option>' + opts;
}
function fillTimes(){
  var ids = [$('mT1').value, $('mT2').value].filter(Boolean).map(Number);
  var mins = ids.reduce(function(a,id){ var t=TREATS.find(function(x){return x.id===id}); return a+(t? t.minutes:0); }, 0) || 60;
  var units = ids.reduce(function(a,id){ var t=TREATS.find(function(x){return x.id===id}); return Math.max(a, t? t.units:1); }, 1);
  var date = $('mDate').value || todayStr();
  fetch(PUB+'?action=availability&date='+date+'&minutes='+mins+'&units='+units).then(function(r){return r.json()}).then(function(j){
    var starts = (j.starts||[]);
    $('mTime').innerHTML = starts.length? starts.map(function(s){ var h=Math.floor(s/60), m=s%60, ap=h>=12?'PM':'AM', h12=h%12||12; return '<option value="'+s+'">'+h12+':'+String(m).padStart(2,'0')+' '+ap+'</option>'; }).join('') : '<option value="">No times available</option>';
  });
}
['mT1','mT2','mDate'].forEach(function(id){ document.addEventListener('change', function(e){ if(e.target.id===id) fillTimes(); }); });
$('mAdd').addEventListener('click', function(){
  var items = [$('mT1').value, $('mT2').value].filter(Boolean).map(Number);
  var body = {name: $('mName').value, phone: $('mPhone').value, email: $('mEmail').value, note: $('mNote').value,
              date: $('mDate').value, start: parseInt($('mTime').value,10), items: items};
  $('mErr').textContent = '';
  api('phone_booking', {method:'POST', body: body}).then(function(j){
    if(j.ok){ $('mMsg').textContent = '✓ Added — reference '+j.ref; loadBookings(); }
    else if(j.full){ if(confirm(j.error)){ body.force = true; api('phone_booking', {method:'POST', body: body}).then(function(j2){ if(j2.ok){ $('mMsg').textContent='✓ Added (overbooked) — reference '+j2.ref; loadBookings(); } }); } }
    else $('mErr').textContent = j.error || 'Could not add the booking.';
  });
});

/* ---------- treatments ---------- */
function loadTreatments(){
  api('treatments').then(function(j){
    TREATS = (j.treatments||[]).filter(function(t){ return t.active==1; });
    fillTreatSelects(); if($('mDate').value) fillTimes();
    $('treatBody').innerHTML = (j.treatments||[]).map(function(t){
      return '<tr data-id="'+t.id+'"><td><input value="'+esc(t.name)+'"></td><td><input class="mini" value="'+esc(t.cat)+'"></td>'
        + '<td><input class="mini" type="number" value="'+t.minutes+'"></td><td><input class="mini" type="number" value="'+t.price+'"></td>'
        + '<td><input class="mini" type="number" value="'+t.units+'" min="1" max="4"></td>'
        + '<td><input type="checkbox" '+(t.active==1?'checked':'')+'></td>'
        + '<td><button class="act saveTreat">Save</button></td></tr>';
    }).join('');
  });
}
$('treatBody').addEventListener('click', function(e){
  if(!e.target.classList.contains('saveTreat')) return;
  var tr = e.target.closest('tr'), inp = tr.querySelectorAll('input');
  api('treatment_save', {method:'POST', body:{id: parseInt(tr.dataset.id,10), name: inp[0].value, cat: inp[1].value, minutes: inp[2].value, price: inp[3].value, units: inp[4].value, active: inp[5].checked, sort: 0}})
    .then(function(j){ $('treatMsg').textContent = j.ok? '✓ Saved' : (j.error||'Error'); loadTreatments(); });
});
$('addTreat').addEventListener('click', function(){
  api('treatment_save', {method:'POST', body:{id:0, name:'New Treatment', cat:'Head Spa', minutes:60, price:68, units:1, active:true, sort:99}}).then(loadTreatments);
});

/* ---------- technicians ---------- */
function loadTechs(){
  api('technicians').then(function(j){
    $('techList').innerHTML = (j.technicians||[]).map(function(t){
      return '<div class="row" style="margin-bottom:8px"><div><input value="'+esc(t.name)+'" data-tech="'+t.id+'"></div>'
        + '<div style="flex:0"><label style="display:inline"><input type="checkbox" data-techon="'+t.id+'" '+(t.active==1?'checked':'')+'> Active</label></div>'
        + '<div style="flex:0"><button class="act saveTech" data-id="'+t.id+'">Save</button></div></div>';
    }).join('') || '<p style="color:var(--muted)">No technicians yet.</p>';
  });
}
$('addTech').addEventListener('click', function(){
  if(!$('newTech').value.trim()) return;
  api('technician_save', {method:'POST', body:{id:0, name: $('newTech').value}}).then(function(){ $('newTech').value=''; $('techMsg').textContent='✓ Added'; loadTechs(); });
});
$('techList').addEventListener('click', function(e){
  if(!e.target.classList.contains('saveTech')) return;
  var id = e.target.dataset.id;
  var name = document.querySelector('input[data-tech="'+id+'"]').value;
  var on = document.querySelector('input[data-techon="'+id+'"]').checked;
  api('technician_save', {method:'POST', body:{id: parseInt(id,10), name: name, active: on}}).then(function(){ $('techMsg').textContent='✓ Saved'; });
});

/* ---------- settings ---------- */
function timeOptions(sel, val){
  var h=''; for(var m=420; m<=1380; m+=30){ var hh=Math.floor(m/60), mm=m%60, ap=hh>=12?'PM':'AM', h12=hh%12||12; h+='<option value="'+m+'"'+(m===val?' selected':'')+'>'+h12+':'+String(mm).padStart(2,'0')+' '+ap+'</option>'; }
  sel.innerHTML = h;
}
function loadSettings(){
  api('settings').then(function(j){
    var s = j.settings||{};
    timeOptions($('sOpen'), parseInt(s.open_min||600,10)); timeOptions($('sClose'), parseInt(s.close_min||1260,10));
    $('sCap').value = s.capacity||2; $('sWin').value = s.window_days||30; $('sCut').value = s.cancel_cutoff_hours||3;
    $('blockedList').innerHTML = (j.blocked||[]).map(function(b){ return '<div style="margin-bottom:6px"><b>'+esc(b.d)+'</b> '+esc(b.note||'')+' <button class="act cancel unblock" data-d="'+esc(b.d)+'">Remove</button></div>'; }).join('') || '<p style="color:var(--muted)">No blocked dates.</p>';
  });
}
$('saveSettings').addEventListener('click', function(){
  api('settings_save', {method:'POST', body:{open_min: $('sOpen').value, close_min: $('sClose').value, capacity: $('sCap').value, window_days: $('sWin').value, cancel_cutoff_hours: $('sCut').value}})
    .then(function(j){ $('setMsg').textContent = j.ok? '✓ Saved' : (j.error||'Error'); });
});
$('bAdd').addEventListener('click', function(){
  if(!$('bDate').value) return;
  api('blocked_add', {method:'POST', body:{date: $('bDate').value, note: $('bNote').value}}).then(loadSettings);
});
$('blockedList').addEventListener('click', function(e){
  if(e.target.classList.contains('unblock')) api('blocked_remove', {method:'POST', body:{date: e.target.dataset.d}}).then(loadSettings);
});

function loadMessages(){
  api('messages').then(function(j){
    var list = j.messages||[];
    var unseen = list.filter(function(m){ return m.seen==0; }).length;
    $('msgCount').textContent = unseen; $('msgCount').classList.toggle('hidden', !unseen);
    $('msgList').innerHTML = list.length? list.map(function(m){
      return '<div class="card"><b>'+esc(m.name)+'</b> <span style="color:var(--muted)">'+esc(m.email)+(m.phone?' · '+esc(m.phone):'')+' · '+esc(m.created_at)+'</span>'
        + (m.seen==0?' <span class="badge pending">New</span>':'')
        + '<div style="margin-top:8px;white-space:pre-wrap">'+esc(m.message)+'</div>'
        + '<div style="margin-top:10px"><a class="act" href="mailto:'+esc(m.email)+'">Reply by email</a>'
        + '<button class="act" data-msg="'+m.id+'" data-seen="'+(m.seen==0?1:0)+'">'+(m.seen==0?'Mark as read':'Mark as unread')+'</button></div></div>';
    }).join('') : '<p style="color:var(--muted)">No messages yet — they will appear here when a guest writes from the About &amp; Contact page.</p>';
  });
}
$('msgList').addEventListener('click', function(e){
  var b = e.target.closest ? e.target.closest('button[data-msg]') : null;
  if(!b) return;
  api('message_seen', {method:'POST', body:{id: parseInt(b.dataset.msg,10), seen: b.dataset.seen==='1'}}).then(loadMessages);
});
function loadAll(){
  $('dayPick').value = viewDay; $('mDate').value = todayStr();
  loadBookings(); loadMessages(); loadTreatments(); loadTechs(); loadSettings();
}
</script>
</body>
</html>
