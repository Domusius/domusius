<?php
// admin/pages/log.php

require_once __DIR__ . '/log_admin_action.php';

// Fjern evt. dobbeltlogning her, hvis du har tilføjet det til admin_panel.php

// Filters
$page  = max(1, (int)($_GET['p'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$filterAdmin  = trim($_GET['admin'] ?? '');
$filterAction = trim($_GET['action'] ?? '');
$filterFrom   = trim($_GET['from'] ?? '');
$filterTo     = trim($_GET['to'] ?? '');
$export       = ($_GET['export'] ?? '') === 'csv';

$where = [];
$params = [];

if ($filterAdmin !== '') {
    $where[] = 'u.username LIKE :admin';
    $params[':admin'] = "%" . $filterAdmin . "%";
}

if ($filterAction !== '') {
    $where[] = 'l.action LIKE :action';
    $params[':action'] = "%" . $filterAction . "%";
}

if ($filterFrom !== '') {
    $where[] = 'l.created_at >= :from';
    $params[':from'] = $filterFrom . ' 00:00:00';
}

if ($filterTo !== '') {
    $where[] = 'l.created_at <= :to';
    $params[':to'] = $filterTo . ' 23:59:59';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// If export requested, output CSV
if ($export) {
    $sql = "SELECT l.created_at, u.username, l.action, l.details, l.ip_address
            FROM admin_logs l
            LEFT JOIN users u ON u.id = l.admin_id
            $whereSql
            ORDER BY l.created_at DESC";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admin_logs.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['created_at','admin','action','details','ip_address']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['created_at'], $r['username'] ?? 'Ukendt', $r['action'], $r['details'], $r['ip_address']]);
    }
    fclose($out);
    exit;
}

// Normal paginated view
$countSql = "SELECT COUNT(*) FROM admin_logs l LEFT JOIN users u ON u.id = l.admin_id $whereSql";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$pages = max(1, ceil($total / $limit));

$sql = "SELECT l.created_at, l.action, l.details, l.ip_address, u.username
        FROM admin_logs l
        LEFT JOIN users u ON u.id = l.admin_id
        $whereSql
        ORDER BY l.created_at DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .log-filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
        margin-bottom: 20px;
    }
    .log-filter-form label {
        display: flex;
        flex-direction: column;
        font-size: 0.9rem;
        font-weight: bold;
    }
    .log-filter-form input {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 5px;
    }
    .button-group {
        display: flex;
        gap: 10px;
    }
    .btn-search {
        background-color: #007bff;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 4px;
        cursor: pointer;
    }
    .btn-export {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 4px;
        cursor: pointer;
    }
    .btn-search:hover { background-color: #0056b3; }
    .btn-export:hover { background-color: #218838; }
</style>

<h1>📊 Admin log</h1>

<div class="content-card">
    <form method="get" class="log-filter-form">
        <input type="hidden" name="side" value="admin">
        <input type="hidden" name="tab" value="log">
        
        <label>Admin: <input name="admin" value="<?= htmlspecialchars($filterAdmin) ?>"></label>
        <label>Handling: <input name="action" value="<?= htmlspecialchars($filterAction) ?>"></label>
        <label>Fra: <input type="date" name="from" value="<?= htmlspecialchars($filterFrom) ?>"></label>
        <label>Til: <input type="date" name="to" value="<?= htmlspecialchars($filterTo) ?>"></label>
        
        <div class="button-group">
            <button type="submit" class="btn-search">Søg</button>
            <button type="submit" name="export" value="csv" class="btn-export">Eksportér CSV</button>
        </div>
    </form>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Dato</th>
                <th>Admin</th>
                <th>Handling</th>
                <th>Detaljer</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                    <td><?= htmlspecialchars($log['username'] ?? 'Ukendt') ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= htmlspecialchars($log['details']) ?></td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination" style="margin-top:20px;">
        <?php if ($page > 1): ?>
            <a href="?side=admin&tab=log&p=<?= $page - 1 ?>" class="btn-secondary">← Forrige</a>
        <?php endif; ?>

        <span style="margin: 0 15px;">Side <?= $page ?> af <?= $pages ?></span>

        <?php if ($page < $pages): ?>
            <a href="?side=admin&tab=log&p=<?= $page + 1 ?>" class="btn-secondary">Næste →</a>
        <?php endif; ?>
    </div>
</div>