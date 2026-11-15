<?php
declare(strict_types=1);
$host = gethostname() ?: 'unknown';
$phpv = PHP_VERSION;
$now = gmdate('Y-m-d H:i:s') . ' UTC';
$env = (strpos($host, 'prod') !== false) ? 'PROD' : 'DEV';
$quotes = [
  ['symbol' => 'RHAT', 'name' => 'Red Hat Equities', 'bid' => 187.42, 'ask' => 187.55, 'chg' => 1.24],
  ['symbol' => 'FX-EUR', 'name' => 'EUR/USD Spot', 'bid' => 1.0841, 'ask' => 1.0843, 'chg' => -0.12],
  ['symbol' => 'OIL-W', 'name' => 'WTI Crude', 'bid' => 78.16, 'ask' => 78.22, 'chg' => 0.45],
  ['symbol' => 'IDX-S', 'name' => 'Synth Index', 'bid' => 4521.8, 'ask' => 4522.1, 'chg' => -0.08],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trading Service — <?= htmlspecialchars($env) ?></title>
  <style>
    :root {
      --bg: #0b1220;
      --panel: #121a2b;
      --line: #243049;
      --text: #e8eef9;
      --muted: #8fa0b8;
      --accent: #3ddc97;
      --warn: #f0b429;
      --bad: #ff6b6b;
      --brand: #5b9dff;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
      color: var(--text);
      background:
        radial-gradient(1200px 600px at 10% -10%, #1a2a4a 0%, transparent 55%),
        radial-gradient(900px 500px at 100% 0%, #13263f 0%, transparent 50%),
        var(--bg);
    }
    .wrap { max-width: 960px; margin: 0 auto; padding: 2.5rem 1.25rem 3rem; }
    .brand {
      font-family: "IBM Plex Serif", Georgia, serif;
      font-size: clamp(2rem, 5vw, 3rem);
      letter-spacing: -0.02em;
      margin: 0 0 0.35rem;
    }
    .tagline { color: var(--muted); margin: 0 0 1.75rem; font-size: 1.05rem; }
    .meta {
      display: flex; flex-wrap: wrap; gap: 0.6rem;
      margin-bottom: 1.5rem;
    }
    .pill {
      border: 1px solid var(--line);
      background: rgba(18, 26, 43, 0.85);
      border-radius: 999px;
      padding: 0.35rem 0.8rem;
      font-size: 0.85rem;
      color: var(--muted);
    }
    .pill strong { color: var(--text); font-weight: 600; }
    .pill.env-DEV strong { color: var(--warn); }
    .pill.env-PROD strong { color: var(--bad); }
    .panel {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 12px;
      overflow: hidden;
    }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 0.9rem 1rem; text-align: left; }
    th {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--muted);
      border-bottom: 1px solid var(--line);
    }
    td { border-bottom: 1px solid var(--line); font-variant-numeric: tabular-nums; }
    tr:last-child td { border-bottom: 0; }
    .sym { font-weight: 600; color: var(--brand); }
    .up { color: var(--accent); }
    .down { color: var(--bad); }
    .foot {
      margin-top: 1.25rem;
      color: var(--muted);
      font-size: 0.9rem;
    }
    a { color: var(--brand); }
  </style>
</head>
<body>
  <div class="wrap">
    <h1 class="brand">Trading Service</h1>
    <p class="tagline">Live market desk — equities, FX, and derivatives</p>
    <div class="meta">
      <span class="pill env-<?= htmlspecialchars($env) ?>">Environment: <strong><?= htmlspecialchars($env) ?></strong></span>
      <span class="pill">Host: <strong><?= htmlspecialchars($host) ?></strong></span>
      <span class="pill">PHP: <strong><?= htmlspecialchars($phpv) ?></strong></span>
      <span class="pill">Status: <strong style="color:var(--accent)">ONLINE</strong></span>
    </div>
    <div class="panel">
      <table>
        <thead>
          <tr>
            <th>Symbol</th>
            <th>Instrument</th>
            <th>Bid</th>
            <th>Ask</th>
            <th>Change %</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($quotes as $q): ?>
          <tr>
            <td class="sym"><?= htmlspecialchars($q['symbol']) ?></td>
            <td><?= htmlspecialchars($q['name']) ?></td>
            <td><?= number_format($q['bid'], 2) ?></td>
            <td><?= number_format($q['ask'], 2) ?></td>
            <td class="<?= $q['chg'] >= 0 ? 'up' : 'down' ?>">
              <?= ($q['chg'] >= 0 ? '+' : '') . number_format($q['chg'], 2) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="foot">
      Last refresh <?= htmlspecialchars($now) ?> ·
      <a href="/health.php">/health</a>
    </p>
  </div>
</body>
</html>
