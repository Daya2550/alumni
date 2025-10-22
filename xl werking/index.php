<?php
require_once 'excel_reader.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maharashtra Engineering Alumni Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-bar {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-box input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .alumni-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .alumni-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .alumni-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            margin-right: 1rem;
        }

        .profile-info h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .profile-info .position {
            color: #64748b;
            font-size: 0.9rem;
        }

        .card-content {
            margin-bottom: 1rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .info-row i {
            width: 20px;
            color: #667eea;
            margin-right: 0.5rem;
        }

        .skills {
            margin: 1rem 0;
        }

        .skills-label {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .skill-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .skill-tag {
            background: #f1f5f9;
            color: #475569;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .action-btn {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            color: #64748b;
            text-decoration: none;
            text-align: center;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: #f8fafc;
            border-color: #667eea;
            color: #667eea;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-decoration: none;
            color: #64748b;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .data-source {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .data-source i {
            color: #0ea5e9;
            margin-right: 0.5rem;
        }

        @media (max-width: 768px) {
            .alumni-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-bar {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-graduation-cap"></i> Maharashtra Engineering Alumni</h1>
            <p>Connect with fellow engineering graduates and explore career opportunities</p>
        </div>
    </div>

    <div class="container">
 

        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number"><?php echo number_format($totalCount); ?></div>
                <div class="stat-label">Total Alumni</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo count($filterOptions['colleges']); ?></div>
                <div class="stat-label">Colleges</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo count($filterOptions['companies']); ?></div>
                <div class="stat-label">Companies</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo count($filterOptions['locations']); ?></div>
                <div class="stat-label">Locations</div>
            </div>
        </div>

        <div class="filters-section">
            <form method="GET" id="filterForm">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search alumni by name, company, position, skills..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="college">College</label>
                        <select name="college" id="college">
                            <option value="">All Colleges</option>
                            <?php foreach ($filterOptions['colleges'] as $college): ?>
                                <option value="<?php echo htmlspecialchars($college); ?>" <?php echo ($_GET['college'] ?? '') == $college ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($college); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="company">Company</label>
                        <select name="company" id="company">
                            <option value="">All Companies</option>
                            <?php foreach ($filterOptions['companies'] as $company): ?>
                                <option value="<?php echo htmlspecialchars($company); ?>" <?php echo ($_GET['company'] ?? '') == $company ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="location">Location</label>
                        <select name="location" id="location">
                            <option value="">All Locations</option>
                            <?php foreach ($filterOptions['locations'] as $location): ?>
                                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo $filters['location'] == $location ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="experience_min">Min Experience (Years)</label>
                        <input type="number" name="experience_min" id="experience_min" 
                               value="<?php echo htmlspecialchars($filters['experience_min']); ?>" min="0" max="50" step="0.1">
                    </div>

                    <div class="filter-group">
                        <label for="experience_max">Max Experience (Years)</label>
                        <input type="number" name="experience_max" id="experience_max" 
                               value="<?php echo htmlspecialchars($filters['experience_max']); ?>" min="0" max="50" step="0.1">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <?php if (empty($alumni)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No alumni found</h3>
                <p>Try adjusting your search criteria or filters</p>
            </div>
        <?php else: ?>
            <div class="alumni-grid">
                <?php foreach ($alumni as $person): ?>
                    <div class="alumni-card">
                        <div class="card-header">
                            <div class="profile-avatar">
                                <?php echo strtoupper(substr($person['Name'] ?? 'A', 0, 2)); ?>
                            </div>
                            <div class="profile-info">
                                <h3><?php echo htmlspecialchars($person['Name'] ?? 'Name not available'); ?></h3>
                                <div class="position">
                                    <?php echo htmlspecialchars($person['Current Position'] ?? 'Position not specified'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-content">
                            <?php if (!empty($person['Company'])): ?>
                                <div class="info-row">
                                    <i class="fas fa-building"></i>
                                    <span><?php echo htmlspecialchars($person['Company']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($person['College'])): ?>
                                <div class="info-row">
                                    <i class="fas fa-university"></i>
                                    <span><?php echo htmlspecialchars($person['College']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($person['Location'])): ?>
                                <div class="info-row">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($person['Location']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($person['Experience (Years)'])): ?>
                                <div class="info-row">
                                    <i class="fas fa-briefcase"></i>
                                    <span><?php echo number_format($person['Experience (Years)'], 1); ?> years experience</span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($person['Skills'])): ?>
                                <div class="skills">
                                    <div class="skills-label">Skills</div>
                                    <div class="skill-tags">
                                        <?php 
                                        $skills = array_filter(array_map('trim', explode(',', $person['Skills'])));
                                        foreach (array_slice($skills, 0, 5) as $skill): 
                                        ?>
                                            <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($skills) > 5): ?>
                                            <span class="skill-tag">+<?php echo count($skills) - 5; ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-actions">
                            <?php if (!empty($person['LinkedIn URL'])): ?>
                                <a href="<?php echo htmlspecialchars($person['LinkedIn URL']); ?>" target="_blank" class="action-btn">
                                    <i class="fab fa-linkedin"></i> LinkedIn
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($person['Email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($person['Email']); ?>" class="action-btn">
                                    <i class="fas fa-envelope"></i> Email
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($person['GitHub Profile'])): ?>
                                <a href="<?php echo htmlspecialchars($person['GitHub Profile']); ?>" target="_blank" class="action-btn">
                                    <i class="fab fa-github"></i> GitHub
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Auto-submit form on filter change
        document.querySelectorAll('select, input[type="number"]').forEach(element => {
            element.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });

        // Search with debounce
        let searchTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });
    </script>
</body>
</html>
