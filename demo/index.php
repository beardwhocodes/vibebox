<?php
// Vibebox demo — served from a real Hetzner CX23 over the Cloudflare tunnel.
// Pulls live system stats from /proc; counts visits in WAL-mode SQLite.
// Lives at vibebox.beardwho.codes. Marketing surface for beardwho.codes.

$start = microtime(true);
header('Cache-Control: no-cache, must-revalidate');
header('X-Frame-Options: SAMEORIGIN');

$db = new SQLite3(getenv('VIBEBOX_DB_PATH') ?: '/srv/data/vibebox/app.db');
$db->busyTimeout(5000);
$db->exec('PRAGMA journal_mode=WAL;');
$db->exec('PRAGMA synchronous=NORMAL;');
$db->exec('CREATE TABLE IF NOT EXISTS visits(id INTEGER PRIMARY KEY, at TEXT NOT NULL)');

$route = trim($_GET['r'] ?? '', '/');

switch ($route) {
    case '':
        $db->exec("INSERT INTO visits(at) VALUES(datetime('now'))");
        $visits = (int) $db->querySingle('SELECT COUNT(*) FROM visits');
        $stats  = collect_stats();
        render_home($stats, $visits, round((microtime(true) - $start) * 1000, 1));
        break;

    case 'health':
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'time' => date('c')]);
        break;

    case 'stats.json':
        header('Content-Type: application/json');
        echo json_encode(['visits' => (int) $db->querySingle('SELECT COUNT(*) FROM visits')] + collect_stats());
        break;

    default:
        http_response_code(404);
        echo "<!doctype html><meta charset=utf-8><title>404</title>";
        echo "<body style='font:16px system-ui;background:#0a0a0a;color:#f5f5f5;margin:3rem auto;max-width:42rem;padding:0 1rem;'>";
        echo "<h1>404</h1><p>No route here. Try <a style='color:#ff6b35' href='/'>the home page</a>.</p>";
}

function collect_stats(): array {
    $uptime_s = (int) floatval(explode(' ', file_get_contents('/proc/uptime'))[0] ?? 0);
    $loadavg  = preg_split('/\s+/', trim(file_get_contents('/proc/loadavg') ?: '0 0 0'));

    $meminfo = file_get_contents('/proc/meminfo') ?: '';
    preg_match('/MemTotal:\s+(\d+)/',     $meminfo, $mt);
    preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $ma);
    $mem_total_kb = (int) ($mt[1] ?? 0);
    $mem_avail_kb = (int) ($ma[1] ?? 0);
    $mem_used_kb  = max(0, $mem_total_kb - $mem_avail_kb);

    $df = preg_split('/\s+/', shell_exec("df -k / | tail -1") ?: '');
    $disk_total_kb = (int) ($df[1] ?? 0);
    $disk_used_kb  = (int) ($df[2] ?? 0);

    $os = @parse_ini_file('/etc/os-release') ?: [];

    $cpuinfo = file_get_contents('/proc/cpuinfo') ?: '';
    preg_match_all('/^processor/m', $cpuinfo, $cpus);
    preg_match('/model name\s*:\s*(.+)/', $cpuinfo, $cpu_model);

    return [
        'os'             => trim((string) ($os['PRETTY_NAME'] ?? 'Linux')),
        'kernel'         => trim((string) (shell_exec('uname -r') ?: 'unknown')),
        'arch'           => php_uname('m'),
        'cpu_count'      => count($cpus[0] ?? []),
        'cpu_model'      => trim((string) ($cpu_model[1] ?? 'unknown')),
        'uptime_s'       => $uptime_s,
        'load_1m'        => (float) ($loadavg[0] ?? 0),
        'load_5m'        => (float) ($loadavg[1] ?? 0),
        'load_15m'       => (float) ($loadavg[2] ?? 0),
        'mem_total_mib'  => (int) round($mem_total_kb / 1024),
        'mem_used_mib'   => (int) round($mem_used_kb / 1024),
        'mem_pct'        => $mem_total_kb ? round($mem_used_kb / $mem_total_kb * 100, 1) : 0,
        'disk_total_gib' => round($disk_total_kb / 1024 / 1024, 1),
        'disk_used_gib'  => round($disk_used_kb / 1024 / 1024, 1),
        'disk_pct'       => $disk_total_kb ? round($disk_used_kb / $disk_total_kb * 100, 1) : 0,
        'php'            => PHP_VERSION,
        'sqlite'         => SQLite3::version()['versionString'] ?? 'unknown',
        'server'         => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'nginx'),
    ];
}

function fmt_uptime(int $s): string {
    $d = intdiv($s, 86400); $s %= 86400;
    $h = intdiv($s, 3600);  $s %= 3600;
    $m = intdiv($s, 60);
    if ($d > 0) return "{$d}d {$h}h {$m}m";
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function render_home(array $s, int $visits, float $gen_ms): void {
    $uptime = fmt_uptime($s['uptime_s']);
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Welcome to Vibebox · live demo</title>
<meta name="description" content="A live Vibebox demo. Real Hetzner box, real stack, real stats from /proc. Get your own at beardwho.codes.">
<meta property="og:title" content="Welcome to Vibebox · live demo">
<meta property="og:description" content="Served from a real Hetzner box configured by Vibebox. Live specs below.">
<meta property="og:url" content="https://vibebox.beardwho.codes">
<meta name="theme-color" content="#0a0a0a">
<link rel="icon" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📦</text></svg>">
<style>
:root {
  --bg:#0a0a0a; --fg:#f5f5f5; --muted:#9a9a9a; --line:#222;
  --accent:#ff6b35; --code-bg:#131313; --card-bg:#111; --max:760px;
}
* { box-sizing:border-box; }
body { margin:0; background:var(--bg); color:var(--fg); font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif; -webkit-font-smoothing:antialiased; }
main { max-width:var(--max); margin:0 auto; padding:3.5rem 1.5rem; }
h1 { font-size:clamp(2rem,5.5vw,3rem); line-height:1.1; margin:0 0 1rem; letter-spacing:-0.02em; }
h2 { font-size:1.5rem; margin:3.5rem 0 1rem; letter-spacing:-0.01em; }
p { margin:0 0 1rem; }
a { color:var(--accent); text-decoration:none; }
a:hover { text-decoration:underline; }
.muted { color:var(--muted); }
.tag { display:inline-block; padding:0.2rem 0.7rem; border:1px solid var(--line); border-radius:999px; font-size:0.85rem; color:var(--muted); margin-bottom:1.5rem; }
.lead { font-size:1.15rem; color:#cfcfcf; margin:1rem 0 2rem; }
.btn { display:inline-block; background:var(--accent); color:#0a0a0a; padding:0.85rem 1.5rem; border-radius:6px; font-weight:600; margin:0 0.5rem 0.5rem 0; }
.btn:hover { opacity:0.92; text-decoration:none; }
.btn.secondary { background:transparent; border:1px solid var(--line); color:var(--fg); }
code { font-family:ui-monospace,SF Mono,Menlo,monospace; font-size:0.85em; background:var(--code-bg); padding:0.1rem 0.4rem; border-radius:4px; }
.grid { display:grid; grid-template-columns:repeat(3,1fr); gap:0.75rem; margin-top:1rem; }
@media (max-width:600px) { .grid { grid-template-columns:repeat(2,1fr); } }
.card { background:var(--card-bg); border:1px solid var(--line); border-radius:8px; padding:1rem 1.1rem; }
.card .label { font-size:0.78rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.3rem; }
.card .value { font-size:1.3rem; font-weight:600; font-variant-numeric:tabular-nums; }
.card .sub { font-size:0.8rem; color:var(--muted); margin-top:0.3rem; font-variant-numeric:tabular-nums; }
.bar { height:4px; background:var(--line); border-radius:2px; overflow:hidden; margin-top:0.5rem; }
.bar > div { height:100%; background:var(--accent); }
table { width:100%; border-collapse:collapse; margin:1rem 0; }
td, th { padding:0.55rem 0.75rem; text-align:left; border-bottom:1px solid var(--line); }
th { font-weight:500; color:var(--muted); font-size:0.9rem; }
td:first-child { color:var(--muted); width:35%; }
.posture { background:var(--card-bg); border:1px solid var(--line); border-radius:8px; padding:1.25rem 1.5rem; margin-top:1rem; }
.posture li { margin-bottom:0.5rem; color:#cfcfcf; }
.posture li strong { color:var(--fg); }
.cta-block { margin:4rem 0 0; padding:2.5rem 1.5rem; background:var(--card-bg); border-radius:8px; border:1px solid var(--line); text-align:center; }
.cta-block h2 { margin:0 0 0.5rem; }
.cta-block p { color:var(--muted); margin-bottom:1.5rem; }
footer { margin-top:4rem; padding-top:2rem; border-top:1px solid var(--line); color:var(--muted); font-size:0.85rem; }
footer a { color:var(--muted); }
.dot { display:inline-block; width:0.5rem; height:0.5rem; border-radius:50%; background:#3ecf8e; margin-right:0.4rem; vertical-align:middle; }
</style>
</head>
<body>
<main>
  <header>
    <span class="tag"><span class="dot"></span>live · served from a real Hetzner Vibebox</span>
    <h1>You're on a Vibebox 📦</h1>
    <p class="lead">This page is served by a real Hetzner box configured by <a href="https://beardwho.codes">Vibebox</a>. Zero public inbound ports. Cloudflare outbound tunnel + Tailscale-only SSH + Claude Code on the box. The stats below come live from this exact machine.</p>
    <a href="https://beardwho.codes" class="btn">Get your own — $9.99</a>
    <a href="https://beardwho.codes#whats-inside" class="btn secondary">See what's inside</a>
  </header>

  <h2>Live specs</h2>
  <div class="grid">
    <div class="card">
      <div class="label">OS</div>
      <div class="value"><?= h($s['os']) ?></div>
      <div class="sub">kernel <?= h($s['kernel']) ?> · <?= h($s['arch']) ?></div>
    </div>
    <div class="card">
      <div class="label">CPU</div>
      <div class="value"><?= $s['cpu_count'] ?> × vCPU</div>
      <div class="sub">load <?= $s['load_1m'] ?> / <?= $s['load_5m'] ?> / <?= $s['load_15m'] ?></div>
    </div>
    <div class="card">
      <div class="label">Uptime</div>
      <div class="value"><?= h($uptime) ?></div>
      <div class="sub">since last reboot</div>
    </div>
    <div class="card">
      <div class="label">Memory</div>
      <div class="value"><?= number_format($s['mem_used_mib']) ?> / <?= number_format($s['mem_total_mib']) ?> MiB</div>
      <div class="bar"><div style="width:<?= $s['mem_pct'] ?>%"></div></div>
      <div class="sub"><?= $s['mem_pct'] ?>% used</div>
    </div>
    <div class="card">
      <div class="label">Disk (root)</div>
      <div class="value"><?= $s['disk_used_gib'] ?> / <?= $s['disk_total_gib'] ?> GiB</div>
      <div class="bar"><div style="width:<?= $s['disk_pct'] ?>%"></div></div>
      <div class="sub"><?= $s['disk_pct'] ?>% used</div>
    </div>
    <div class="card">
      <div class="label">Page render</div>
      <div class="value"><?= $gen_ms ?> ms</div>
      <div class="sub">PHP + SQLite, no cache</div>
    </div>
  </div>

  <h2>Stack on this box</h2>
  <table>
    <tr><td>Hardware</td><td>Hetzner <strong>CX23</strong> · EU-central (nbg1)</td></tr>
    <tr><td>OS</td><td><?= h($s['os']) ?> · kernel <?= h($s['kernel']) ?></td></tr>
    <tr><td>Web server</td><td><?= h($s['server']) ?> (listens on <code>127.0.0.1:80</code> only — no public bind)</td></tr>
    <tr><td>App runtime</td><td>PHP <?= h($s['php']) ?> · FPM</td></tr>
    <tr><td>Database</td><td>SQLite <?= h($s['sqlite']) ?> · WAL mode</td></tr>
    <tr><td>Public ingress</td><td>Cloudflare Tunnel (outbound) · zero inbound ports</td></tr>
    <tr><td>Admin access</td><td>Tailscale-only · SSH 22 firewalled to <code>100.64.0.0/10</code></td></tr>
    <tr><td>Brute-force</td><td>fail2ban on SSH</td></tr>
    <tr><td>Patching</td><td>unattended-upgrades · 04:00 auto-reboot</td></tr>
    <tr><td>Backups</td><td>3-2-1 plan: Hetzner snapshot + Litestream → R2 + B2 offsite</td></tr>
    <tr><td>AI agent</td><td>Claude Code installed at <code>/usr/bin/claude</code></td></tr>
  </table>

  <h2>Security posture</h2>
  <div class="posture">
    <ul style="margin:0; padding-left:1.4rem;">
      <li><strong>Public inbound ports:</strong> 0. Nothing listening for the internet.</li>
      <li><strong>Web traffic:</strong> arrives via Cloudflare's outbound tunnel — origin has no public web port.</li>
      <li><strong>SSH:</strong> only reachable over Tailscale (private mesh, your devices only).</li>
      <li><strong>TLS chain:</strong> browser → CF edge (Universal SSL) → encrypted tunnel → nginx on loopback.</li>
      <li><strong>Bypass-mode caveat:</strong> the kit is honest that <code>IS_SANDBOX=1</code> isn't a real sandbox — the disposable VPS + 3-2-1 backups are the actual safety boundary.</li>
    </ul>
  </div>

  <div class="cta-block">
    <h2 style="margin-top:0;">Same box, ready in ~5 minutes.</h2>
    <p>The Vibebox kit packages everything you see on this page — hardened, lockout-proof, field-validated. $9.99, one-time.</p>
    <a href="https://beardwho.codes#pricing" class="btn">See pricing →</a>
  </div>

  <footer>
    <p>Served from a Hetzner Vibebox · this render took <strong><?= $gen_ms ?> ms</strong> · <strong><?= number_format($visits) ?></strong> visits tracked · <a href="/?r=stats.json">stats.json</a> · <a href="/?r=health">health</a></p>
    <p><a href="https://beardwho.codes">beardwho.codes</a> · Inspired by <a href="https://x.com/levelsio">@levelsio</a>. Not affiliated.</p>
  </footer>
</main>
</body>
</html>
<?php }
