<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Your Booking · Lumina Head Spa &amp; Beauty</title>
<link rel="icon" href="../favicon-32.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root{--ink:#24211c;--muted:#7d7161;--line:#e5dcc8;--brass:#a8854a;--brass-d:#8a6c36;--card:#fffdf8;--red:#a83c2a}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Jost',sans-serif;background:#f8f4ec;color:var(--ink);font-weight:300}
  .wrap{max-width:560px;margin:0 auto;padding:36px 20px 70px}
  .brand{text-align:center;margin-bottom:26px}
  .brand img{height:64px}
  h1{font-family:'Cormorant Garamond',serif;font-weight:500;font-size:32px;text-align:center;margin-bottom:6px}
  .sub{text-align:center;color:var(--muted);margin-bottom:24px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px}
  .lines{font-size:16.5px;line-height:1.65;margin:10px 0}
  .total{border-top:1px solid var(--line);padding-top:10px;margin-top:10px;font-weight:500}
  .badge{display:inline-block;font-size:12px;border-radius:99px;padding:2px 11px;letter-spacing:.05em}
  .badge.pending{background:#fdf0d5;color:#8a6d1f}.badge.confirmed{background:#e2f2e6;color:#2e7d4f}.badge.cancelled{background:#f6e3e0;color:var(--red)}.badge.done{background:#e8e4da;color:var(--muted)}
  h2{font-family:'Cormorant Garamond',serif;font-weight:500;font-size:23px;margin:26px 0 10px}
  .days{display:flex;gap:7px;overflow-x:auto;padding-bottom:6px}
  .day{flex:0 0 auto;border:1px solid var(--line);background:#fff;border-radius:11px;padding:8px 11px;text-align:center;cursor:pointer;font-size:13.5px}
  .day small{display:block;color:var(--muted)}
  .day.sel{background:var(--ink);color:#fdf8ec;border-color:var(--ink)} .day.sel small{color:#d8cdb2}
  .day.dis{opacity:.35;pointer-events:none}
  .slots{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
  .slot{border:1px solid #d8cdb8;background:#fff;border-radius:99px;padding:8px 15px;cursor:pointer;font-size:14.5px;font-family:inherit}
  .slot.sel{background:var(--brass);color:#fff;border-color:var(--brass)}
  .btn{display:inline-block;border:none;border-radius:99px;padding:12px 26px;font-size:15.5px;cursor:pointer;font-family:inherit;font-weight:500}
  .btn.gold{background:linear-gradient(135deg,#c9a45c,#a07c3c);color:#fff}
  .btn.ghost{background:none;border:1px solid #d8cdb8;color:var(--ink)}
  .btn.danger{background:none;border:1px solid var(--red);color:var(--red)}
  .msg{margin-top:14px;font-size:15px;min-height:20px}
  .err{color:var(--red)} .ok{color:#2e7d4f}
  .foot{text-align:center;color:var(--muted);font-size:13.5px;margin-top:30px;line-height:1.7}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand"><a href="../" title="Back to the Lumina homepage"><img src="../lumina-logo-dark.svg" alt="Lumina Head Spa &amp; Beauty"></a></div>
  <h1>Manage your booking</h1>
  <p class="sub">Reschedule or cancel — no login needed with your private link.</p>
  <div id="root"><p style="text-align:center;color:var(--muted)">Loading your booking…</p></div>
  <p class="foot">Lumina Head Spa &amp; Beauty · 9505 Keele Street, Unit 1A, Maple<br>Questions? Call us at <a href="tel:+16473688148" style="color:var(--brass-d)">647-368-8148</a></p>
</div>
<script>
var token = new URLSearchParams(location.search).get('t') || '';
var API = '../api/public.php';
var bk = null, selDate = null, selStart = null;
function $(id){ return document.getElementById(id); }
function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;'); }
function fmtT(mins){ var h=Math.floor(mins/60), m=mins%60, ap=h>=12?'PM':'AM', h12=h%12||12; return h12+':'+String(m).padStart(2,'0')+' '+ap; }
function dstr(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
function post(action, body){
  return fetch(API+'?action='+action, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)})
    .then(function(r){ return r.json(); });
}
function render(){
  var root = $('root');
  if(!bk){ root.innerHTML = '<div class="card"><p>We could not find that booking link. Please check the link in your confirmation email, or call us at 647-368-8148.</p></div>'; return; }
  var b = bk;
  var lines = b.lines.map(function(l){ return esc(l); }).join('<br>');
  var html = '<div class="card"><span class="badge '+b.status+'">'+b.status+'</span>'
    + '<p style="margin-top:10px">Hi '+esc(b.name)+' — booking <b>'+esc(b.ref)+'</b></p>'
    + '<div class="lines"><b>'+esc(b.dayLabel)+'</b><br>'+lines+'</div>'
    + '<div class="total">Total due at the shop: $'+b.totalPrice+' · '+b.totalMin+' minutes</div>';
  if(b.status === 'cancelled'){ html += '<p class="msg err">This booking has been cancelled. You are welcome to book again any time.</p>'; }
  else if(b.status !== 'done'){
    html += '<h2>Move it to another time</h2><div class="days" id="days"></div><div class="slots" id="slots"></div>'
      + '<p class="msg" id="rmsg"></p>'
      + '<button class="btn gold" id="doMove" disabled>Reschedule to this time</button> '
      + '<h2>Can\'t make it?</h2>'
      + '<button class="btn danger" id="doCancel">Cancel this booking</button>'
      + '<p class="msg" id="cmsg"></p>';
  }
  root.innerHTML = html + '</div>';
  if(b.status !== 'cancelled' && b.status !== 'done') wire();
}
function wire(){
  var daysEl = $('days'), today = new Date(); today.setHours(0,0,0,0);
  var names = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'], months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  for(var i=0;i<30;i++){
    (function(){
      var d = new Date(today); d.setDate(d.getDate()+i);
      var el = document.createElement('div'); el.className = 'day';
      el.innerHTML = '<small>'+(i===0?'Today':names[d.getDay()])+'</small>'+months[d.getMonth()]+' '+d.getDate();
      el.addEventListener('click', function(){
        daysEl.querySelectorAll('.day').forEach(function(x){x.classList.remove('sel')});
        el.classList.add('sel'); selDate = dstr(d); selStart = null; $('doMove').disabled = true; loadSlots();
      });
      daysEl.appendChild(el);
    })();
  }
  $('doMove').addEventListener('click', function(){
    if(!selDate || selStart===null) return;
    post('manage_reschedule', {token: token, date: selDate, start: selStart}).then(function(j){
      if(j.ok){ $('rmsg').className='msg ok'; $('rmsg').textContent = '✓ Done — your booking is now on '+j.dayLabel+'. We have emailed the shop.'; bk.date = selDate; bk.dayLabel = j.dayLabel; bk.lines = j.lines; setTimeout(render, 1800); }
      else { $('rmsg').className='msg err'; $('rmsg').textContent = j.error || 'That did not work — please try another time.'; }
    });
  });
  $('doCancel').addEventListener('click', function(){
    if(!confirm('Cancel this booking? The time slot will be released.')) return;
    post('manage_cancel', {token: token}).then(function(j){
      if(j.ok){ bk.status = 'cancelled'; render(); }
      else { $('cmsg').className='msg err'; $('cmsg').textContent = j.error || 'Could not cancel online — please call us.'; }
    });
  });
}
function loadSlots(){
  fetch(API+'?action=availability&date='+selDate+'&minutes='+bk.totalMin+'&units='+bk.units+'&exclude='+bk.id).then(function(r){return r.json()}).then(function(j){
    var slots = j.starts||[];
    $('slots').innerHTML = slots.length? '' : '<p style="color:var(--muted)">No times left that day — try another day.</p>';
    slots.forEach(function(s){
      var b = document.createElement('button'); b.className='slot'; b.textContent = fmtT(s);
      b.addEventListener('click', function(){
        $('slots').querySelectorAll('.slot').forEach(function(x){x.classList.remove('sel')});
        b.classList.add('sel'); selStart = s; $('doMove').disabled = false;
      });
      $('slots').appendChild(b);
    });
  });
}
fetch(API+'?action=manage_get&token='+encodeURIComponent(token)).then(function(r){return r.json()}).then(function(j){
  if(j.ok){ bk = j.booking; render(); } else render();
}).catch(function(){ render(); });
</script>
</body>
</html>
