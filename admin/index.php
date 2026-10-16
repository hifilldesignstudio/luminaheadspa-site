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
  #pCalGrid .day{cursor:default}
  #pCalGrid .day.bookable{cursor:pointer}
  #pCalGrid .day.p-many{background:#e2f2e6;border-color:#b7dfc4}
  #pCalGrid .day.p-some{background:#f3f7ea;border-color:#dde5c8}
  #pCalGrid .day.p-few{background:#fdf0d5;border-color:#ecd3a0}
  #pCalGrid .day.p-full{background:#f6e3e0;border-color:#eec6bf}
  #pCalGrid .day.p-grey{background:#efedea;color:var(--muted)}
  #pCalGrid .day.sel{border-color:var(--brass-d);box-shadow:inset 0 0 0 2px var(--brass-d)}
  .pcal-key{display:inline-block;width:11px;height:11px;border-radius:3px;border:1px solid var(--line);vertical-align:-1px}
  .pcal-key.p-many{background:#e2f2e6}.pcal-key.p-some{background:#f3f7ea}.pcal-key.p-few{background:#fdf0d5}.pcal-key.p-full{background:#f6e3e0}.pcal-key.p-grey{background:#efedea}
  .msg{min-height:20px;color:var(--green);font-size:14px;margin-top:10px}
  .err{color:var(--red)}
  #login{max-width:380px;margin:70px auto;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:28px}
  .hidden{display:none}
  .row{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
  .row>*{flex:1;min-width:130px}

  #toasts{position:fixed;top:14px;right:14px;z-index:999;display:flex;flex-direction:column;gap:8px}
  .toast{background:#2b2118;color:#fdf8ef;padding:12px 16px;border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,.35);font-size:14px;line-height:1.45;cursor:pointer;max-width:330px;transition:opacity .4s}
  .toast.out{opacity:0}
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
        <div style="flex:0"><button class="btn" id="upBtn">Upcoming</button> <button class="btn dark" id="todayBtn">Today</button> <button class="btn" id="alertBtn">🔔 Enable alerts</button></div>
      </div>
      <h3 class="sec">Waiting for confirmation</h3>
      <div id="pendingList"></div>
      <h3 class="sec" id="dayTitle">Upcoming bookings</h3>
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
        <div class="row" style="margin-bottom:10px"><button class="act" id="pCalPrev">‹ Prev</button><b id="pCalTitle" style="text-align:center"></b><button class="act" id="pCalNext">Next ›</button></div>
        <div class="cal" id="pCalGrid"></div>
        <p style="color:var(--muted);font-size:12.5px;margin:10px 0 14px">Free start times for the treatment(s) picked below: <span class="pcal-key p-many"></span> 6 or more · <span class="pcal-key p-some"></span> 3–5 · <span class="pcal-key p-few"></span> 1–2 · <span class="pcal-key p-full"></span> Full · <span class="pcal-key p-grey"></span> Closed, past or too far ahead. Tap a day with times free to pick it.</p>
        <div class="grid">
          <div><label>Guest name</label><input id="mName"></div>
          <div><label>Phone</label><input id="mPhone"></div>
          <div><label>Email (optional)</label><input id="mEmail" type="email"></div>
          <div><label>Note (optional)</label><input id="mNote"></div>
          <div><label>Treatment 1</label><select id="mT1"></select></div>
          <div><label>Treatment 2 (optional)</label><select id="mT2"></select></div>
          <div><label>Date</label><input type="date" id="mDate"></div>
          <div><label>Start time</label><select id="mTime"></select></div>
          <div id="mTechWrap" class="hidden"><label>Therapist</label><select id="mTech"></select></div>
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
        <div class="row" style="margin-top:12px"><div><label>New technician name</label><input id="newTech"></div><div><label>Email (receives booking notifications)</label><input id="newTechEmail" type="email"></div><div style="flex:0"><button class="btn" id="addTech">Add</button></div></div>
        <p class="msg" id="techMsg"></p>
        <p style="color:var(--muted);font-size:13px">Guests never pick a technician — bookings are shared across the team. How many guests you can serve at the same time is set by "Simultaneous guests" in Hours &amp; Settings. A technician with an email on file receives every booking notification too — and the shop's main email always receives them. In Per-technician booking mode (Hours &amp; Settings), guests can pick a therapist or choose "No preference", and the schedules you set with "Schedule &amp; days off" decide who can be booked when.</p>
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
          <div><label>Booking mode</label><select id="sMode"><option value="pool">Shared pool</option><option value="staff">Per-technician</option></select>
          <p style="color:var(--muted);font-size:13px;margin:6px 0 0">Shared pool: guests book against one shared capacity. Per-technician: every booking is assigned to a specific therapist using the schedules on the Technicians tab; guests may pick a therapist or choose "No preference".</p></div>
          <div style="grid-column:1/-1"><label>Notification emails</label><input id="sNotify" type="text" placeholder="info@luminaheadspa.ca">
          <p style="color:var(--muted);font-size:13px;margin:6px 0 0">New-booking, cancellation, reschedule and website-message notifications are sent to these addresses. Separate several with commas — for example the owner and a staff member. Leave blank to use info@luminaheadspa.ca only.</p></div>
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
var listMode = 'upcoming';
(function(){ var d=new Date(); calY=d.getFullYear(); calM=d.getMonth(); })();
function badge(st){ return '<span class="badge '+st+'">'+st+'</span>'; }
function cardHTML(b){
  var items = b.items.map(function(i){ return esc(i.name_snapshot)+' <small style="color:var(--muted)">'+i.startLabel+'</small>'; }).join('<br>');
  var h = '<div class="card booking"><div class="time">'+esc(b.startLabel)+'<small>to '+esc(b.endLabel)+'</small><small>'+esc(b.dayLabel)+'</small></div>'
    + '<div class="info"><b>'+esc(b.name)+'</b> '+badge(b.status)+(b.source==='phone'?' <span class="badge phone">Phone</span>':'')
    + ' <small style="color:var(--muted)">Ref '+esc(b.ref)+'</small><div>'+items+'</div>'
    + '<div>Phone: '+esc(b.phone)+(b.email?' · '+esc(b.email):'')+'</div>'
    + (b.note? '<div class="note">Note: '+esc(b.note)+'</div>':'')
    + (b.techName? '<div>Therapist: <b>'+esc(b.techName)+(b.tech2Name? ' &amp; '+esc(b.tech2Name):'')+'</b>'+(b.tech_pref==1?' (guest pick)':'')+'</div>' : (STAFFMODE? '<div style="color:var(--muted)">Therapist: not assigned yet</div>' : ''))
    + '<div style="color:var(--muted)">Total $'+b.total_price+' · '+b.total_min+' min</div></div>';
  if(b.status==='pending') h += '<div><button class="act confirm" data-id="'+b.id+'" data-st="confirmed">Confirm</button><button class="act cancel" data-id="'+b.id+'" data-st="cancelled">Cancel</button></div>';
  else if(b.status==='confirmed') h += '<div><button class="act" data-id="'+b.id+'" data-st="done">Done</button><button class="act cancel" data-id="'+b.id+'" data-st="cancelled">Cancel</button></div>';
  if(STAFFMODE && (b.status==='pending' || b.status==='confirmed')){
    var aopts = '<option value="0">Auto-assign</option>' + TECHLIST.filter(function(t){ return t.active==1; }).map(function(t){ return '<option value="'+t.id+'"'+(String(b.tech_id)===String(t.id)?' selected':'')+'>'+esc(t.name)+'</option>'; }).join('');
    h += '<div style="margin-top:8px"><label style="display:inline">Therapist</label> <select class="asgSel">'+aopts+'</select> <button class="act assignBtn" data-id="'+b.id+'">Assign</button></div>';
  }
  return h + '</div>';
}
document.body.addEventListener('click', function(e){
  var btn = e.target.closest ? e.target.closest('button[data-st]') : null;
  if(!btn) return;
  var fromQueue = btn.closest && btn.closest('#pendingList');
  api('booking_status', {method:'POST', body:{id: parseInt(btn.dataset.id,10), status: btn.dataset.st}}).then(function(){
    if(fromQueue && btn.dataset.st==='confirmed' && !$('q').value.trim()) listMode = 'upcoming';
    loadBookings();
  });
});
function loadBookings(){
  var q = $('q').value.trim();
  api('bookings', {qs: '&status=pending'}).then(function(j){
    var pend = (j.bookings||[]);
    $('pendCount').textContent = pend.length; $('pendCount').classList.toggle('hidden', !pend.length);
    $('pendingList').innerHTML = pend.length? pend.map(cardHTML).join('') : '<p style="color:var(--muted)">All caught up — nothing waiting. ✓</p>';
  });
  var qs, title;
  if(q){ qs = '&q='+encodeURIComponent(q); title = 'Search results'; }
  else if(listMode==='upcoming'){ qs = '&from='+todayStr(); title = 'Upcoming bookings'; }
  else { qs = '&date='+viewDay; title = 'Bookings for '+viewDay; }
  $('dayTitle').textContent = title;
  api('bookings', {qs: qs}).then(function(j){
    var list = (j.bookings||[]);
    if(listMode==='upcoming' && !q) list = list.filter(function(b){ return b.status!=='cancelled' && b.status!=='pending'; });
    $('dayList').innerHTML = list.length? list.map(cardHTML).join('') : '<p style="color:var(--muted)">No bookings found.</p>';
  });
  loadCal();
}
document.body.addEventListener('click', function(e){
  var btn = e.target.closest ? e.target.closest('button.assignBtn') : null;
  if(!btn) return;
  var card = btn.closest('.card');
  var sel = card? card.querySelector('select.asgSel') : null;
  if(!sel) return;
  api('booking_assign', {method:'POST', body:{id: parseInt(btn.dataset.id,10), techId: parseInt(sel.value,10)||0}}).then(function(j){
    if(j.ok) loadBookings(); else alert(j.error||'Could not assign that therapist.');
  });
});
$('q').addEventListener('input', loadBookings);
$('dayPick').addEventListener('change', function(){ viewDay = this.value || todayStr(); listMode='day'; loadBookings(); });
$('todayBtn').addEventListener('click', function(){ viewDay = todayStr(); $('dayPick').value = viewDay; $('q').value=''; loadBookings(); });
$('upBtn').addEventListener('click', function(){ listMode='upcoming'; $('q').value=''; loadBookings(); });
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
      el.addEventListener('click', function(){ viewDay = el.dataset.day; $('dayPick').value = viewDay; $('q').value=''; listMode='day'; loadBookings(); });
    });
  });
}
$('calPrev').addEventListener('click', function(){ calM--; if(calM<0){calM=11;calY--;} loadCal(); });
$('calNext').addEventListener('click', function(){ calM++; if(calM>11){calM=0;calY++;} loadCal(); });

/* ---------- phone booking ---------- */
var STAFFMODE = false, TECHLIST = [];
var TREATS = [];
function fillTreatSelects(){
  var opts = TREATS.map(function(t){ return '<option value="'+t.id+'">'+esc(t.name)+' · '+t.minutes+' min · $'+t.price+'</option>'; }).join('');
  $('mT1').innerHTML = opts;
  $('mT2').innerHTML = '<option value="">— none —</option>' + opts;
}
function phoneChain(){
  var ids = [$('mT1').value, $('mT2').value].filter(Boolean).map(Number);
  var mins = ids.reduce(function(a,id){ var t=TREATS.find(function(x){return x.id===id}); return a+(t? t.minutes:0); }, 0) || 60;
  var units = ids.reduce(function(a,id){ var t=TREATS.find(function(x){return x.id===id}); return Math.max(a, t? t.units:1); }, 1);
  return {mins: mins, units: units};
}
function fillTimes(){
  var ch = phoneChain();
  var date = $('mDate').value || todayStr();
  fetch(PUB+'?action=availability&date='+date+'&minutes='+ch.mins+'&units='+ch.units+(STAFFMODE? '&techId='+(($('mTech')||{}).value||0) : '')).then(function(r){return r.json()}).then(function(j){
    var starts = (j.starts||[]);
    $('mTime').innerHTML = starts.length? starts.map(function(s){ var h=Math.floor(s/60), m=s%60, ap=h>=12?'PM':'AM', h12=h%12||12; return '<option value="'+s+'">'+h12+':'+String(m).padStart(2,'0')+' '+ap+'</option>'; }).join('') : '<option value="">No times available</option>';
  });
}
/* Phone Booking availability calendar — a month-at-a-glance guide to how
   many start times are still free for the chain picked in the form below.
   Purely visual: the date input still works as before, and submitting a
   phone booking still revalidates (with the overbook confirm). */
var pCalY, pCalM, pCalCache = {}, pCalReq = 0;
(function(){ var d=new Date(); pCalY=d.getFullYear(); pCalM=d.getMonth(); })();
function pcalCell(entry){
  if(!entry) return {cls:'p-grey', label:'—', bookable:false};
  if(entry.state==='many' || entry.state==='some' || entry.state==='few'){
    return {cls:'p-'+entry.state, label: entry.starts+(entry.starts===1?' time':' times'), bookable:true};
  }
  if(entry.state==='full') return {cls:'p-full', label:'Full', bookable:false};
  if(entry.state==='blocked') return {cls:'p-grey', label:'Closed', bookable:false};
  return {cls:'p-grey', label:'—', bookable:false};
}
function renderPhoneCal(days){
  var ym = pCalY+'-'+String(pCalM+1).padStart(2,'0');
  $('pCalTitle').textContent = new Date(pCalY, pCalM, 1).toLocaleDateString('en-CA',{month:'long', year:'numeric'});
  var first = new Date(pCalY, pCalM, 1), lead = (first.getDay()+6)%7, dim = new Date(pCalY, pCalM+1, 0).getDate();
  var sel = $('mDate').value;
  var h = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].map(function(d){ return '<div class="dow">'+d+'</div>'; }).join('');
  for(var i=0;i<lead;i++) h += '<div></div>';
  for(var dnum=1; dnum<=dim; dnum++){
    var ds = ym+'-'+String(dnum).padStart(2,'0');
    var cell = pcalCell(days? days[ds] : null);
    h += '<div class="day '+cell.cls+(cell.bookable?' bookable':'')+(ds===sel?' sel':'')+'"'+(cell.bookable?' data-pday="'+ds+'"':'')+'>'+dnum+'<small>'+cell.label+'</small></div>';
  }
  $('pCalGrid').innerHTML = h;
  $('pCalGrid').querySelectorAll('.day[data-pday]').forEach(function(el){
    el.addEventListener('click', function(){
      $('mDate').value = el.dataset.pday;
      fillTimes();
      renderPhoneCal(pCalCache[pcalKey()] || null);
    });
  });
}
function pcalKey(){
  var ch = phoneChain();
  var techId = STAFFMODE? (parseInt(($('mTech')||{}).value||'0',10)||0) : 0;
  return (pCalY+'-'+String(pCalM+1).padStart(2,'0'))+'|'+ch.mins+'|'+ch.units+'|'+techId;
}
function loadPhoneCal(){
  var key = pcalKey();
  if(pCalCache[key]){ renderPhoneCal(pCalCache[key]); return; }
  var ch = phoneChain();
  var techId = STAFFMODE? (parseInt(($('mTech')||{}).value||'0',10)||0) : 0;
  var ym = pCalY+'-'+String(pCalM+1).padStart(2,'0');
  var req = ++pCalReq;
  renderPhoneCal(null);
  api('phone_availability', {qs: '&ym='+ym+'&minutes='+ch.mins+'&units='+ch.units+(STAFFMODE? '&techId='+techId : '')}).then(function(j){
    if(req!==pCalReq) return;
    if(j.ok){ pCalCache[key] = j.days||{}; renderPhoneCal(pCalCache[key]); }
  });
}
$('pCalPrev').addEventListener('click', function(){ pCalM--; if(pCalM<0){ pCalM=11; pCalY--; } loadPhoneCal(); });
$('pCalNext').addEventListener('click', function(){ pCalM++; if(pCalM>11){ pCalM=0; pCalY++; } loadPhoneCal(); });
document.querySelector('.tab[data-tab="phone"]').addEventListener('click', function(){ loadPhoneCal(); });
['mT1','mT2','mDate','mTech'].forEach(function(id){ document.addEventListener('change', function(e){ if(e.target.id===id) fillTimes(); }); });
['mT1','mT2','mTech'].forEach(function(id){ document.addEventListener('change', function(e){ if(e.target.id===id) loadPhoneCal(); }); });
document.addEventListener('change', function(e){
  if(e.target.id!=='mDate') return;
  var parts = $('mDate').value? $('mDate').value.split('-') : [];
  if(parts.length===3){
    var y = parseInt(parts[0],10), m = parseInt(parts[1],10)-1;
    if(y!==pCalY || m!==pCalM){ pCalY = y; pCalM = m; loadPhoneCal(); return; }
  }
  var cur = pCalCache[pcalKey()]; if(cur) renderPhoneCal(cur);
});
$('mAdd').addEventListener('click', function(){
  var items = [$('mT1').value, $('mT2').value].filter(Boolean).map(Number);
  var body = {name: $('mName').value, phone: $('mPhone').value, email: $('mEmail').value, note: $('mNote').value,
              date: $('mDate').value, start: parseInt($('mTime').value,10), items: items, techId: parseInt(($('mTech')||{}).value||'0',10)||0};
  $('mErr').textContent = '';
  api('phone_booking', {method:'POST', body: body}).then(function(j){
    if(j.ok){ $('mMsg').textContent = '✓ Added — reference '+j.ref; pCalCache = {}; loadPhoneCal(); loadBookings(); }
    else if(j.full){ if(confirm(j.error)){ body.force = true; api('phone_booking', {method:'POST', body: body}).then(function(j2){ if(j2.ok){ $('mMsg').textContent='✓ Added (overbooked) — reference '+j2.ref; pCalCache = {}; loadPhoneCal(); loadBookings(); } }); } }
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
function fillTechSelects(){
  if(!$('mTech')) return;
  var cur = $('mTech').value;
  $('mTech').innerHTML = '<option value="0">Auto-assign</option>' + TECHLIST.filter(function(t){ return t.active==1; }).map(function(t){ return '<option value="'+t.id+'">'+esc(t.name)+'</option>'; }).join('');
  if(cur) $('mTech').value = cur;
}
function syncStaffUI(){
  var w = $('mTechWrap'); if(w) w.classList.toggle('hidden', !STAFFMODE);
  fillTechSelects();
}
function loadTechs(){
  api('technicians').then(function(j){
    TECHLIST = j.technicians||[];
    $('techList').innerHTML = TECHLIST.map(function(t){
      return '<div class="row" style="margin-bottom:8px"><div><input value="'+esc(t.name)+'" data-tech="'+t.id+'"></div>'
        + '<div><input type="email" placeholder="Email for notifications" value="'+esc(t.email||'')+'" data-techemail="'+t.id+'"></div>'
        + '<div style="flex:0"><label style="display:inline"><input type="checkbox" data-techon="'+t.id+'" '+(t.active==1?'checked':'')+'> Active</label></div>'
        + '<div style="flex:0"><button class="act saveTech" data-id="'+t.id+'">Save</button></div>'
        + '<div style="flex:0"><button class="act schedToggle" data-id="'+t.id+'">Schedule &amp; days off</button></div></div>'
        + '<div id="sched-'+t.id+'" class="hidden" style="border-top:1px dashed #ddd;padding:10px 0 4px;margin-bottom:10px"></div>';
    }).join('') || '<p style="color:var(--muted)">No technicians yet.</p>';
    fillTechSelects();
  });
}
var DAYNAMES = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
function techSchedMap(t){
  var map = null; try{ map = t.sched? JSON.parse(t.sched) : null; }catch(e){}
  return map;
}
function buildSchedPanel(id){
  var t = TECHLIST.find(function(x){ return String(x.id)===String(id); });
  var panel = $('sched-'+id); if(!panel || !t) return;
  var map = techSchedMap(t);
  var h = '<div style="font-weight:600;margin-bottom:6px">Weekly schedule</div>';
  for(var d=0; d<7; d++){
    var v = map? map[d] : [600,1260];
    h += '<div class="row" style="margin-bottom:6px;align-items:center"><div style="flex:0;min-width:92px">'+DAYNAMES[d]+'</div>'
      + '<div style="flex:0"><label style="display:inline"><input type="checkbox" id="soff-'+id+'-'+d+'"'+(map && !v?' checked':'')+'> Day off</label></div>'
      + '<div><select id="ss-'+id+'-'+d+'"></select></div><div><select id="se-'+id+'-'+d+'"></select></div></div>';
  }
  h += '<button class="act saveSched" data-id="'+id+'">Save schedule</button> <span class="msg" id="schedMsg-'+id+'"></span>'
    + '<div style="font-weight:600;margin:12px 0 6px">Days off (one-off dates)</div><div id="offList-'+id+'"></div>'
    + '<div class="row"><div><input type="date" id="offDate-'+id+'"></div><div style="flex:0"><button class="act addOff" data-id="'+id+'">Add day off</button></div></div>'
    + '<p style="color:var(--muted);font-size:12.5px;margin-top:6px">A technician with no schedule saved works the shop hours (10:00 AM \u2013 9:00 PM) every day. Schedules and days off only take effect in Per-technician booking mode.</p>';
  panel.innerHTML = h;
  for(var d2=0; d2<7; d2++){
    var v2 = map? map[d2] : [600,1260];
    timeOptions($('ss-'+id+'-'+d2), v2? v2[0] : 600);
    timeOptions($('se-'+id+'-'+d2), v2? v2[1] : 1260);
  }
  loadOffList(id);
}
function loadOffList(id){
  api('tech_days_off', {qs: '&techId='+id}).then(function(j){
    var el = $('offList-'+id); if(!el) return;
    el.innerHTML = (j.days||[]).map(function(d){ return '<div style="margin-bottom:4px"><b>'+esc(d)+'</b> <button class="act cancel rmOff" data-tid="'+id+'" data-d="'+esc(d)+'">Remove</button></div>'; }).join('') || '<p style="color:var(--muted)">No one-off days off.</p>';
  });
}
function saveSched(id){
  var arr = [];
  for(var d=0; d<7; d++){
    if($('soff-'+id+'-'+d).checked) arr.push(null);
    else arr.push([parseInt($('ss-'+id+'-'+d).value,10), parseInt($('se-'+id+'-'+d).value,10)]);
  }
  var body = {id: parseInt(id,10), name: document.querySelector('input[data-tech="'+id+'"]').value,
              email: document.querySelector('input[data-techemail="'+id+'"]').value,
              active: document.querySelector('input[data-techon="'+id+'"]').checked, sched: JSON.stringify(arr)};
  api('technician_save', {method:'POST', body: body}).then(function(j){
    var m = $('schedMsg-'+id); if(m) m.textContent = j.ok? '✓ Schedule saved' : (j.error||'Error');
    if(j.ok){ var t = TECHLIST.find(function(x){ return String(x.id)===String(id); }); if(t) t.sched = JSON.stringify(arr); $('techMsg').textContent='✓ Saved'; }
  });
}
$('addTech').addEventListener('click', function(){
  if(!$('newTech').value.trim()) return;
  api('technician_save', {method:'POST', body:{id:0, name: $('newTech').value, email: $('newTechEmail').value}}).then(function(j){ if(j.ok){ $('newTech').value=''; $('newTechEmail').value=''; $('techMsg').textContent='✓ Added'; loadTechs(); } else $('techMsg').textContent = j.error||'Error'; });
});
$('techList').addEventListener('click', function(e){
  var cl = e.target.classList;
  if(cl.contains('schedToggle')){
    var panel = $('sched-'+e.target.dataset.id);
    if(panel){ panel.classList.toggle('hidden'); if(!panel.dataset.built){ panel.dataset.built='1'; buildSchedPanel(e.target.dataset.id); } }
    return;
  }
  if(cl.contains('saveSched')){ saveSched(e.target.dataset.id); return; }
  if(cl.contains('addOff')){
    var inp = $('offDate-'+e.target.dataset.id);
    if(inp && inp.value) api('tech_dayoff_add', {method:'POST', body:{techId: parseInt(e.target.dataset.id,10), date: inp.value}}).then(function(){ loadOffList(e.target.dataset.id); });
    return;
  }
  if(cl.contains('rmOff')){
    api('tech_dayoff_remove', {method:'POST', body:{techId: parseInt(e.target.dataset.tid,10), date: e.target.dataset.d}}).then(function(){ loadOffList(e.target.dataset.tid); });
    return;
  }
  if(!cl.contains('saveTech')) return;
  var id = e.target.dataset.id;
  var name = document.querySelector('input[data-tech="'+id+'"]').value;
  var on = document.querySelector('input[data-techon="'+id+'"]').checked;
  var em = document.querySelector('input[data-techemail="'+id+'"]').value;
  api('technician_save', {method:'POST', body:{id: parseInt(id,10), name: name, active: on, email: em}}).then(function(){ $('techMsg').textContent='✓ Saved'; });
});

/* ---------- new-booking alerts (sound + popup while this page is open) ---------- */
var alertOn = localStorage.getItem('lumina_alerts')==='1';
var snap = null, actx = null, baseTitle = document.title;
function chime(){ try{ actx = actx || new (window.AudioContext||window.webkitAudioContext)(); if(actx.state==='suspended') actx.resume(); var t=actx.currentTime; [880,1174].forEach(function(f,i){ var o=actx.createOscillator(), g=actx.createGain(); o.frequency.value=f; o.type='sine'; g.gain.setValueAtTime(0.0001, t+i*0.18); g.gain.exponentialRampToValueAtTime(0.22, t+i*0.18+0.03); g.gain.exponentialRampToValueAtTime(0.0001, t+i*0.18+0.5); o.connect(g); g.connect(actx.destination); o.start(t+i*0.18); o.stop(t+i*0.18+0.55); }); }catch(e){} }
function toast(html){
  var box = $('toasts'); if(!box) return;
  var el = document.createElement('div'); el.className='toast'; el.innerHTML = html;
  el.addEventListener('click', function(){ el.remove(); document.title = baseTitle; document.querySelector('.tab[data-tab="bookings"]').click(); listMode='upcoming'; loadBookings(); });
  box.appendChild(el);
  setTimeout(function(){ el.classList.add('out'); setTimeout(function(){ el.remove(); }, 400); }, 14000);
}
function notifyDesktop(title, body){ try{ if(window.Notification && Notification.permission==='granted'){ var n = new Notification(title, {body: body}); n.onclick = function(){ window.focus(); n.close(); }; } }catch(e){} }
function pollAlerts(){
  if(!alertOn) return;
  api('bookings', {qs: '&from='+todayStr()}).then(function(j){
    var cur = {}; (j.bookings||[]).forEach(function(b){ cur[b.id] = b; });
    if(snap){
      Object.keys(cur).forEach(function(id){
        var b = cur[id], old = snap[id];
        if(!old && b.source==='online' && b.status==='pending'){
          chime(); document.title = '(•) New booking — ' + baseTitle;
          toast('🔔 <b>New booking</b> — ' + esc(b.name) + '<br>' + esc(b.dayLabel) + ' · ' + esc(b.startLabel) + ' · Ref ' + esc(b.ref));
          notifyDesktop('New Lumina booking — ' + b.ref, b.name + ' · ' + b.dayLabel + ' ' + b.startLabel);
        } else if(old && old.status!=='cancelled' && b.status==='cancelled'){
          chime();
          toast('⚠️ <b>Booking cancelled</b> — ' + esc(b.name) + '<br>' + esc(b.dayLabel) + ' · ' + esc(b.startLabel) + ' · Ref ' + esc(b.ref));
          notifyDesktop('Lumina booking cancelled — ' + b.ref, b.name + ' · ' + b.dayLabel + ' ' + b.startLabel);
        }
      });
    }
    snap = cur;
  }).catch(function(){});
}
function refreshAlertBtn(){ $('alertBtn').textContent = alertOn? '🔔 Alerts on' : '🔔 Enable alerts'; }
$('alertBtn').addEventListener('click', function(){
  alertOn = !alertOn; localStorage.setItem('lumina_alerts', alertOn? '1' : '0');
  if(alertOn){ chime(); if(window.Notification && Notification.permission==='default') Notification.requestPermission(); snap = null; pollAlerts(); }
  refreshAlertBtn();
});
refreshAlertBtn();
window.addEventListener('focus', function(){ document.title = baseTitle; });
document.addEventListener('click', function(){ if(actx && actx.state==='suspended') actx.resume(); });
if(alertOn) pollAlerts();
setInterval(pollAlerts, 30000);

/* ---------- settings ---------- */
function timeOptions(sel, val){
  var h=''; for(var m=420; m<=1380; m+=30){ var hh=Math.floor(m/60), mm=m%60, ap=hh>=12?'PM':'AM', h12=hh%12||12; h+='<option value="'+m+'"'+(m===val?' selected':'')+'>'+h12+':'+String(mm).padStart(2,'0')+' '+ap+'</option>'; }
  sel.innerHTML = h;
}
function loadSettings(){
  return api('settings').then(function(j){
    var s = j.settings||{};
    timeOptions($('sOpen'), parseInt(s.open_min||600,10)); timeOptions($('sClose'), parseInt(s.close_min||1260,10));
    $('sCap').value = s.capacity||2; $('sWin').value = s.window_days||30; $('sCut').value = s.cancel_cutoff_hours||3;
    $('sNotify').value = s.notify_emails||'';
    $('sMode').value = s.booking_mode||'pool';
    STAFFMODE = (s.booking_mode||'pool')==='staff';
    syncStaffUI();
    $('blockedList').innerHTML = (j.blocked||[]).map(function(b){ return '<div style="margin-bottom:6px"><b>'+esc(b.d)+'</b> '+esc(b.note||'')+' <button class="act cancel unblock" data-d="'+esc(b.d)+'">Remove</button></div>'; }).join('') || '<p style="color:var(--muted)">No blocked dates.</p>';
  });
}
$('saveSettings').addEventListener('click', function(){
  api('settings_save', {method:'POST', body:{open_min: $('sOpen').value, close_min: $('sClose').value, capacity: $('sCap').value, window_days: $('sWin').value, cancel_cutoff_hours: $('sCut').value, notify_emails: $('sNotify').value, booking_mode: $('sMode').value}})
    .then(function(j){ $('setMsg').textContent = j.ok? '✓ Saved' : (j.error||'Error'); if(j.ok){ loadSettings(); loadBookings(); } });
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
  $('dayPick').value = ''; $('mDate').value = todayStr();
  loadMessages(); loadTreatments();
  loadSettings().then(function(){ loadTechs(); loadBookings(); });
}
</script>
<div id="toasts"></div>
</body>
</html>
