<!DOCTYPE html>
<html lang="en" data-vibe="synthwave">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VIBEBOX // ship the side project, not the firewall</title>
<meta name="description" content="A hardened, AI-ready Hetzner box in ~5 minutes. Tailscale-only SSH, Cloudflare tunnel, Claude Code on the box, Litestream ready for configuration, zero public ports. The @levelsio stack — debugged on real hardware.">
<meta property="og:title" content="VIBEBOX — ship the side project, not the firewall">
<meta property="og:description" content="Hardened, AI-ready Hetzner VPS in 5 minutes. ~$7/mo all-in. $9.99 one-time.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
/* ─────────────────────────  VIBE SYSTEM  ───────────────────────── */
:root{
  --bg:#0a0612; --bg2:#120a22; --ink:#f4ecff; --muted:#a392c9;
  --c1:#ff2e97; --c2:#2bd1ff; --c3:#8b5cff; --sun:#ffd23f;
  --grid:rgba(139,92,255,.16); --card:rgba(255,255,255,.03);
  --stroke:rgba(255,255,255,.10); --glow:1; --paper:0;
  --font:'Space Grotesk',system-ui,sans-serif; --mono:'Space Mono',ui-monospace,monospace;
}
[data-vibe="matrix"]{
  --bg:#000600; --bg2:#001a08; --ink:#c6ffd8; --muted:#3fae6b;
  --c1:#36ff8b; --c2:#9dff5e; --c3:#1cff6a; --sun:#bfff3f;
  --grid:rgba(54,255,139,.13); --card:rgba(54,255,139,.04);
  --stroke:rgba(54,255,139,.16);
}
[data-vibe="vapor"]{
  --bg:#160d2e; --bg2:#241048; --ink:#fff0fb; --muted:#c9a7e6;
  --c1:#ff6ad5; --c2:#8be9fd; --c3:#c084fc; --sun:#fef08a;
  --grid:rgba(255,106,213,.15); --card:rgba(255,255,255,.04);
  --stroke:rgba(255,255,255,.12);
}
[data-vibe="paper"]{
  --bg:#f3efe6; --bg2:#fffdf7; --ink:#15110c; --muted:#6b6256;
  --c1:#ff3b1f; --c2:#1f57ff; --c3:#111; --sun:#111;
  --grid:rgba(0,0,0,.07); --card:#fffdf7;
  --stroke:#15110c; --glow:0; --paper:1;
}

*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  background:var(--bg); color:var(--ink); font-family:var(--font);
  overflow-x:hidden; line-height:1.5; -webkit-font-smoothing:antialiased;
  transition:background .6s ease,color .6s ease;
}
a{color:inherit;text-decoration:none}
::selection{background:var(--c1);color:#fff}

/* aurora canvas + grain */
#aurora{position:fixed;inset:0;z-index:-3;width:100%;height:100%}
#grid{position:fixed;inset:0;z-index:-2;pointer-events:none;
  background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);
  background-size:54px 54px;mask-image:radial-gradient(ellipse 80% 70% at 50% 0%,#000 30%,transparent 75%);
  -webkit-mask-image:radial-gradient(ellipse 80% 70% at 50% 0%,#000 30%,transparent 75%);}
#grain{position:fixed;inset:0;z-index:-1;pointer-events:none;opacity:.05;mix-blend-mode:overlay;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");}

/* custom cursor */
#cursor{position:fixed;top:0;left:0;width:26px;height:26px;border-radius:50%;
  background:radial-gradient(circle,var(--c2),transparent 65%);pointer-events:none;z-index:9999;
  transform:translate(-50%,-50%);mix-blend-mode:screen;transition:width .2s,height .2s;will-change:transform}
@media (hover:none){#cursor{display:none}}

/* ─────────────────────────  BOOT OVERLAY  ───────────────────────── */
#boot{position:fixed;inset:0;z-index:1000;background:var(--bg);
  display:flex;align-items:center;justify-content:center;font-family:var(--mono);
  transition:opacity .6s ease,visibility .6s}
#boot.gone{opacity:0;visibility:hidden}
#boot::after{content:"";position:absolute;inset:0;pointer-events:none;
  background:repeating-linear-gradient(0deg,rgba(0,0,0,.25) 0 1px,transparent 1px 3px)}
.boot-win{width:min(620px,90vw);color:var(--c2);font-size:13px;line-height:1.8;
  text-shadow:0 0 8px currentColor}
.boot-win .ok{color:var(--c1)}
.boot-skip{position:absolute;top:18px;right:22px;color:var(--muted);font-size:12px;
  font-family:var(--mono);cursor:pointer;letter-spacing:.1em}
.boot-skip:hover{color:var(--ink)}

/* ─────────────────────────  NAV  ───────────────────────── */
nav{position:fixed;top:0;left:0;right:0;z-index:200;display:flex;align-items:center;
  justify-content:space-between;padding:18px clamp(16px,4vw,46px);
  backdrop-filter:blur(9px);background:linear-gradient(var(--bg),transparent)}
.brand{font-weight:700;font-size:19px;letter-spacing:.04em;display:flex;gap:8px;align-items:center}
.brand b{color:var(--c1)}
.nav-l{display:flex;align-items:center;gap:16px}
.chill{font-family:var(--mono);font-size:13px;color:var(--muted);letter-spacing:.02em;transition:color .25s;white-space:nowrap}
.chill:hover{color:var(--ink)}
@media(max-width:520px){.chill{display:none}}
.cube-mini{display:inline-block;animation:spin 7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.nav-r{display:flex;align-items:center;gap:14px}
.vibes{display:flex;gap:6px;padding:5px;border:1px solid var(--stroke);border-radius:40px;background:var(--card)}
.vibe{width:20px;height:20px;border-radius:50%;cursor:pointer;border:2px solid transparent;
  transition:transform .2s}
.vibe:hover{transform:scale(1.18)}
.vibe[data-on="1"]{border-color:var(--ink)}
.vibe.s{background:linear-gradient(135deg,#ff2e97,#2bd1ff)}
.vibe.m{background:linear-gradient(135deg,#36ff8b,#bfff3f)}
.vibe.v{background:linear-gradient(135deg,#ff6ad5,#8be9fd)}
.vibe.p{background:linear-gradient(135deg,#f3efe6,#999)}
@media(max-width:640px){.vibes{display:none}}

/* buttons */
.btn{position:relative;display:inline-flex;align-items:center;gap:9px;
  font-family:var(--mono);font-weight:700;font-size:15px;letter-spacing:.02em;
  padding:14px 26px;border-radius:50px;cursor:pointer;border:0;color:#0a0612;
  background:linear-gradient(135deg,var(--c1),var(--c3));
  box-shadow:calc(var(--glow)*0 + 0px) 0 0 0,0 0 calc(var(--glow)*34px) var(--c1);
  transition:transform .12s ease,box-shadow .3s;will-change:transform}
[data-vibe="paper"] .btn{border:2px solid var(--ink);box-shadow:5px 5px 0 var(--ink)}
.btn:hover{box-shadow:0 0 calc(var(--glow)*52px) var(--c1)}
[data-vibe="paper"] .btn:hover{box-shadow:7px 7px 0 var(--ink)}
.btn.sm{padding:10px 18px;font-size:13px}
.btn.ghost{background:transparent;color:var(--ink);border:1px solid var(--stroke);box-shadow:none}
[data-vibe="paper"] .btn.ghost{border:2px solid var(--ink);box-shadow:3px 3px 0 var(--ink);background:transparent}

/* ─────────────────────────  HERO  ───────────────────────── */
main{position:relative;z-index:1}
.hero{position:relative;min-height:100vh;display:flex;flex-direction:column;justify-content:center;
  padding:120px clamp(16px,5vw,80px) 60px;max-width:1280px;margin:0 auto}
.eyebrow{font-family:var(--mono);font-size:12.5px;letter-spacing:.22em;text-transform:uppercase;
  color:var(--muted);display:flex;align-items:center;gap:10px;margin-bottom:26px}
.pulse{width:9px;height:9px;border-radius:50%;background:var(--c2);
  box-shadow:0 0 0 0 var(--c2);animation:pls 2s infinite}
@keyframes pls{0%{box-shadow:0 0 0 0 var(--c2)}70%{box-shadow:0 0 0 11px transparent}100%{box-shadow:0 0 0 0 transparent}}
h1{font-size:clamp(44px,10.5vw,148px);line-height:.92;font-weight:700;letter-spacing:-.03em;
  margin-bottom:8px;text-transform:uppercase}
h1 .ln{display:block;overflow:hidden}
h1 .ch{display:inline-block;transform:translateY(110%);opacity:0;
  animation:rise .8s cubic-bezier(.2,.8,.2,1) forwards}
@keyframes rise{to{transform:translateY(0);opacity:1}}
.strike{position:relative;color:var(--muted)}
.strike .ch{color:var(--muted)}
.strike::after{content:"";position:absolute;left:-1%;top:54%;width:0;height:.08em;
  background:var(--c1);box-shadow:0 0 16px var(--c1);animation:slash .7s 1.1s forwards ease-out}
@keyframes slash{to{width:102%}}
.grad{background:linear-gradient(110deg,var(--c1),var(--sun),var(--c2));
  -webkit-background-clip:text;background-clip:text;color:transparent;
  background-size:200% auto;animation:flow 6s linear infinite}
@keyframes flow{to{background-position:200% center}}
.word{display:inline-block;white-space:nowrap}
.grad .ch{background:linear-gradient(110deg,var(--c1),var(--sun),var(--c2));background-size:200% auto;
  -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
  animation:rise .8s cubic-bezier(.2,.8,.2,1) forwards,flow 6s linear infinite}
.lead{max-width:620px;font-size:clamp(15px,1.9vw,19px);color:var(--muted);margin:28px 0 34px;line-height:1.6}
.lead b{color:var(--ink)}
.cta-row{display:flex;flex-wrap:wrap;gap:14px;align-items:center}
.tiny{font-family:var(--mono);font-size:12px;color:var(--muted);margin-top:20px}
.tiny a{color:var(--c2);border-bottom:1px solid currentColor}

/* floating cube */
.stage{position:absolute;right:clamp(10px,5vw,90px);top:50%;transform:translateY(-50%);
  width:230px;height:230px;perspective:900px;z-index:0;pointer-events:none}
@media(max-width:1000px){.stage{display:none}}
.cube{position:absolute;inset:0;transform-style:preserve-3d;animation:tumble 16s linear infinite}
@keyframes tumble{to{transform:rotateX(360deg) rotateY(360deg)}}
.face{position:absolute;width:230px;height:230px;display:flex;align-items:center;justify-content:center;
  font-family:var(--mono);font-weight:700;font-size:18px;letter-spacing:.05em;
  border:1.5px solid var(--c2);background:linear-gradient(135deg,rgba(43,209,255,.07),rgba(139,92,255,.07));
  box-shadow:inset 0 0 40px rgba(43,209,255,.18);color:var(--ink);text-align:center;backface-visibility:visible}
.face span{text-shadow:0 0 12px var(--c2)}
.f1{transform:translateZ(115px)} .f2{transform:rotateY(180deg) translateZ(115px)}
.f3{transform:rotateY(90deg) translateZ(115px)} .f4{transform:rotateY(-90deg) translateZ(115px)}
.f5{transform:rotateX(90deg) translateZ(115px)} .f6{transform:rotateX(-90deg) translateZ(115px)}

/* ─────────────────────────  MARQUEE  ───────────────────────── */
.marq{border-top:1px solid var(--stroke);border-bottom:1px solid var(--stroke);
  padding:18px 0;overflow:hidden;white-space:nowrap;background:var(--card)}
.marq .track{display:inline-block;animation:march 26s linear infinite;font-family:var(--mono);
  font-weight:700;font-size:clamp(18px,3vw,30px);letter-spacing:.04em;text-transform:uppercase}
.marq.r .track{animation-direction:reverse;animation-duration:32s}
.marq .track span{margin:0 26px;color:var(--ink)}
.marq .track i{color:var(--c1);font-style:normal}
@keyframes march{to{transform:translateX(-50%)}}

/* ─────────────────────────  SECTIONS  ───────────────────────── */
section.block{max-width:1180px;margin:0 auto;padding:clamp(70px,11vw,130px) clamp(16px,5vw,60px)}
.kicker{font-family:var(--mono);font-size:12.5px;letter-spacing:.2em;text-transform:uppercase;color:var(--c2);margin-bottom:14px}
h2{font-size:clamp(30px,6vw,68px);line-height:1;font-weight:700;letter-spacing:-.02em;text-transform:uppercase;margin-bottom:18px}
.reveal{opacity:0;transform:translateY(34px);transition:opacity .7s ease,transform .7s cubic-bezier(.2,.8,.2,1)}
.reveal.in{opacity:1;transform:none}

/* terminal */
.term{border:1px solid var(--stroke);border-radius:14px;overflow:hidden;background:#06030d;
  box-shadow:0 30px 80px rgba(0,0,0,.5);max-width:760px;margin:30px auto 0}
[data-vibe="paper"] .term{background:#15110c;box-shadow:8px 8px 0 var(--ink)}
.term-bar{display:flex;align-items:center;gap:8px;padding:12px 16px;background:rgba(255,255,255,.04);border-bottom:1px solid var(--stroke)}
.tdot{width:12px;height:12px;border-radius:50%}.r{background:#ff5f57}.y{background:#febc2e}.g{background:#28c840}
.term-bar .ttl{margin-left:10px;font-family:var(--mono);font-size:12px;color:var(--muted)}
.term-body{padding:22px;font-family:var(--mono);font-size:13.5px;line-height:1.85;min-height:330px;color:#cfe9ff}
[data-vibe="matrix"] .term-body{color:#9affc0}
.term-body .p{color:var(--c2)} .term-body .ar{color:var(--c3)} .term-body .ok{color:#28c840}
.term-body .warn{color:var(--sun)} .term-body .dead{color:var(--c1)}
.cur{display:inline-block;width:9px;height:16px;background:var(--c2);vertical-align:-2px;animation:blink 1s steps(1) infinite}
@keyframes blink{50%{opacity:0}}
.replay{margin:18px auto 0;display:block}

/* features */
.feat{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:18px;margin-top:40px}
.card{position:relative;padding:30px;border:1px solid var(--stroke);border-radius:16px;background:var(--card);
  overflow:hidden;transition:transform .25s ease,border-color .25s}
.card::before{content:"";position:absolute;width:380px;height:380px;border-radius:50%;
  left:var(--mx,50%);top:var(--my,50%);transform:translate(-50%,-50%);
  background:radial-gradient(circle,var(--c3),transparent 60%);opacity:0;transition:opacity .3s;pointer-events:none}
.card:hover{transform:translateY(-6px);border-color:var(--c2)}
.card:hover::before{opacity:calc(var(--glow)*.16)}
.card .ic{font-size:30px;margin-bottom:14px}
.card h3{font-size:19px;text-transform:uppercase;letter-spacing:.01em;margin-bottom:8px}
.card p{color:var(--muted);font-size:14.5px}
.card .no{position:absolute;top:18px;right:22px;font-family:var(--mono);font-size:12px;color:var(--muted)}

/* stats */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:20px;margin-top:30px;text-align:center}
.stat .n{font-size:clamp(40px,7vw,74px);font-weight:700;line-height:1;
  background:linear-gradient(120deg,var(--c1),var(--c2));-webkit-background-clip:text;background-clip:text;color:transparent}
[data-vibe="paper"] .stat .n{color:var(--c1);-webkit-text-fill-color:var(--c1)}
.stat .l{font-family:var(--mono);font-size:12.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.12em;margin-top:8px}

/* pricing */
.tiers{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:22px;margin-top:42px;max-width:820px}
.tier{position:relative;padding:34px;border:1px solid var(--stroke);border-radius:20px;background:var(--card)}
.tier.pro{border-color:var(--c1);box-shadow:0 0 calc(var(--glow)*60px) rgba(255,46,151,.22)}
[data-vibe="paper"] .tier{box-shadow:6px 6px 0 var(--ink)}
.tier .badge{position:absolute;top:-13px;left:30px;font-family:var(--mono);font-size:11px;font-weight:700;
  background:var(--c1);color:#0a0612;padding:4px 12px;border-radius:30px;letter-spacing:.1em}
.tier h3{font-size:15px;font-family:var(--mono);letter-spacing:.14em;text-transform:uppercase;color:var(--muted)}
.tier .price{font-size:clamp(46px,8vw,68px);font-weight:700;letter-spacing:-.03em;margin:6px 0 4px}
.tier .price small{font-size:15px;color:var(--muted);font-weight:400;font-family:var(--mono)}
.tier ul{list-style:none;margin:20px 0 26px;display:flex;flex-direction:column;gap:11px}
.tier li{font-size:14.5px;color:var(--muted);display:flex;gap:10px}
.tier li::before{content:"▸";color:var(--c2)}
.tier .btn{width:100%;justify-content:center}

/* closing */
.closing{text-align:center;padding:clamp(90px,16vw,180px) 20px}
.closing h2{font-size:clamp(34px,9vw,110px);margin:0 auto 30px;max-width:14ch}
footer{border-top:1px solid var(--stroke);padding:50px clamp(16px,5vw,60px);
  font-family:var(--mono);font-size:13px;color:var(--muted);max-width:1180px;margin:0 auto;
  display:flex;flex-wrap:wrap;gap:16px;justify-content:space-between}
footer a{color:var(--c2)}
.egg{font-size:11px;opacity:.6}

@media (prefers-reduced-motion:reduce){
  *{animation-duration:.001s!important;transition-duration:.001s!important}
  h1 .ch{transform:none;opacity:1}
}
</style>
</head>
<body>

<canvas id="aurora"></canvas>
<div id="grid"></div>
<div id="grain"></div>
<div id="cursor"></div>

<!-- BOOT -->
<div id="boot">
  <span class="boot-skip" id="bootSkip">[ skip ]</span>
  <div class="boot-win" id="bootWin"></div>
</div>

<!-- NAV -->
<nav>
  <div class="nav-l">
    <div class="brand"><span class="cube-mini">📦</span> VIBE<b>BOX</b></div>
    <a class="chill magnetic" href="https://beardwho.codes" title="back to the calm landing">← chill out</a>
  </div>
  <div class="nav-r">
    <div class="vibes" title="pick your vibe">
      <div class="vibe s" data-vibe="synthwave" data-on="1"></div>
      <div class="vibe m" data-vibe="matrix"></div>
      <div class="vibe v" data-vibe="vapor"></div>
      <div class="vibe p" data-vibe="paper"></div>
    </div>
    <a class="btn sm magnetic" href="#pricing">Ship it →</a>
  </div>
</nav>

<main>
<!-- HERO -->
<header class="hero">
  <div class="eyebrow"><span class="pulse"></span> v0.2 · field-validated on real hardware</div>
  <h1>
    <span class="ln" data-split>Ship the</span>
    <span class="ln grad" data-split>side project.</span>
    <span class="ln strike" data-split>Not the firewall.</span>
  </h1>
  <p class="lead">A hardened, AI-ready Hetzner box in <b>~5 minutes</b>. Tailscale-only SSH, a Cloudflare outbound tunnel, Claude Code <b>on the box</b>, real 3-2-1 backups, and <b>zero public ports</b>. The <a href="https://x.com/levelsio" style="color:var(--c2)">@levelsio</a> "vibe coding on a server" stack — packaged and debugged on real hardware.</p>
  <div class="cta-row">
    <button class="btn magnetic" id="deployBtn">DEPLOY YOURS — $9.99 🚀</button>
    <a class="btn ghost magnetic" href="#lockdown">watch it lock down ↓</a>
  </div>
  <div class="tiny">No public inbound ports. Ever. · <a href="https://beardwho.codes">full kit at beardwho.codes</a></div>

  <div class="stage" id="stage">
    <div class="cube" id="cube">
      <div class="face f1"><span>📦<br>VIBEBOX</span></div>
      <div class="face f2"><span>🔒<br>SSH</span></div>
      <div class="face f3"><span>🌀<br>TUNNEL</span></div>
      <div class="face f4"><span>🤖<br>CLAUDE</span></div>
      <div class="face f5"><span>💾<br>BACKUP</span></div>
      <div class="face f6"><span>⏱<br>DEADMAN</span></div>
    </div>
  </div>
</header>

<!-- MARQUEE -->
<div class="marq">
  <div class="track" id="m1"></div>
</div>

<!-- LOCKDOWN TERMINAL -->
<section class="block" id="lockdown">
  <div class="reveal">
    <div class="kicker">// one command, eleven phases</div>
    <h2>Watch a box<br>lock itself down.</h2>
    <p class="lead" style="margin-top:18px">Real phases from <code style="font-family:var(--mono);color:var(--c2)">vibebox.sh</code>. The deadman timer arms during firewall lockdown so you <b>physically cannot</b> lock yourself out. Hit replay — it never gets old.</p>
  </div>
  <div class="term reveal">
    <div class="term-bar"><span class="tdot r"></span><span class="tdot y"></span><span class="tdot g"></span><span class="ttl">root@vibebox-1 — bash vibebox.sh</span></div>
    <div class="term-body" id="term"></div>
  </div>
  <button class="btn ghost sm replay magnetic" id="replay">↻ replay lockdown</button>
</section>

<!-- MARQUEE 2 -->
<div class="marq r"><div class="track" id="m2"></div></div>

<!-- FEATURES -->
<section class="block">
  <div class="reveal"><div class="kicker">// what's in the box</div>
  <h2>Six things that<br>save your Saturday.</h2></div>
  <div class="feat">
    <div class="card reveal spot"><div class="no">01</div><div class="ic">🛑</div><h3>Zero public ports</h3><p>Inbound firewall 100% closed. SSH rides Tailscale, web rides a Cloudflare outbound tunnel. Nothing listens in.</p></div>
    <div class="card reveal spot"><div class="no">02</div><div class="ic">⏱</div><h3>Deadman timer</h3><p>During lockdown a timer auto-reverts the firewall in 10 min unless you confirm from a fresh Tailscale session. Lockout: impossible.</p></div>
    <div class="card reveal spot"><div class="no">03</div><div class="ic">🤖</div><h3>Claude Code, on the box</h3><p>Pre-installed at <code style="font-family:var(--mono)">/usr/bin/claude</code>. Vibe-code your app from your laptop or your phone over Tailscale.</p></div>
    <div class="card reveal spot"><div class="no">04</div><div class="ic">💾</div><h3>3-2-1 backups</h3><p>Litestream pre-wired for R2 / B2 / Hetzner snapshots. Your SQLite streams off-box continuously.</p></div>
    <div class="card reveal spot"><div class="no">05</div><div class="ic">⚡</div><h3>40-second provision</h3><p>Packer bakes a snapshot once (~3 min); Terraform spins a fresh hardened box from it in ~40 seconds.</p></div>
    <div class="card reveal spot"><div class="no">06</div><div class="ic">🪤</div><h3>13 traps, pre-debugged</h3><p>Every footgun — needrestart hangs, IPv6 CIDRs, SSH-key dedup, SSL ghosts — caught on real hardware and handled.</p></div>
  </div>
</section>

<!-- STATS -->
<section class="block">
  <div class="stats">
    <div class="stat reveal"><div class="n" data-count="0" data-suffix="">0</div><div class="l">public ports</div></div>
    <div class="stat reveal"><div class="n" data-count="5" data-suffix="min">5</div><div class="l">to ship</div></div>
    <div class="stat reveal"><div class="n" data-count="0" data-suffix="">0</div><div class="l">lockouts, ever</div></div>
    <div class="stat reveal"><div class="n" data-count="13" data-suffix="">13</div><div class="l">traps handled</div></div>
  </div>
</section>

<!-- PRICING -->
<section class="block" id="pricing">
  <div class="reveal"><div class="kicker">// one-time. lifetime updates.</div>
  <h2>Pay once.<br>Ship forever.</h2></div>
  <div class="tiers">
    <div class="tier reveal">
      <h3>Standard</h3>
      <div class="price">$9.99 <small>one-time</small></div>
      <ul><li>Everything in the kit</li><li>Lifetime private-repo access</li><li>All updates via <code style="font-family:var(--mono)">git pull</code></li><li>MIT-licensed scripts</li></ul>
      <a class="btn magnetic" href="https://buy.polar.sh/polar_cl_OUbO1OWCivAJUspHv71hnt09VtYDT6fIlveGC3VToUq">Buy Standard — $9.99</a>
    </div>
    <div class="tier pro reveal">
      <span class="badge">MOST SHIPPED</span>
      <h3>Pro</h3>
      <div class="price">$59.99 <small>one-time</small></div>
      <ul><li>Everything in Standard</li><li>Buyer Discord access</li><li>Priority bug-fix response (48h)</li><li>30 min async 1:1 setup help</li><li>Proactive breaking-change pings</li></ul>
      <a class="btn magnetic" href="https://buy.polar.sh/polar_cl_nciotrJddTKqhsNAw3Vs7sOMSX4pXeZwootbo3eCtE6">Buy Pro — $59.99</a>
    </div>
  </div>
  <p class="tiny" style="margin-top:24px">14-day refund if you can't make it work. I'll try to help you debug first.</p>
</section>

<!-- CLOSING -->
<section class="closing">
  <div class="reveal">
    <h2>Stop yak-shaving.<br><span class="grad">Start shipping.</span></h2>
    <button class="btn magnetic" id="shipBtn" style="font-size:18px;padding:18px 38px">GET VIBEBOX — $9.99 🎉</button>
  </div>
</section>

<footer>
  <div>VIBEBOX · inspired by <a href="https://x.com/levelsio">@levelsio</a>'s workflow. not affiliated. scripts MIT.</div>
  <div>want a personal AI agent instead? <a href="https://hermes.beardwho.codes">hermesbox ↗</a></div>
  <div class="egg" id="egg">psst — type "ship", or try ↑↑↓↓←→←→ba</div>
</footer>
</main>

<canvas id="confetti" style="position:fixed;inset:0;z-index:998;pointer-events:none"></canvas>

<script>
const RM = matchMedia('(prefers-reduced-motion:reduce)').matches;
const $ = s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];

/* ── VIBE SWITCHER ── */
const root = document.documentElement;
function setVibe(v){
  root.setAttribute('data-vibe',v);
  $$('.vibe').forEach(e=>e.dataset.on = e.dataset.vibe===v ? '1':'');
  try{localStorage.setItem('vibe',v)}catch(e){}
}
$$('.vibe').forEach(e=>e.addEventListener('click',()=>setVibe(e.dataset.vibe)));
try{const sv=localStorage.getItem('vibe'); if(sv) setVibe(sv);}catch(e){}

/* ── SPLIT HEADLINE INTO LETTERS ── */
$$('[data-split]').forEach((ln,li)=>{
  const words=ln.textContent.split(' '); ln.textContent=''; let idx=0;
  words.forEach((w,wi)=>{
    const wd=document.createElement('span'); wd.className='word';
    [...w].forEach((c)=>{
      const s=document.createElement('span'); s.className='ch'; s.textContent=c;
      s.style.animationDelay=(li*0.22 + idx*0.03)+'s'; idx++;
      s.addEventListener('mouseenter',()=>{s.style.transform='translateY(-10px) rotate('+(Math.random()*16-8)+'deg)';
        setTimeout(()=>s.style.transform='',260);});
      wd.appendChild(s);
    });
    ln.appendChild(wd);
    if(wi<words.length-1) ln.appendChild(document.createTextNode(' '));
  });
});

/* ── CUSTOM CURSOR ── */
const cur=$('#cursor'); let cx=innerWidth/2,cy=innerHeight/2,tx=cx,ty=cy;
addEventListener('mousemove',e=>{tx=e.clientX;ty=e.clientY});
(function loop(){cx+=(tx-cx)*.18;cy+=(ty-cy)*.18;cur.style.transform=`translate(${cx}px,${cy}px) translate(-50%,-50%)`;requestAnimationFrame(loop)})();
$$('a,button,.vibe').forEach(el=>{el.addEventListener('mouseenter',()=>{cur.style.width='46px';cur.style.height='46px'});
  el.addEventListener('mouseleave',()=>{cur.style.width='26px';cur.style.height='26px'})});

/* ── MAGNETIC BUTTONS ── */
if(!RM) $$('.magnetic').forEach(b=>{
  b.addEventListener('mousemove',e=>{const r=b.getBoundingClientRect();
    b.style.transform=`translate(${(e.clientX-r.left-r.width/2)*.3}px,${(e.clientY-r.top-r.height/2)*.4}px)`});
  b.addEventListener('mouseleave',()=>b.style.transform='');
});

/* ── CUBE MOUSE TILT ── */
const cube=$('#cube');
if(cube&&!RM) addEventListener('mousemove',e=>{
  const rx=(e.clientY/innerHeight-.5)*-40, ry=(e.clientX/innerWidth-.5)*40;
  cube.style.animationPlayState='paused';
  cube.style.transform=`rotateX(${rx}deg) rotateY(${ry}deg)`;
});

/* ── AURORA CANVAS ── */
const ac=$('#aurora'), actx=ac.getContext('2d'); let blobs=[];
function vibeColors(){const cs=getComputedStyle(root);return ['--c1','--c2','--c3'].map(v=>cs.getPropertyValue(v).trim())}
function sizeAurora(){ac.width=innerWidth;ac.height=innerHeight;}
function initBlobs(){blobs=[];for(let i=0;i<5;i++)blobs.push({x:Math.random()*ac.width,y:Math.random()*ac.height,
  r:160+Math.random()*220,a:Math.random()*6.28,s:.15+Math.random()*.3})}
sizeAurora();initBlobs();addEventListener('resize',()=>{sizeAurora();initBlobs()});
function drawAurora(t){
  actx.clearRect(0,0,ac.width,ac.height);
  const cols=vibeColors(), paper=getComputedStyle(root).getPropertyValue('--paper').trim()==='1';
  actx.globalAlpha = paper? .10 : .55;
  blobs.forEach((b,i)=>{
    b.a+=0.002*b.s; const x=b.x+Math.cos(b.a)*120, y=b.y+Math.sin(b.a*1.3)*120;
    const g=actx.createRadialGradient(x,y,0,x,y,b.r);
    g.addColorStop(0,cols[i%3]); g.addColorStop(1,'transparent');
    actx.fillStyle=g; actx.beginPath(); actx.arc(x,y,b.r,0,6.29); actx.fill();
  });
  actx.globalAlpha=1; requestAnimationFrame(drawAurora);
}
if(!RM) requestAnimationFrame(drawAurora); else {drawAurora.length;sizeAurora();}

/* ── MARQUEE CONTENT ── */
const words=['ZERO PUBLIC PORTS','DEADMAN TIMER','CLAUDE CODE ON THE BOX','3-2-1 BACKUPS','40-SECOND PROVISION','TAILSCALE-ONLY SSH','CLOUDFLARE TUNNEL','SHIP THE SIDE PROJECT'];
function fillMarq(el){let h='';for(let k=0;k<2;k++)words.forEach(w=>h+=`<span>${w}</span><i>✦</i>`);el.innerHTML=h;}
fillMarq($('#m1'));fillMarq($('#m2'));

/* ── REVEAL ON SCROLL ── */
const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}}),{threshold:.15});
$$('.reveal').forEach(e=>io.observe(e));

/* ── CARD SPOTLIGHT ── */
$$('.spot').forEach(c=>c.addEventListener('mousemove',e=>{const r=c.getBoundingClientRect();
  c.style.setProperty('--mx',(e.clientX-r.left)+'px');c.style.setProperty('--my',(e.clientY-r.top)+'px')}));

/* ── COUNT-UP STATS ── */
const cio=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){
  const el=e.target.querySelector('.n'), to=+el.dataset.count, sfx=el.dataset.suffix||''; let n=0;
  const step=()=>{n+=Math.max(1,Math.ceil(to/30));if(n>=to)n=to;el.textContent=n+sfx;if(n<to)requestAnimationFrame(step)};
  step(); cio.unobserve(e.target);
}}),{threshold:.5});
$$('.stat').forEach(s=>cio.observe(s));

/* ── TERMINAL TYPER ── */
const seq=[
  {t:'$ bash vibebox.sh',c:'p'},
  {t:'==> preflight ............... ok',c:'ar'},
  {t:'==> base_packages .......... ok',c:'ar'},
  {t:'==> ssh_harden (key-only) .. ok',c:'ar'},
  {t:'==> tailscale_up ........... 100.x reachable',c:'ar'},
  {t:'==> firewall: inbound 100% CLOSED',c:'warn'},
  {t:'    ⏱ deadman armed — auto-revert in 10:00',c:'dead'},
  {t:'==> cloudflared_tunnel ..... healthy',c:'ar'},
  {t:'==> fail2ban + auto_upgrades ok',c:'ar'},
  {t:'==> claude_code ............ /usr/bin/claude',c:'ar'},
  {t:'==> app_starter ............ /srv/app live',c:'ar'},
  {t:'==> backups (litestream) ... streaming',c:'ar'},
  {t:'$ ssh root@vibebox-1  # from a NEW session',c:'p'},
  {t:'==> confirm — deadman CANCELLED ✓',c:'ok'},
  {t:'',c:''},
  {t:'  ✓ SHIPPED. zero public ports. go build. 🚀',c:'ok'},
];
const term=$('#term'); let typing=false;
function runTerm(){
  if(typing)return; typing=true; term.innerHTML=''; let i=0;
  function line(){
    if(i>=seq.length){typing=false;return;}
    const o=seq[i++], div=document.createElement('div');
    if(o.c)div.className=o.c; term.appendChild(div);
    if(RM){div.textContent=o.t; if(i<seq.length)line(); else typing=false; return;}
    let j=0; const speed = o.t.length>4? 14 : 90;
    (function ch(){div.innerHTML=o.t.slice(0,j)+'<span class="cur"></span>';
      if(j++<o.t.length)setTimeout(ch,speed+Math.random()*18);
      else{div.textContent=o.t; setTimeout(line, o.t.includes('deadman armed')?420:90);}})();
  }
  line();
}
const tio=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){runTerm();tio.unobserve(e.target)}}),{threshold:.4});
tio.observe(term);
$('#replay').addEventListener('click',runTerm);

/* ── CONFETTI ── */
const cc=$('#confetti'), ctx=cc.getContext('2d'); let parts=[];
function sizeC(){cc.width=innerWidth;cc.height=innerHeight}sizeC();addEventListener('resize',sizeC);
function burst(x,y){const cols=vibeColors().concat(['#fff',getComputedStyle(root).getPropertyValue('--sun').trim()]);
  for(let i=0;i<140;i++)parts.push({x,y,vx:(Math.random()-.5)*15,vy:Math.random()*-15-4,
    g:.45+Math.random()*.3,r:4+Math.random()*7,rot:Math.random()*6,vr:(Math.random()-.5)*.5,
    col:cols[i%cols.length],life:90+Math.random()*40});}
(function cloop(){ctx.clearRect(0,0,cc.width,cc.height);
  parts=parts.filter(p=>p.life-->0);
  parts.forEach(p=>{p.vy+=p.g;p.x+=p.vx;p.y+=p.vy;p.vx*=.99;p.rot+=p.vr;
    ctx.save();ctx.translate(p.x,p.y);ctx.rotate(p.rot);ctx.fillStyle=p.col;ctx.globalAlpha=Math.min(1,p.life/40);
    ctx.fillRect(-p.r/2,-p.r/2,p.r,p.r*.6);ctx.restore();});
  requestAnimationFrame(cloop)})();
function celebrate(e){const x=e?e.clientX:innerWidth/2,y=e?e.clientY:innerHeight/2;burst(x,y);
  setTimeout(()=>burst(innerWidth*.3,innerHeight*.5),120);setTimeout(()=>burst(innerWidth*.7,innerHeight*.5),240);}
$('#deployBtn').addEventListener('click',e=>{celebrate(e);setTimeout(()=>location.href='#pricing',500)});
$('#shipBtn').addEventListener('click',e=>{celebrate(e);
  setTimeout(()=>location.href='#pricing',650)});

/* ── BOOT SEQUENCE ── */
const boot=$('#boot'), bw=$('#bootWin');
const bootLines=['vibebox bootloader v0.2','> mounting /srv/app ............ ok','> tailscale ................... up','> firewall .................... inbound CLOSED','> deadman ..................... armed','> claude code ................. ready','> <span class="ok">● SHIPPED — entering vibe mode</span>'];
function killBoot(){boot.classList.add('gone');setTimeout(()=>boot.style.display='none',650)}
$('#bootSkip').addEventListener('click',killBoot); boot.addEventListener('click',killBoot);
if(RM){killBoot();}
else{let i=0;(function bl(){if(i>=bootLines.length){setTimeout(killBoot,650);return;}
  bw.innerHTML+=bootLines[i++]+'<br>';setTimeout(bl,260+Math.random()*160)})();}

/* ── EASTER EGGS ── */
let buf='';const KON='ArrowUp,ArrowUp,ArrowDown,ArrowDown,ArrowLeft,ArrowRight,ArrowLeft,ArrowRight,b,a';let kon=[];
addEventListener('keydown',e=>{
  buf=(buf+e.key.toLowerCase()).slice(-4);
  if(buf.endsWith('ship')){celebrate();}
  kon.push(e.key);kon=kon.slice(-10);
  if(kon.join(',')===KON){ // rainbow secret vibe
    root.style.setProperty('--c1','#ff2e97');root.style.setProperty('--c2','#2bd1ff');root.style.setProperty('--c3','#36ff8b');
    document.body.style.animation='flow 2s linear infinite';celebrate();
    $('#egg').textContent='🌈 TURBO VIBE UNLOCKED';
  }
});
</script>
</body>
</html>
