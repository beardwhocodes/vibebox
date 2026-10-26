<?php
// vibebox starter — single-file PHP + SQLite, no framework, no build step.
// The levelsio pattern: a switch-router monolith. Add cases as you grow.

$db = new SQLite3(getenv('VIBEBOX_DB_PATH') ?: '/srv/data/vibebox/app.db');
$db->busyTimeout(5000);
$db->exec('PRAGMA journal_mode=WAL;');          // concurrent reads while writing
$db->exec('PRAGMA synchronous=NORMAL;');
$db->exec('CREATE TABLE IF NOT EXISTS visits(id INTEGER PRIMARY KEY, at TEXT NOT NULL)');

$route = trim($_GET['r'] ?? '', '/');

switch ($route) {
    case '':                                     // home
        $db->exec("INSERT INTO visits(at) VALUES(datetime('now'))");
        $n = $db->querySingle('SELECT COUNT(*) FROM visits');
        page("It works 🚀", "<p>Visit #$n.</p>
            <p>Edit <code>" . __FILE__ . "</code> and refresh — no build, no deploy.</p>
            <p><a href='?r=health'>health</a></p>");
        break;

    case 'health':                               // simple JSON endpoint
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'time' => date('c')]);
        break;

    default:
        http_response_code(404);
        page("Not found", "<p>No route <code>" . htmlspecialchars($route) . "</code>.</p>");
}

function page(string $title, string $body): void {
    echo "<!doctype html><meta charset=utf-8>";
    echo "<meta name=viewport content='width=device-width,initial-scale=1'>";
    echo "<title>" . htmlspecialchars($title) . "</title>";
    echo "<style>body{font:16px/1.5 system-ui;margin:3rem auto;max-width:42rem;padding:0 1rem}</style>";
    echo "<h1>" . htmlspecialchars($title) . "</h1>$body";
}
