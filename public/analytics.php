<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin or staff role
Auth::requireAnyRole(['admin', 'staff']);
$user = Auth::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Analytics Dashboard - <?php echo APP_NAME ?? 'Alumni Portal'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="analytics-assets/css/styles.css" />
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
    <style>
        /* Override some styles for better integration */
        .topbar {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        .content {
            padding: 0 15px;
        }
        .analytics-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .role-badge {
            position: absolute;
            top: 10px;
            right: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container" style="margin-top:10px">
    </div>

    <div class="analytics-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1">
                        <i class="fas fa-chart-bar me-2"></i>
                        Analytics Dashboard
                    </h1>
                    <p class="mb-0 opacity-75">Comprehensive data analysis and visualization</p>
                </div>
                <div class="col-auto">
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-user-shield me-1"></i>
                        <?php echo ucfirst($user['role']); ?> Access
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Analytics Dashboard Content -->
        <header class="topbar">
            <div class="brand">
                <h1>Management Analytics Dashboard</h1>
                <p class="subtitle">Explore tables, group metrics, and visualize instantly</p>
            </div>
            <div class="controls">
                <select id="presetSelect" title="Presets">
                    <option value="">Presets (alumni_portal)</option>
                </select>
                <select id="tableSelect"></select>
                <select id="groupBySelect" multiple title="Group By"></select>
                <select id="metricFuncSelect">
                    <option value="COUNT">COUNT</option>
                    <option value="SUM">SUM</option>
                    <option value="AVG">AVG</option>
                    <option value="MIN">MIN</option>
                    <option value="MAX">MAX</option>
                </select>
                <select id="metricColSelect"></select>
                <select id="chartTypeSelect" title="Chart Type">
                    <option value="table">Table</option>
                    <option value="bar">Bar</option>
                    <option value="line">Line</option>
                    <option value="pie">Pie</option>
                    <option value="donut">Donut</option>
                    <option value="area">Area</option>
                    <option value="stackedBar">Stacked Bar</option>
                    <option value="stackedArea">Stacked Area</option>
                    <option value="scatter">Scatter</option>
                    <option value="heatmap">Heatmap</option>
                </select>
                <label class="toggle"><input type="checkbox" id="rawMode" /> Raw rows</label>
                <select id="rawColumnsSelect" multiple title="Raw Columns" hidden></select>
                <label class="toggle" id="rawAllWrap" hidden><input type="checkbox" id="rawSelectAll" /> Select all</label>
                <input id="rawLimit" type="number" min="1" max="10000" value="200" title="Page size" hidden />
                <select id="rawSortCol" title="Sort by" hidden></select>
                <select id="rawSortDir" title="Direction" hidden>
                    <option value="asc">ASC</option>
                    <option value="desc">DESC</option>
                </select>
                <div id="filters" class="filters" hidden>
                    <div class="filter-row">
                        <select class="filter-col"></select>
                        <select class="filter-op">
                            <option value="eq">=</option>
                            <option value="neq">!=</option>
                            <option value="contains">contains</option>
                            <option value="gt">></option>
                            <option value="lt"><</option>
                            <option value="between">between</option>
                            <option value="isnull">is null</option>
                            <option value="notnull">is not null</option>
                        </select>
                        <input class="filter-val" placeholder="Value" />
                        <input class="filter-val2" placeholder="And" style="display:none" />
                        <button type="button" class="filter-add">+</button>
                    </div>
                </div>
                <button id="rawPrev" hidden>&lt;</button>
                <span id="rawPageInfo" class="muted" hidden>Page 1</span>
                <button id="rawNext" hidden>&gt;</button>
                <button id="refreshBtn">Run</button>
                <button id="exportCsvBtn" title="Export CSV">Export CSV</button>
                <button id="resetBtn" title="Reset all">Reset</button>
            </div>
        </header>

        <main class="content">
            <div id="alertBox" class="alert" hidden></div>
            <div id="loading" class="loading" hidden>
                <span class="spinner" aria-hidden="true"></span>
                <span>Loading…</span>
            </div>
            <div id="sqlBox" class="alert" hidden>
                <span id="sqlText"></span>
                <button id="copySql" style="margin-left:8px">Copy SQL</button>
            </div>
            <section class="kpis" id="kpiContainer"></section>
            <section class="visual">
                <div id="chart" class="chart"></div>
                <div id="table" class="table" hidden>
                    <table>
                        <thead id="tableHead"></thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Include the analytics JavaScript -->
    <script src="analytics-assets/js/app.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>