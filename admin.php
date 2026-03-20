<?php
require_once 'config.php';

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    redirect('admin.php');
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin'] = $admin['id'];
        redirect('admin.php');
    } else {
        $error = "Invalid username or password";
    }
}

// If not logged in, show login form
if (!isAdmin()) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Admin Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5" style="max-width:400px;">
            <h2 class="text-center">Admin Login</h2>
            <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <form method="post">
                <div class="mb-3">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle admin actions (add, edit, delete work; upload resume)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add work
    if (isset($_POST['add_work'])) {
        $category_id = (int)$_POST['category_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . 'works/' . $image);
        }
        $stmt = $pdo->prepare("INSERT INTO works (category_id, title, description, image) VALUES (?, ?, ?, ?)");
        $stmt->execute([$category_id, $title, $description, $image]);
        $_SESSION['flash'] = "Work added successfully.";
        redirect('admin.php?page=works');
    }

    // Edit work
    if (isset($_POST['edit_work'])) {
        $id = (int)$_POST['id'];
        $category_id = (int)$_POST['category_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $image = $_POST['existing_image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Delete old image
            if ($image && file_exists(UPLOAD_DIR . 'works/' . $image)) {
                unlink(UPLOAD_DIR . 'works/' . $image);
            }
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . 'works/' . $image);
        }
        $stmt = $pdo->prepare("UPDATE works SET category_id=?, title=?, description=?, image=? WHERE id=?");
        $stmt->execute([$category_id, $title, $description, $image, $id]);
        $_SESSION['flash'] = "Work updated.";
        redirect('admin.php?page=works');
    }

    // Upload resume
    if (isset($_POST['upload_resume'])) {
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                // Delete old resume
                if (file_exists(UPLOAD_DIR . 'resume.pdf')) {
                    unlink(UPLOAD_DIR . 'resume.pdf');
                }
                move_uploaded_file($_FILES['resume']['tmp_name'], UPLOAD_DIR . 'resume.pdf');
                $_SESSION['flash'] = "Resume updated.";
            } else {
                $_SESSION['error'] = "Only PDF files allowed.";
            }
        }
        redirect('admin.php?page=resume');
    }
}

// Handle delete work
if (isset($_GET['delete_work'])) {
    $id = (int)$_GET['delete_work'];
    // Get image to delete
    $stmt = $pdo->prepare("SELECT image FROM works WHERE id = ?");
    $stmt->execute([$id]);
    $work = $stmt->fetch();
    if ($work && $work['image'] && file_exists(UPLOAD_DIR . 'works/' . $work['image'])) {
        unlink(UPLOAD_DIR . 'works/' . $work['image']);
    }
    $pdo->prepare("DELETE FROM works WHERE id = ?")->execute([$id]);
    $_SESSION['flash'] = "Work deleted.";
    redirect('admin.php?page=works');
}

// Determine admin page
$page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin.php">Admin Panel</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="admin.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin.php?page=works">Manage Works</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin.php?page=resume">Resume</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin.php?page=users">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin.php?page=ratings">Ratings</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin.php?page=contacts">Contacts</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin.php?page=stats">Stats</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin.php?logout=1">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-success"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if ($page === 'dashboard'): ?>
            <h2>Dashboard</h2>
            <?php
            $totalVisitors = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_stats")->fetchColumn();
            $totalWorks = $pdo->query("SELECT COUNT(*) FROM works")->fetchColumn();
            $totalRatings = $pdo->query("SELECT COUNT(*) FROM work_ratings")->fetchColumn();
            $avgOverall = getOverallRating($pdo);
            ?>
            <div class="row">
                <div class="col-md-3"><div class="card text-white bg-primary"><div class="card-body"><h5>Visitors</h5><h2><?= $totalVisitors ?></h2></div></div></div>
                <div class="col-md-3"><div class="card text-white bg-success"><div class="card-body"><h5>Works</h5><h2><?= $totalWorks ?></h2></div></div></div>
                <div class="col-md-3"><div class="card text-white bg-warning"><div class="card-body"><h5>Ratings</h5><h2><?= $totalRatings ?></h2></div></div></div>
                <div class="col-md-3"><div class="card text-white bg-info"><div class="card-body"><h5>Overall Rating</h5><h2><?= $avgOverall ?></h2></div></div></div>
            </div>

        <?php elseif ($page === 'works'): ?>
            <h2>Manage Works</h2>
            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addWorkModal">Add New Work</button>
            <table class="table table-bordered">
                <thead><tr><th>ID</th><th>Category</th><th>Title</th><th>Image</th><th>Actions</th></tr></thead>
                <tbody>
                <?php
                $works = $pdo->query("SELECT w.*, c.name as cat_name FROM works w JOIN categories c ON w.category_id = c.id ORDER BY w.id DESC")->fetchAll();
                foreach ($works as $w): ?>
                    <tr>
                        <td><?= $w['id'] ?></td>
                        <td><?= $w['cat_name'] ?></td>
                        <td><?= $w['title'] ?></td>
                        <td><?= $w['image'] ? '<img src="assets/uploads/works/'.$w['image'].'" width="50">' : 'No image' ?></td>
                        <td>
                            <a href="admin.php?page=edit_work&id=<?= $w['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="admin.php?delete_work=<?= $w['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this work?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Add Work Modal -->
            <div class="modal fade" id="addWorkModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" enctype="multipart/form-data">
                            <div class="modal-header"><h5>Add New Work</h5></div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label>Category</label>
                                    <select name="category_id" class="form-control" required>
                                        <?php foreach (getCategories($pdo) as $cat): ?>
                                            <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label>Title</label>
                                    <input type="text" name="title" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>Description</label>
                                    <textarea name="description" class="form-control" rows="4"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label>Image</label>
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="add_work" class="btn btn-primary">Add</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php elseif ($page === 'edit_work' && isset($_GET['id'])): ?>
            <?php
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM works WHERE id = ?");
            $stmt->execute([$id]);
            $work = $stmt->fetch();
            if (!$work) {
                echo "<div class='alert alert-danger'>Work not found.</div>";
            } else {
            ?>
            <h2>Edit Work</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $work['id'] ?>">
                <input type="hidden" name="existing_image" value="<?= $work['image'] ?>">
                <div class="mb-3">
                    <label>Category</label>
                    <select name="category_id" class="form-control" required>
                        <?php foreach (getCategories($pdo) as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $cat['id']==$work['category_id']?'selected':'' ?>><?= $cat['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" value="<?= $work['title'] ?>" required>
                </div>
                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= $work['description'] ?></textarea>
                </div>
                <div class="mb-3">
                    <label>Current Image</label><br>
                    <?php if ($work['image'] && file_exists(UPLOAD_DIR . 'works/' . $work['image'])): ?>
                        <img src="assets/uploads/works/<?= $work['image'] ?>" width="150"><br>
                    <?php endif; ?>
                    <label>Change Image (leave empty to keep current)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <button type="submit" name="edit_work" class="btn btn-primary">Update</button>
                <a href="admin.php?page=works" class="btn btn-secondary">Cancel</a>
            </form>
            <?php } ?>

        <?php elseif ($page === 'resume'): ?>
            <h2>Upload Resume</h2>
            <p>Current resume: <a href="<?= RESUME_FILE ?>" target="_blank">View</a></p>
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label>New Resume (PDF only)</label>
                    <input type="file" name="resume" class="form-control" accept=".pdf" required>
                </div>
                <button type="submit" name="upload_resume" class="btn btn-primary">Upload</button>
            </form>

        <?php elseif ($page === 'users'): ?>
            <h2>Registered Users</h2>
            <table class="table table-bordered">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Company</th><th>Designation</th><th>Registered</th></tr></thead>
                <tbody>
                <?php
                $users = $pdo->query("SELECT * FROM visitors ORDER BY registered_at DESC")->fetchAll();
                foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= $u['name'] ?></td>
                        <td><?= $u['email'] ?></td>
                        <td><?= $u['mobile'] ?></td>
                        <td><?= $u['company'] ?></td>
                        <td><?= $u['designation'] ?></td>
                        <td><?= $u['registered_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($page === 'ratings'): ?>
            <h2>All Ratings</h2>
            <h4>Work Ratings</h4>
            <table class="table table-bordered">
                <thead><tr><th>Work</th><th>Visitor</th><th>Rating</th><th>Date</th></tr></thead>
                <tbody>
                <?php
                $ratings = $pdo->query("SELECT wr.*, w.title as work_title, v.name as visitor_name FROM work_ratings wr JOIN works w ON wr.work_id = w.id JOIN visitors v ON wr.visitor_id = v.id ORDER BY wr.rated_at DESC")->fetchAll();
                foreach ($ratings as $r): ?>
                    <tr><td><?= $r['work_title'] ?></td><td><?= $r['visitor_name'] ?></td><td><?= $r['rating'] ?></td><td><?= $r['rated_at'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h4>Overall Ratings</h4>
            <table class="table table-bordered">
                <thead><tr><th>Visitor</th><th>Rating</th><th>Date</th></tr></thead>
                <tbody>
                <?php
                $overall = $pdo->query("SELECT ors.*, v.name as visitor_name FROM overall_ratings ors JOIN visitors v ON ors.visitor_id = v.id ORDER BY ors.rated_at DESC")->fetchAll();
                foreach ($overall as $o): ?>
                    <tr><td><?= $o['visitor_name'] ?></td><td><?= $o['rating'] ?></td><td><?= $o['rated_at'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($page === 'contacts'): ?>
            <h2>Contact Messages</h2>
            <table class="table table-bordered">
                <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Message</th><th>Date</th></tr></thead>
                <tbody>
                <?php
                $msgs = $pdo->query("SELECT * FROM contacts ORDER BY submitted_at DESC")->fetchAll();
                foreach ($msgs as $m): ?>
                    <tr>
                        <td><?= $m['name'] ?></td>
                        <td><?= $m['email'] ?></td>
                        <td><?= $m['mobile'] ?></td>
                        <td><?= nl2br($m['message']) ?></td>
                        <td><?= $m['submitted_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($page === 'stats'): ?>
            <h2>Visitor Statistics</h2>
            <?php
            $total = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_stats")->fetchColumn();
            $today = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) FROM visitor_stats WHERE DATE(visited_at) = CURDATE()");
            $today->execute();
            $todayCount = $today->fetchColumn();
            ?>
            <p>Total unique visitors: <?= $total ?></p>
            <p>Unique visitors today: <?= $todayCount ?></p>
            <h4>Recent Visits</h4>
            <table class="table table-bordered">
                <thead><tr><th>IP Address</th><th>Visited At</th></tr></thead>
                <tbody>
                <?php
                $recent = $pdo->query("SELECT DISTINCT ip_address, MAX(visited_at) as last_visit FROM visitor_stats GROUP BY ip_address ORDER BY last_visit DESC LIMIT 20")->fetchAll();
                foreach ($recent as $r): ?>
                    <tr><td><?= $r['ip_address'] ?></td><td><?= $r['last_visit'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>