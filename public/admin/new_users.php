<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['admin','staff']);

// Filters
$range = $_GET['range'] ?? 'week'; // day|week|month|custom
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where = ['1=1'];
$params = [];

if ($range === 'day') { $where[] = 'DATE(created_at) = CURDATE()'; }
elseif ($range === 'week') { $where[] = 'created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'; }
elseif ($range === 'month') { $where[] = 'created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'; }
elseif ($range === 'custom' && $from && $to) { $where[] = 'DATE(created_at) BETWEEN ? AND ?'; $params[] = $from; $params[] = $to; }

if ($status !== '') { $where[] = 'is_active = ?'; $params[] = ($status === 'valid' ? 1 : 0); }

if ($search) { $where[] = '(name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$whereSql = 'WHERE ' . implode(' AND ', $where);

$users = db()->fetchAll("SELECT id, name, email, role, batch, profile_photo, created_at, is_active FROM users $whereSql ORDER BY created_at DESC LIMIT 200", $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>New Users - Admin</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h4 mb-0"><i class="fas fa-user-plus text-primary"></i> Newly Joined Users</h1>
			<a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-tachometer-alt"></i> Admin Home</a>
		</div>
		<div class="card mb-3">
			<div class="card-body">
				<form class="row g-2">
					<div class="col-md-2">
						<label class="form-label">Range</label>
						<select class="form-select" name="range">
							<option value="day" <?= $range==='day'?'selected':''; ?>>Today</option>
							<option value="week" <?= $range==='week'?'selected':''; ?>>This Week</option>
							<option value="month" <?= $range==='month'?'selected':''; ?>>Last 30 Days</option>
							<option value="custom" <?= $range==='custom'?'selected':''; ?>>Custom</option>
						</select>
					</div>
					<div class="col-md-3">
						<label class="form-label">From</label>
						<input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from); ?>">
					</div>
					<div class="col-md-3">
						<label class="form-label">To</label>
						<input type="date" class="form-control" name="to" value="<?= htmlspecialchars($to); ?>">
					</div>
					<div class="col-md-2">
						<label class="form-label">Status</label>
						<select class="form-select" name="status">
							<option value="">All</option>
							<option value="valid" <?= $status==='valid'?'selected':''; ?>>Valid</option>
							<option value="invalid" <?= $status==='invalid'?'selected':''; ?>>Not Valid</option>
						</select>
					</div>
					<div class="col-md-2">
						<label class="form-label">Search</label>
						<input class="form-control" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Name or email">
					</div>
					<div class="col-12 text-end">
						<button class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
					</div>
				</form>
			</div>
		</div>

		<div class="card">
			<div class="card-body table-responsive">
				<table class="table align-middle">
					<thead>
						<tr>
							<th>User</th><th>Role</th><th>Batch</th><th>Joined</th><th>Status</th><th class="text-end">Actions</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach($users as $u): ?>
						<tr>
							<td>
								<div class="d-flex align-items-center">
									<?php if ($u['profile_photo']): ?>
										<img src="<?= htmlspecialchars($u['profile_photo']); ?>" class="rounded-circle me-2" width="36" height="36" style="object-fit:cover;">
									<?php else: ?>
										<i class="fas fa-user-circle fa-2x text-muted me-2"></i>
									<?php endif; ?>
									<div>
										<div class="fw-bold"><?= htmlspecialchars($u['name']); ?></div>
										<small class="text-muted"><?= htmlspecialchars($u['email']); ?></small>
									</div>
								</div>
							</td>
							<td><?= htmlspecialchars(ucfirst($u['role'])); ?></td>
							<td><?= htmlspecialchars($u['batch'] ?? '—'); ?></td>
							<td><?= date('M j, Y', strtotime($u['created_at'])); ?></td>
							<td>
								<span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary'; ?>"><?= $u['is_active'] ? 'Valid' : 'Not Valid'; ?></span>
							</td>
							<td class="text-end">
								<div class="btn-group btn-group-sm">
									<a href="../profile.php?id=<?= urlencode($u['id']); ?>" class="btn btn-outline-primary"><i class="fas fa-eye"></i></a>
									<button class="btn btn-outline-success" onclick="setValid('<?= $u['id']; ?>', 1)"><i class="fas fa-check"></i></button>
									<button class="btn btn-outline-warning" onclick="setValid('<?= $u['id']; ?>', 0)"><i class="fas fa-ban"></i></button>
									<button class="btn btn-outline-danger" onclick="deleteUser('<?= $u['id']; ?>')"><i class="fas fa-trash"></i></button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script>
	async function setValid(userId, isActive){
		if(!confirm('Mark this account as ' + (isActive? 'Valid' : 'Not Valid') + '?')) return;
		try{
			const res = await fetch('../api/user_set_active.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'user_id='+encodeURIComponent(userId)+'&is_active='+(isActive?1:0)});
			const data = await res.json();
			if(data.success){ location.reload(); } else { alert(data.message||'Failed'); }
		}catch(e){ alert('Network error'); }
	}
	async function deleteUser(userId){
		if(!confirm('Delete this account permanently?')) return;
		try{
			const res = await fetch('../api/user_delete.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'target_id='+encodeURIComponent(userId)});
			const data = await res.json();
			if(data.success){ location.reload(); } else { alert(data.message||'Failed'); }
		}catch(e){ alert('Network error'); }
	}
	</script>
</body>
</html>






