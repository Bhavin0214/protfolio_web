<?php
require_once 'config.php';
trackVisitor($pdo);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Contact form
    if (isset($_POST['contact'])) {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $mobile = sanitize($_POST['mobile']);
        $message = sanitize($_POST['message']);
        $pdo->prepare("INSERT INTO contacts (name, email, mobile, message) VALUES (?, ?, ?, ?)")
            ->execute([$name, $email, $mobile, $message]);
        $_SESSION['flash'] = "Message sent successfully!";
        redirect('index.php?page=contact');
    }

    // Work rating from logged-in visitor
    if (isset($_POST['rate_work']) && isVisitor()) {
        $work_id = (int)$_POST['work_id'];
        $rating = (int)$_POST['rating'];
        $visitor_id = getVisitorId();
        $check = $pdo->prepare("SELECT id FROM work_ratings WHERE work_id = ? AND visitor_id = ?");
        $check->execute([$work_id, $visitor_id]);
        if (!$check->fetch() && $rating >= 1 && $rating <= 5) {
            $pdo->prepare("INSERT INTO work_ratings (work_id, visitor_id, rating) VALUES (?, ?, ?)")
                ->execute([$work_id, $visitor_id, $rating]);
            $_SESSION['flash'] = "Rating submitted!";
        } else {
            $_SESSION['error'] = "You have already rated this project.";
        }
        redirect("index.php?page=project&id=$work_id");
    }

    // Overall rating from logged-in visitor
    if (isset($_POST['rate_overall']) && isVisitor()) {
        $rating = (int)$_POST['rating'];
        $visitor_id = getVisitorId();
        $check = $pdo->prepare("SELECT id FROM overall_ratings WHERE visitor_id = ?");
        $check->execute([$visitor_id]);
        if (!$check->fetch() && $rating >= 1 && $rating <= 5) {
            $pdo->prepare("INSERT INTO overall_ratings (visitor_id, rating) VALUES (?, ?)")
                ->execute([$visitor_id, $rating]);
            $_SESSION['flash'] = "Overall rating saved!";
        } else {
            $_SESSION['error'] = "You have already rated the profile.";
        }
        redirect('index.php');
    }

    // Visitor registration (first-time rating)
    if (isset($_POST['register'])) {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $mobile = sanitize($_POST['mobile']);
        $company = sanitize($_POST['company'] ?? '');
        $designation = sanitize($_POST['designation'] ?? '');
        $type = $_POST['type']; // 'work' or 'overall'
        $work_id = (int)$_POST['work_id'];
        $rating = (int)$_POST['rating'];

        // Insert visitor
        $stmt = $pdo->prepare("INSERT INTO visitors (name, email, mobile, company, designation) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $mobile, $company, $designation]);
        $visitor_id = $pdo->lastInsertId();
        $_SESSION['visitor_id'] = $visitor_id;

        // Apply pending rating
        if ($type === 'work' && $work_id) {
            $pdo->prepare("INSERT INTO work_ratings (work_id, visitor_id, rating) VALUES (?, ?, ?)")
                ->execute([$work_id, $visitor_id, $rating]);
            $_SESSION['flash'] = "Thank you! Your rating has been recorded.";
            redirect("index.php?page=project&id=$work_id");
        } elseif ($type === 'overall') {
            $pdo->prepare("INSERT INTO overall_ratings (visitor_id, rating) VALUES (?, ?)")
                ->execute([$visitor_id, $rating]);
            $_SESSION['flash'] = "Thank you for rating my profile!";
            redirect('index.php');
        }
    }
}

// Determine page
$page = $_GET['page'] ?? 'home';
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If rating attempted but not logged in, show registration form
if (($action === 'rate_work' || $action === 'rate_overall') && !isVisitor()) {
    $type = $action === 'rate_work' ? 'work' : 'overall';
    $work_id = $id; // for work rating
    $rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
    // Show registration form (embedded below)
    $showRegistration = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Portfolio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .navbar-brand { font-weight: bold; }
        .rating-stars { color: gold; font-size: 1.2rem; }
        .card-img-top { height: 200px; object-fit: cover; }
        .flash { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">My Portfolio</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Work</a>
                        <ul class="dropdown-menu">
                            <?php foreach (getCategories($pdo) as $cat): ?>
                                <li><a class="dropdown-item" href="index.php?page=category&id=<?= $cat['id'] ?>"><?= $cat['name'] ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="index.php?page=contact">Contact</a></li>
                    <?php if (isAdmin()): ?>
                        <li class="nav-item"><a class="nav-link" href="admin.php">Admin</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin.php?logout=1">Logout</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-success flash"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger flash"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (isset($showRegistration) && $showRegistration): ?>
            <!-- Registration Form -->
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h4>Register to Rate</h4></div>
                        <div class="card-body">
                            <p>Please provide your details (one-time registration).</p>
                            <form method="post">
                                <input type="hidden" name="type" value="<?= $type ?>">
                                <input type="hidden" name="work_id" value="<?= $work_id ?>">
                                <input type="hidden" name="rating" value="<?= $rating ?>">
                                <div class="mb-3">
                                    <label>Full Name *</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>Email *</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>Mobile *</label>
                                    <input type="text" name="mobile" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>Company (optional)</label>
                                    <input type="text" name="company" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label>Designation (optional)</label>
                                    <input type="text" name="designation" class="form-control">
                                </div>
                                <button type="submit" name="register" class="btn btn-primary">Register & Rate</button>
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>

        <!-- Page Content -->
        <?php if ($page === 'home'): ?>
            <!-- Homepage -->
            <div class="row">
                <div class="col-md-4 text-center">
                    <img src="assets/img/profile.jpg" class="rounded-circle img-fluid" alt="Profile" style="max-width:200px;" onerror="this.src='https://via.placeholder.com/200'">
                    <h1 class="mt-3">Your Name</h1>
                    <p class="lead">Software & Web Developer | Designer</p>
                    <a href="<?= RESUME_FILE ?>" class="btn btn-primary" download>Download Resume</a>
                </div>
                <div class="col-md-8">
                    <h2>About Me</h2>
                    <p>Short bio goes here – showcase your passion and expertise.</p>
                    <hr>
                    <h3>Overall Profile Rating: <?= getOverallRating($pdo) ?> / 5</h3>
                    <?php if (!isVisitor()): ?>
                        <a href="index.php?action=rate_overall" class="btn btn-outline-warning">Rate My Profile</a>
                    <?php else: ?>
                        <form method="post" class="mt-3">
                            <label>Rate my profile:</label>
                            <select name="rating" class="form-select w-auto d-inline-block">
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Good</option>
                                <option value="3">3 - Average</option>
                                <option value="2">2 - Poor</option>
                                <option value="1">1 - Very Poor</option>
                            </select>
                            <button type="submit" name="rate_overall" class="btn btn-warning">Submit</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mt-5">
                <h2>My Work Categories</h2>
                <?php foreach (getCategories($pdo) as $cat): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?= $cat['name'] ?></h5>
                                <a href="index.php?page=category&id=<?= $cat['id'] ?>" class="btn btn-primary">View Projects</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($page === 'category' && $id): ?>
            <!-- Category listing -->
            <h2><?= getCategoryName($pdo, $id) ?> Projects</h2>
            <div class="row">
                <?php
                $stmt = $pdo->prepare("SELECT * FROM works WHERE category_id = ? ORDER BY created_at DESC");
                $stmt->execute([$id]);
                $works = $stmt->fetchAll();
                foreach ($works as $work): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if ($work['image'] && file_exists(UPLOAD_DIR . 'works/' . $work['image'])): ?>
                                <img src="assets/uploads/works/<?= $work['image'] ?>" class="card-img-top">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/300x200" class="card-img-top">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?= $work['title'] ?></h5>
                                <p class="card-text"><?= substr($work['description'], 0, 100) ?>...</p>
                                <a href="index.php?page=project&id=<?= $work['id'] ?>" class="btn btn-primary">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($page === 'project' && $id): ?>
            <!-- Single project -->
            <?php
            $stmt = $pdo->prepare("SELECT w.*, c.name as cat_name FROM works w JOIN categories c ON w.category_id = c.id WHERE w.id = ?");
            $stmt->execute([$id]);
            $work = $stmt->fetch();
            if (!$work): echo "<div class='alert alert-warning'>Project not found.</div>";
            else: ?>
                <h2><?= $work['title'] ?></h2>
                <p><strong>Category:</strong> <?= $work['cat_name'] ?></p>
                <?php if ($work['image'] && file_exists(UPLOAD_DIR . 'works/' . $work['image'])): ?>
                    <img src="assets/uploads/works/<?= $work['image'] ?>" class="img-fluid mb-3" style="max-height:400px;">
                <?php endif; ?>
                <p><?= nl2br($work['description']) ?></p>
                <h4>Rating: <?= getWorkRating($pdo, $id) ?> / 5</h4>

                <?php if (isVisitor()): ?>
                    <?php
                    $check = $pdo->prepare("SELECT id FROM work_ratings WHERE work_id = ? AND visitor_id = ?");
                    $check->execute([$id, getVisitorId()]);
                    if (!$check->fetch()): ?>
                        <form method="post" class="row g-3">
                            <input type="hidden" name="work_id" value="<?= $id ?>">
                            <div class="col-auto">
                                <select name="rating" class="form-select">
                                    <option value="5">5 - Excellent</option>
                                    <option value="4">4 - Good</option>
                                    <option value="3">3 - Average</option>
                                    <option value="2">2 - Poor</option>
                                    <option value="1">1 - Very Poor</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" name="rate_work" class="btn btn-primary">Rate this project</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-muted">You have already rated this project.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="index.php?action=rate_work&id=<?= $id ?>&rating=5" class="btn btn-outline-warning">Login to Rate</a>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($page === 'contact'): ?>
            <!-- Contact page -->
            <div class="row">
                <div class="col-md-6">
                    <h2>Contact Me</h2>
                    <form method="post">
                        <div class="mb-3">
                            <label>Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Mobile</label>
                            <input type="text" name="mobile" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>Message *</label>
                            <textarea name="message" class="form-control" rows="5" required></textarea>
                        </div>
                        <button type="submit" name="contact" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
                <div class="col-md-6">
                    <h2>Other Details</h2>
                    <p>Email: your.email@example.com</p>
                    <p>Phone: +1234567890</p>
                    <p>LinkedIn: <a href="#">linkedin.com/in/yourname</a></p>
                    <p>GitHub: <a href="#">github.com/yourname</a></p>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-warning">Page not found.</div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide flash messages after 3 seconds
        setTimeout(() => {
            document.querySelectorAll('.flash').forEach(el => el.remove());
        }, 3000);
    </script>
</body>
</html>