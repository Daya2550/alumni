<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../xl werking/excel_reader.php';

Auth::requireAnyRole(['admin', 'staff']);

// Get current user
$current_user = Auth::getCurrentUser();

// Initialize Excel reader
$reader = new ExcelReader();

// Read data from Excel file
$allData = $reader->readExcelFile(__DIR__ . '/../xl werking/Maharashtra_Engineering_Alumni.xlsx');

// Get parameters for filtering and pagination
$search = $_GET['search'] ?? '';
$filters = [
    'graduation_year' => $_GET['graduation_year'] ?? '',
    'degree' => $_GET['degree'] ?? '',
    'specialization' => $_GET['specialization'] ?? '',
    'location' => $_GET['location'] ?? '',
    'college' => $_GET['college'] ?? '',
    'company' => $_GET['company'] ?? '',
    'experience_min' => $_GET['experience_min'] ?? '',
    'experience_max' => $_GET['experience_max'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;

// Filter and paginate data
$filteredData = $reader->filterData($allData, $filters, $search);
$totalCount = count($filteredData);
$totalPages = ceil($totalCount / $limit);
$alumni = $reader->paginateData($filteredData, $page, $limit);
$filterOptions = $reader->getFilterOptions($allData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Data Viewer - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .alumni-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .alumni-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .profile-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
        }
        .skill-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin: 0.125rem;
            display: inline-block;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .data-source-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 0 8px 8px 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><i class="fas fa-file-excel text-success"></i> Excel Data Viewer</h1>
                <p class="text-muted mb-0">View and analyze Maharashtra Engineering Alumni data from Excel file</p>
            </div>
            <div>
                <a href="admin-dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Data Source Info -->
        <div class="data-source-info">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle text-primary me-2"></i>
                <div>
                    <strong>Data Source:</strong> Maharashtra Engineering Alumni Excel File
                    <br>
                    <small class="text-muted">Last updated: <?php echo date('Y-m-d H:i:s', filemtime(__DIR__ . '/../xl werking/Maharashtra_Engineering_Alumni.xlsx')); ?></small>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo number_format($totalCount); ?></h4>
                        <small>Total Alumni</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-university fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo count($filterOptions['colleges']); ?></h4>
                        <small>Colleges</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-building fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo count($filterOptions['companies']); ?></h4>
                        <small>Companies</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-map-marker-alt fa-2x mb-2"></i>
                        <h4 class="mb-0"><?php echo count($filterOptions['locations']); ?></h4>
                        <small>Locations</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <h5 class="mb-3"><i class="fas fa-filter"></i> Filters & Search</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by name, company, skills...">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">College</label>
                    <select class="form-select" name="college">
                        <option value="">All Colleges</option>
                        <?php foreach ($filterOptions['colleges'] as $college): ?>
                            <option value="<?php echo htmlspecialchars($college); ?>" <?php echo $filters['college'] == $college ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($college); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Company</label>
                    <select class="form-select" name="company">
                        <option value="">All Companies</option>
                        <?php foreach ($filterOptions['companies'] as $company): ?>
                            <option value="<?php echo htmlspecialchars($company); ?>" <?php echo $filters['company'] == $company ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Location</label>
                    <select class="form-select" name="location">
                        <option value="">All Locations</option>
                        <?php foreach ($filterOptions['locations'] as $location): ?>
                            <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $filters['location'] == $location ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($location); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Experience (Years)</label>
                    <div class="row">
                        <div class="col-6">
                            <input type="number" class="form-control" name="experience_min" placeholder="Min" 
                                   value="<?php echo htmlspecialchars($filters['experience_min']); ?>" min="0" max="50">
                        </div>
                        <div class="col-6">
                            <input type="number" class="form-control" name="experience_max" placeholder="Max" 
                                   value="<?php echo htmlspecialchars($filters['experience_max']); ?>" min="0" max="50">
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="excel-viewer.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Alumni Grid -->
        <?php if (empty($alumni)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>No alumni found</h4>
                <p class="text-muted">Try adjusting your search criteria or filters</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($alumni as $person): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card alumni-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="profile-avatar me-3">
                                        <?php echo strtoupper(substr($person['Name'] ?? 'A', 0, 2)); ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($person['Name'] ?? 'Name not available'); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($person['Current Position'] ?? 'Position not specified'); ?></small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <?php if (!empty($person['Company'])): ?>
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="fas fa-building text-primary me-2"></i>
                                            <small><?php echo htmlspecialchars($person['Company']); ?></small>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($person['College'])): ?>
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="fas fa-university text-info me-2"></i>
                                            <small><?php echo htmlspecialchars($person['College']); ?></small>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($person['Location'])): ?>
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="fas fa-map-marker-alt text-success me-2"></i>
                                            <small><?php echo htmlspecialchars($person['Location']); ?></small>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($person['Experience (Years)'])): ?>
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="fas fa-briefcase text-warning me-2"></i>
                                            <small><?php echo number_format($person['Experience (Years)'], 1); ?> years experience</small>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($person['Skills'])): ?>
                                    <div class="mb-3">
                                        <small class="text-muted d-block mb-2">Skills:</small>
                                        <div>
                                            <?php 
                                            $skills = array_filter(array_map('trim', explode(',', $person['Skills'])));
                                            foreach (array_slice($skills, 0, 4) as $skill): 
                                            ?>
                                                <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($skills) > 4): ?>
                                                <span class="skill-tag">+<?php echo count($skills) - 4; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex gap-2">
                                    <?php if (!empty($person['LinkedIn URL'])): ?>
                                        <a href="<?php echo htmlspecialchars($person['LinkedIn URL']); ?>" target="_blank" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-linkedin"></i> LinkedIn
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($person['Email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($person['Email']); ?>" 
                                           class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-envelope"></i> Email
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($person['GitHub Profile'])): ?>
                                        <a href="<?php echo htmlspecialchars($person['GitHub Profile']); ?>" target="_blank" 
                                           class="btn btn-outline-dark btn-sm">
                                            <i class="fab fa-github"></i> GitHub
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Alumni pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form on filter change
        document.querySelectorAll('select, input[type="number"]').forEach(element => {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Search with debounce
        let searchTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>
</body>
</html>

