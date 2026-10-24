<?php
// ============================================================
// LIBRARY MANAGEMENT SYSTEM
// Save as: library_system.php inside C:\xampp\htdocs\
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'library_db');

// --- DATABASE CONNECTION FUNCTION ---
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) die("DB Error: " . $conn->connect_error);
    return $conn;
}

session_start();

// --- HELPER FUNCTIONS ---
function isLoggedIn() { return isset($_SESSION['user_id']); }
function isAdmin()    { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function sanitize($d) { return htmlspecialchars(strip_tags(trim($d ?? ''))); }

function calcPenalty($due_date, $returned_date = null) {
    $check = $returned_date ? new DateTime($returned_date) : new DateTime();
    $due   = new DateTime($due_date);
    if ($check > $due) return $due->diff($check)->days * 10.00;
    return 0;
}

// ─── ACTIONS ────────────────────────────────────────────────
$action  = $_GET['action'] ?? (isLoggedIn() ? 'home' : 'login');
$message = '';
$error   = '';

// --- ACTION: LOGIN ---
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];
        header("Location: ?action=home"); exit;
    } else {
        $error = "Invalid email or password."; 
    }
    $db->close(); 
}

// --- ACTION: LOGOUT ---
if ($action === 'logout') { session_destroy(); header("Location: ?action=login"); exit; }

// --- ACTION: REGISTER ---
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = sanitize($_POST['name']);
    $email   = sanitize($_POST['email']);
    $phone   = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $pw      = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $db = getDB();

    $stmt = $db->prepare("INSERT INTO users (name,email,password,phone,address) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssss", $name, $email, $pw, $phone, $address);
    if ($stmt->execute()) { $message = "Account created! You can now log in."; $action = 'login'; }
    else { $error = "Email already exists."; }
    $db->close();
}

// --- ACTION: RESERVE A BOOK (User) ---
if ($action === 'do_reserve' && isLoggedIn()) {
    $book_id = (int)($_GET['book_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    $db = getDB();

    // Check if user already reserved this active book
    $check = $db->prepare("SELECT id FROM reservations WHERE user_id=? AND book_id=? AND status IN ('pending', 'ready')");
    $check->bind_param("ii", $user_id, $book_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "You already have an active reservation for this book.";
    } else {
        $stmt = $db->prepare("INSERT INTO reservations (user_id, book_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $book_id);
        if ($stmt->execute()) {
            $message = "Book reserved successfully! Check your status under Reservations.";
        } else {
            $error = "Failed to place reservation.";
        }
    }
    $action = 'reservations';
    $db->close();
}

// --- ACTION: CANCEL RESERVATION ---
if ($action === 'cancel_reservation' && isLoggedIn()) {
    $res_id  = (int)($_GET['id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    $db = getDB();

    if (isAdmin()) {
        $stmt = $db->prepare("UPDATE reservations SET status='cancelled' WHERE id=?");
        $stmt->bind_param("i", $res_id);
    } else {
        $stmt = $db->prepare("UPDATE reservations SET status='cancelled' WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $res_id, $user_id);
    }

    if ($stmt->execute()) {
        $message = "Reservation cancelled.";
    }
    $action = 'reservations';
    $db->close();
}

// --- ACTION: BORROW A BOOK (Admin only) ---
if ($action === 'do_borrow' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $user_id  = (int)$_POST['user_id'];
    $book_id  = (int)$_POST['book_id'];
    $date_b   = $_POST['date_borrowed'];
    $due      = $_POST['due_date'];
    $db = getDB();

    $book  = $db->query("SELECT * FROM books WHERE id=$book_id")->fetch_assoc();
    $days  = max(1, (new DateTime($due))->diff(new DateTime($date_b))->days);
    $fee   = $book['fee_per_day'] * $days;

    $stmt  = $db->prepare("INSERT INTO borrowed_books (user_id,book_id,date_borrowed,due_date,fee) VALUES (?,?,?,?,?)");
    $stmt->bind_param("iissd", $user_id, $book_id, $date_b, $due, $fee);
    if ($stmt->execute()) {
        $db->query("UPDATE books SET copies=copies-1 WHERE id=$book_id AND copies>0");
        
        // Fulfill pending reservation if present
        $db->query("UPDATE reservations SET status='claimed' WHERE user_id=$user_id AND book_id=$book_id AND status IN ('pending','ready') LIMIT 1");
        
        $message = "Book borrowed successfully!";
    } else { $error = "Error borrowing book."; }
    $action = 'borrowed';
    $db->close();
}

// --- ACTION: RETURN A BOOK (Admin only) ---
if ($action === 'do_return' && $_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $bid  = (int)$_POST['borrow_id'];
    $date = $_POST['date_returned'];
    $db   = getDB();

    $b    = $db->query("SELECT * FROM borrowed_books WHERE id=$bid")->fetch_assoc();
    $pen  = calcPenalty($b['due_date'], $date);

    $stmt = $db->prepare("UPDATE borrowed_books SET date_returned=?,penalty=?,status='returned' WHERE id=?");
    $stmt->bind_param("sdi", $date, $pen, $bid);
    if ($stmt->execute()) {
        $db->query("UPDATE books SET copies=copies+1 WHERE id={$b['book_id']}");
        $message = "Book returned! Penalty: ₱" . number_format($pen, 2);
    }
    $action = 'returned';
    $db->close();
}

// --- ACTION: MARK PENALTY AS PAID (Admin only) ---
if ($action === 'mark_paid' && isAdmin()) {
    $id  = (int)$_GET['id'];
    $today = date('Y-m-d');
    $db  = getDB();
    $db->query("UPDATE borrowed_books SET date_paid='$today' WHERE id=$id");
    $message = "Penalty marked as paid.";
    $action  = 'report';
    $db->close();
}

// --- ACTION: UPDATE PROFILE ---
if ($action === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $name    = sanitize($_POST['name']);
    $phone   = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $id      = $_SESSION['user_id'];
    $db = getDB();

    $stmt = $db->prepare("UPDATE users SET name=?,phone=?,address=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $phone, $address, $id);
    $stmt->execute();
    $_SESSION['name'] = $name;
    $message = "Profile updated!";
    $action  = 'profile';
    $db->close();
}

// ─── DATA FETCH ──────────────────────────────────────────────
$db = getDB();
$uid = $_SESSION['user_id'] ?? 0;

$all_users = $db->query("SELECT * FROM users ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$all_books = $db->query("SELECT * FROM books ORDER BY title")->fetch_all(MYSQLI_ASSOC);

$search = sanitize($_GET['q'] ?? '');
$book_search_results = [];
if ($search !== '') {
    $s = $db->real_escape_string($search);
    $book_search_results = $db->query("SELECT * FROM books WHERE title LIKE '%$s%' OR author LIKE '%$s%'")->fetch_all(MYSQLI_ASSOC);
}

if (isAdmin()) {
    $borrowed_books = $db->query("
        SELECT bb.*, u.name as user_name, b.title as book_title, b.author
        FROM borrowed_books bb JOIN users u ON bb.user_id=u.id JOIN books b ON bb.book_id=b.id
        WHERE bb.status='borrowed' ORDER BY bb.date_borrowed DESC
    ")->fetch_all(MYSQLI_ASSOC);

    $returned_books = $db->query("
        SELECT bb.*, u.name as user_name, b.title as book_title, b.author
        FROM borrowed_books bb JOIN users u ON bb.user_id=u.id JOIN books b ON bb.book_id=b.id
        WHERE bb.status='returned' ORDER BY bb.date_returned DESC
    ")->fetch_all(MYSQLI_ASSOC);

    $report_data = $db->query("
        SELECT bb.*, u.name as user_name, b.title as book_title
        FROM borrowed_books bb JOIN users u ON bb.user_id=u.id JOIN books b ON bb.book_id=b.id
        ORDER BY bb.date_borrowed DESC
    ")->fetch_all(MYSQLI_ASSOC);

    $reservations = $db->query("
        SELECT r.*, u.name as user_name, b.title as book_title, b.copies
        FROM reservations r 
        JOIN users u ON r.user_id=u.id 
        JOIN books b ON r.book_id=b.id
        ORDER BY r.reserved_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
} else {
    $borrowed_books = $db->query("
        SELECT bb.*, u.name as user_name, b.title as book_title, b.author
        FROM borrowed_books bb JOIN users u ON bb.user_id=u.id JOIN books b ON bb.book_id=b.id
        WHERE bb.status='borrowed' AND bb.user_id=$uid ORDER BY bb.date_borrowed DESC
    ")->fetch_all(MYSQLI_ASSOC);

    $reservations = $db->query("
        SELECT r.*, u.name as user_name, b.title as book_title, b.copies
        FROM reservations r 
        JOIN users u ON r.user_id=u.id 
        JOIN books b ON r.book_id=b.id
        WHERE r.user_id=$uid
        ORDER BY r.reserved_at DESC
    ")->fetch_all(MYSQLI_ASSOC);

    $returned_books = [];
    $report_data    = [];
}

$current_user = isLoggedIn() ? $db->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc() : null;

$stats = [
    'total_books'    => $db->query("SELECT SUM(copies) as t FROM books")->fetch_assoc()['t'] ?? 0,
    'total_borrowed' => $db->query("SELECT COUNT(*) as t FROM borrowed_books WHERE status='borrowed'")->fetch_assoc()['t'] ?? 0,
    'total_returned' => $db->query("SELECT COUNT(*) as t FROM borrowed_books WHERE status='returned'")->fetch_assoc()['t'] ?? 0,
    'total_penalty'  => $db->query("SELECT SUM(penalty) as t FROM borrowed_books WHERE date_paid IS NULL AND penalty>0")->fetch_assoc()['t'] ?? 0,
    'my_borrowed'    => $db->query("SELECT COUNT(*) as t FROM borrowed_books WHERE user_id=$uid AND status='borrowed'")->fetch_assoc()['t'] ?? 0,
];
$db->close();

if (!isLoggedIn() && !in_array($action, ['login','register'])) {
    header("Location: ?action=login"); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Library Management System</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap');

* { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --blue:     #3b82f6;
  --blue-dk:  #2563eb;
  --blue-lt:  #eff6ff;
  --green:    #22c55e;
  --green-lt: #f0fdf4;
  --red:      #ef4444;
  --red-lt:   #fef2f2;
  --yellow:   #f59e0b;
  --yellow-lt:#fffbeb;
  --gray:     #6b7280;
  --gray-lt:  #f3f4f6;
  --border:   #e5e7eb;
  --text:     #1f2937;
  --text-sm:  #6b7280;
  --white:    #ffffff;
  --bg:       #f9fafb;
}

body {
  font-family: 'Nunito', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  font-size: 15px;
}

nav {
  background: var(--white);
  border-bottom: 1px solid var(--border);
  padding: 0 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 64px;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 1px 3px rgba(0,0,0,.04);
}

.nav-brand {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--blue);
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: .4rem;
  white-space: nowrap;
}

.nav-links {
  display: flex;
  gap: .15rem;
  align-items: center;
  overflow-x: auto;
  white-space: nowrap;
  scrollbar-width: none;
}
.nav-links::-webkit-scrollbar { display: none; }

.nav-links a {
  color: var(--gray);
  text-decoration: none;
  padding: .35rem .65rem;
  border-radius: 8px;
  font-size: .82rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  transition: all .15s ease;
}

.nav-links a:hover  { background: var(--blue-lt); color: var(--blue); }
.nav-links a.active { background: var(--blue-lt); color: var(--blue); }

.nav-logout {
  background: var(--red-lt);
  color: var(--red) !important;
  border: 1px solid #fecaca;
  margin-left: .4rem;
}
.nav-logout:hover { background: #fecaca !important; }

.container { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem; }

.page-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 1.2rem;
  display: flex;
  align-items: center;
  gap: .5rem;
}

.card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.2rem 1.4rem;
  margin-bottom: 1.2rem;
  box-shadow: 0 1px 3px rgba(0,0,0,.05);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: .8rem;
  margin-bottom: 1.2rem;
}

.stat-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1rem;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.stat-num  { font-size: 1.8rem; font-weight: 800; color: var(--blue); display: block; }
.stat-lbl  { font-size: .78rem; color: var(--text-sm); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-top: .2rem; }

.table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
table { width: 100%; border-collapse: collapse; font-size: .88rem; }
th {
  background: var(--gray-lt);
  color: var(--text-sm);
  font-weight: 700;
  padding: .65rem 1rem;
  text-align: left;
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  border-bottom: 1px solid var(--border);
}
td { padding: .65rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: var(--blue-lt); }

.badge { display: inline-block; padding: .2rem .6rem; border-radius: 20px; font-size: .75rem; font-weight: 700; }
.badge-blue   { background: var(--blue-lt); color: var(--blue); }
.badge-green  { background: var(--green-lt); color: #16a34a; }
.badge-red    { background: var(--red-lt); color: var(--red); }
.badge-yellow { background: var(--yellow-lt); color: #b45309; }
.badge-gray   { background: var(--gray-lt); color: var(--gray); }

.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: .8rem; }
.form-group { display: flex; flex-direction: column; gap: .3rem; }
label { font-size: .8rem; font-weight: 700; color: var(--text-sm); text-transform: uppercase; letter-spacing: .04em; }

input, select, textarea {
  background: var(--white);
  border: 1.5px solid var(--border);
  color: var(--text);
  padding: .55rem .85rem;
  border-radius: 8px;
  font-family: inherit;
  font-size: .9rem;
  transition: border-color .15s;
}
input:focus, select:focus, textarea:focus { outline: none; border-color: var(--blue); }
textarea { resize: vertical; min-height: 75px; }
select option { background: white; }

.btn {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .5rem 1.1rem;
  border-radius: 8px;
  font-family: inherit;
  font-size: .88rem;
  font-weight: 700;
  cursor: pointer;
  border: none;
  text-decoration: none;
  transition: all .15s;
}
.btn-blue  { background: var(--blue); color: white; }
.btn-blue:hover { background: var(--blue-dk); }
.btn-green { background: var(--green); color: white; }
.btn-green:hover { background: #16a34a; }
.btn-red   { background: var(--red); color: white; }
.btn-red:hover { background: #dc2626; }
.btn-gray  { background: var(--gray-lt); color: var(--text); border: 1px solid var(--border); }
.btn-gray:hover { background: var(--border); }
.btn-sm { padding: .3rem .7rem; font-size: .8rem; }
.btn-outline { background: white; border: 1.5px solid var(--blue); color: var(--blue); }
.btn-outline:hover { background: var(--blue-lt); }

.alert { padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; font-weight: 600; display: flex; align-items: center; gap: .5rem; }
.alert-success { background: var(--green-lt); border: 1px solid #bbf7d0; color: #16a34a; }
.alert-error   { background: var(--red-lt);   border: 1px solid #fecaca; color: var(--red); }

.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
  padding: 1.5rem;
}

.auth-card {
  background: white;
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 2rem;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 4px 24px rgba(59,130,246,.1);
}
.auth-icon  { font-size: 2.5rem; margin-bottom: .5rem; }
.auth-title { font-size: 1.4rem; font-weight: 800; color: var(--text); margin-bottom: .2rem; }
.auth-sub   { color: var(--text-sm); font-size: .88rem; margin-bottom: 1.5rem; }

.modal-bg {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.4);
  z-index: 500;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.modal-bg.open { display: flex; }

.modal {
  background: white;
  border-radius: 14px;
  padding: 1.5rem;
  width: 100%;
  max-width: 480px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 8px 32px rgba(0,0,0,.15);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.2rem;
}

.search-bar {
  display: flex;
  gap: .6rem;
  margin-bottom: 1rem;
}
.search-bar input { flex: 1; }

.profile-top {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.2rem;
  background: var(--blue-lt);
  border-radius: 10px;
  margin-bottom: 1.2rem;
}

.avatar {
  width: 56px; height: 56px;
  border-radius: 50%;
  background: var(--blue);
  color: white;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; font-weight: 800;
  flex-shrink: 0;
}

.overdue-row td { background: #fff5f5 !important; }
.role-admin { background: #fef3c7; color: #b45309; }
.role-user  { background: var(--blue-lt); color: var(--blue); }

.empty { text-align: center; padding: 2.5rem 1rem; color: var(--text-sm); }
.empty-icon { font-size: 2.5rem; margin-bottom: .5rem; }

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  flex-wrap: wrap;
  gap: .5rem;
}
.section-title { font-size: 1rem; font-weight: 800; color: var(--text); }
</style>
</head>
<body>

<?php if (!isLoggedIn()): ?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-icon">📚</div>
    <div class="auth-title"><?= $action === 'register' ? 'Create Account' : 'Library System' ?></div>
    <div class="auth-sub"><?= $action === 'register' ? 'Fill in the form to register' : 'Sign in to your account' ?></div>

    <?php if ($message): ?><div class="alert alert-success">✅ <?= $message ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error">❌ <?= $error ?></div><?php endif; ?>

    <?php if ($action === 'register'): ?>
    <form method="POST" action="?action=register">
      <div class="form-group" style="margin-bottom:.7rem">
        <label>Full Name</label>
        <input type="text" name="name" required placeholder="Enter your full name">
      </div>
      <div class="form-group" style="margin-bottom:.7rem">
        <label>Email</label>
        <input type="email" name="email" required placeholder="yourname@email.com">
      </div>
      <div class="form-group" style="margin-bottom:.7rem">
        <label>Phone</label>
        <input type="text" name="phone" placeholder="09XXXXXXXXX">
      </div>
      <div class="form-group" style="margin-bottom:.7rem">
        <label>Address</label>
        <textarea name="address" placeholder="Your address"></textarea>
      </div>
      <div class="form-group" style="margin-bottom:1.2rem">
        <label>Password</label>
        <input type="password" name="password" required placeholder="Create a password">
      </div>
      <button class="btn btn-blue" style="width:100%;justify-content:center" type="submit">Register</button>
      <p style="margin-top:1rem;text-align:center;font-size:.85rem;color:var(--text-sm)">
        Already have an account? <a href="?action=login" style="color:var(--blue);font-weight:700">Login here</a>
      </p>
    </form>
    <?php else: ?>
    <form method="POST" action="?action=login">
      <div class="form-group" style="margin-bottom:.7rem">
        <label>Email</label>
        <input type="email" name="email" required placeholder="Enter your email">
      </div>
      <div class="form-group" style="margin-bottom:1.2rem">
        <label>Password</label>
        <input type="password" name="password" required placeholder="Enter your password">
      </div>
      <button class="btn btn-blue" style="width:100%;justify-content:center" type="submit">Login</button>
      <p style="margin-top:1rem;text-align:center;font-size:.85rem;color:var(--text-sm)">
        No account yet? <a href="?action=register" style="color:var(--blue);font-weight:700">Register here</a>
      </p>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<nav>
  <a class="nav-brand" href="?action=home">📚 LibraryMS</a>

  <div class="nav-links">
    <a href="?action=home"         <?= $action==='home'?'class="active"':'' ?>>🏠 Home</a>
    <a href="?action=books"        <?= $action==='books'?'class="active"':'' ?>>📖 Books</a>
    <a href="?action=borrowed"     <?= $action==='borrowed'?'class="active"':'' ?>>📋 Borrowed</a>
    <a href="?action=reservations" <?= $action==='reservations'?'class="active"':'' ?>>⏳ Reservations</a>

    <?php if (isAdmin()): ?>
    <a href="?action=returned"  <?= $action==='returned'?'class="active"':'' ?>>✅ Returned</a>
    <a href="?action=users"     <?= $action==='users'?'class="active"':'' ?>>👥 Manage Users</a>
    <a href="?action=analytics" <?= $action==='analytics'?'class="active"':'' ?>>📈 Analytics</a>
    <a href="?action=report"    <?= $action==='report'?'class="active"':'' ?>>📊 Report</a>
    <a href="?action=settings"  <?= $action==='settings'?'class="active"':'' ?>>⚙️ Settings</a>
    <?php endif; ?>

    <a href="?action=profile"  <?= $action==='profile'?'class="active"':'' ?>>👤 Profile</a>
    <a href="?action=logout" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="container">

<?php if ($message): ?><div class="alert alert-success">✅ <?= $message ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error">❌ <?= $error ?></div><?php endif; ?>

<!-- ═══════════════════════════════════════
     PAGE: HOME / DASHBOARD
════════════════════════════════════════ -->
<?php if ($action === 'home'): ?>
<div class="page-title">
  🏠 Welcome, <?= sanitize($_SESSION['name']) ?>!
  <span class="badge <?= isAdmin() ? 'role-admin' : 'role-user' ?>" style="font-size:.75rem">
    <?= isAdmin() ? '👑 Admin' : '🎓 Member' ?>
  </span>
</div>

<div class="stats-grid">
  <?php if (isAdmin()): ?>
  <div class="stat-card">
    <span class="stat-num"><?= $stats['total_books'] ?></span>
    <span class="stat-lbl">📚 Total Books</span>
  </div>
  <div class="stat-card">
    <span class="stat-num" style="color:var(--yellow)"><?= $stats['total_borrowed'] ?></span>
    <span class="stat-lbl">📋 Borrowed</span>
  </div>
  <div class="stat-card">
    <span class="stat-num" style="color:var(--green)"><?= $stats['total_returned'] ?></span>
    <span class="stat-lbl">✅ Returned</span>
  </div>
  <div class="stat-card">
    <span class="stat-num" style="color:var(--red)">₱<?= number_format($stats['total_penalty'],0) ?></span>
    <span class="stat-lbl">⚠️ Unpaid Penalty</span>
  </div>
  <?php else: ?>
  <div class="stat-card">
    <span class="stat-num"><?= $stats['total_books'] ?></span>
    <span class="stat-lbl">📚 Books Available</span>
  </div>
  <div class="stat-card">
    <span class="stat-num" style="color:var(--yellow)"><?= $stats['my_borrowed'] ?></span>
    <span class="stat-lbl">📋 My Borrowed</span>
  </div>
  <?php endif; ?>
</div>

<?php if (!isAdmin()): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:1.2rem">
  <a href="?action=books" class="card" style="text-decoration:none;text-align:center;padding:1.5rem;">
    <div style="font-size:2rem">📖</div>
    <div style="font-weight:700;margin-top:.4rem;color:var(--blue)">Browse Books</div>
    <div style="font-size:.82rem;color:var(--text-sm)">Search available books</div>
  </a>

  <a href="?action=borrowed" class="card" style="text-decoration:none;text-align:center;padding:1.5rem;">
    <div style="font-size:2rem">📋</div>
    <div style="font-weight:700;margin-top:.4rem;color:var(--blue)">My Books</div>
    <div style="font-size:.82rem;color:var(--text-sm)">View borrowed books</div>
  </a>
</div>
<?php endif; ?>

<?php if (isAdmin()): ?>
<div class="card">
  <div class="section-header">
    <div class="section-title">📋 Recently Borrowed</div>
    <a href="?action=borrowed" class="btn btn-outline btn-sm">View All</a>
  </div>
  <?php
  $db2 = getDB();
  $recent = $db2->query("
    SELECT bb.*, u.name as uname, b.title as btitle
    FROM borrowed_books bb JOIN users u ON bb.user_id=u.id JOIN books b ON bb.book_id=b.id
    WHERE bb.status='borrowed' ORDER BY bb.date_borrowed DESC LIMIT 5
  ")->fetch_all(MYSQLI_ASSOC);
  $db2->close();
  ?>
  <?php if (empty($recent)): ?>
  <div class="empty"><div class="empty-icon">📬</div>No borrowed books yet.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr><th>Member</th><th>Book</th><th>Due Date</th><th>Status</th></tr>
      <?php foreach ($recent as $r):
        $ov = (new DateTime($r['due_date'])) < new DateTime();
      ?>
      <tr <?= $ov ? 'class="overdue-row"' : '' ?>>
        <td><?= sanitize($r['uname']) ?></td>
        <td><?= sanitize($r['btitle']) ?></td>
        <td><?= $r['due_date'] ?></td>
        <td><?= $ov ? '<span class="badge badge-red">Overdue</span>' : '<span class="badge badge-yellow">Borrowed</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     PAGE: BOOKS CATALOG
════════════════════════════════════════ -->
<?php elseif ($action === 'books'): ?>
<div class="page-title">📖 Books Catalog</div>

<div class="card">
  <form method="GET" action="">
    <input type="hidden" name="action" value="books">
    <div class="search-bar">
      <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="🔍 Search by title or author...">
      <button class="btn btn-blue" type="submit">Search</button>
      <?php if ($search): ?><a href="?action=books" class="btn btn-gray">Clear</a><?php endif; ?>
    </div>
  </form>

  <?php $display_books = $search ? $book_search_results : $all_books; ?>

  <?php if ($search): ?>
  <p style="font-size:.85rem;color:var(--text-sm);margin-bottom:.8rem">
    Found <strong><?= count($display_books) ?></strong> result(s) for "<strong><?= sanitize($search) ?></strong>"
  </p>
  <?php endif; ?>

  <?php if (empty($display_books)): ?>
  <div class="empty"><div class="empty-icon">🔍</div>No books found.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr><th>#</th><th>Title</th><th>Author</th><th>ISBN</th><th>Fee/Day</th><th>Copies</th><th>Action</th></tr>
      <?php foreach ($display_books as $i => $b): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><strong><?= sanitize($b['title']) ?></strong></td>
        <td><?= sanitize($b['author']) ?></td>
        <td style="color:var(--text-sm);font-size:.82rem"><?= sanitize($b['isbn']) ?></td>
        <td>₱<?= number_format($b['fee_per_day'],2) ?></td>
        <td>
          <?php if ($b['copies'] > 0): ?>
            <span class="badge badge-green"><?= $b['copies'] ?> available</span>
          <?php else: ?>
            <span class="badge badge-red">Out of stock</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!isAdmin()): ?>
            <a href="?action=do_reserve&book_id=<?= $b['id'] ?>" class="btn btn-outline btn-sm" onclick="return confirm('Reserve this book?')">⏳ Reserve</a>
          <?php else: ?>
            <span class="badge badge-gray">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php if (isAdmin()): ?>
<div class="card">
  <div class="section-title" style="margin-bottom:1rem">➕ Borrow a Book</div>
  <form method="POST" action="?action=do_borrow">
    <div class="form-grid" style="margin-bottom:.8rem">
      <div class="form-group">
        <label>Member</label>
        <select name="user_id" required>
          <option value="">— Select Member —</option>
          <?php foreach ($all_users as $u): ?>
          <option value="<?= $u['id'] ?>"><?= sanitize($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Book</label>
        <select name="book_id" required>
          <option value="">— Select Book —</option>
          <?php foreach ($all_books as $b): ?>
          <?php if ($b['copies'] > 0): ?>
          <option value="<?= $b['id'] ?>"><?= sanitize($b['title']) ?> — ₱<?= number_format($b['fee_per_day'],2) ?>/day</option>
          <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Date Borrowed</label>
        <input type="date" name="date_borrowed" value="<?= date('Y-m-d') ?>" required>
      </div>

      <div class="form-group">
        <label>Due Date</label>
        <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
      </div>
    </div>
    <button class="btn btn-blue" type="submit">✅ Confirm Borrow</button>
  </form>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     PAGE: BORROWED BOOKS
════════════════════════════════════════ -->
<?php elseif ($action === 'borrowed'): ?>
<div class="page-title">📋 <?= isAdmin() ? 'All Borrowed Books' : 'My Borrowed Books' ?></div>

<?php if (isAdmin()): ?>
<div class="modal-bg" id="returnModal">
  <div class="modal">
    <div class="modal-header">
      <div class="section-title">📦 Return Book</div>
      <button class="btn btn-gray btn-sm" onclick="closeModal('returnModal')">✕ Close</button>
    </div>
    <form method="POST" action="?action=do_return">
      <input type="hidden" name="borrow_id" id="returnId">

      <div class="form-group" style="margin-bottom:1rem">
        <label>Date Returned</label>
        <input type="date" name="date_returned" value="<?= date('Y-m-d') ?>" required>
      </div>

      <p style="font-size:.85rem;color:var(--text-sm);margin-bottom:1rem;padding:.7rem;background:var(--yellow-lt);border-radius:8px">
        ⚠️ Penalty is <strong>₱10.00 per day</strong> past the due date.
      </p>

      <button class="btn btn-green" type="submit" style="width:100%;justify-content:center">Confirm Return</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <?php if (empty($borrowed_books)): ?>
  <div class="empty">
    <div class="empty-icon">📬</div>
    <?= isAdmin() ? 'No borrowed books at the moment.' : 'You have no borrowed books.' ?>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr>
        <th>#</th>
        <?php if (isAdmin()): ?><th>Member</th><?php endif; ?>
        <th>Book Title</th>
        <th>Author</th>
        <th>Date Borrowed</th>
        <th>Due Date</th>
        <th>Fee</th>
        <th>Status</th>
        <?php if (isAdmin()): ?><th>Action</th><?php endif; ?>
      </tr>
      <?php foreach ($borrowed_books as $i => $b):
        $ov = (new DateTime($b['due_date'])) < new DateTime();
      ?>
      <tr <?= $ov ? 'class="overdue-row"' : '' ?>>
        <td><?= $i+1 ?></td>
        <?php if (isAdmin()): ?><td><strong><?= sanitize($b['user_name']) ?></strong></td><?php endif; ?>
        <td><?= sanitize($b['book_title']) ?></td>
        <td><?= sanitize($b['author']) ?></td>
        <td><?= $b['date_borrowed'] ?></td>
        <td>
          <?= $b['due_date'] ?>
          <?php if ($ov): ?><br><span class="badge badge-red">Overdue!</span><?php endif; ?>
        </td>
        <td>₱<?= number_format($b['fee'], 2) ?></td>
        <td><span class="badge badge-yellow">Borrowed</span></td>

        <?php if (isAdmin()): ?>
        <td>
          <button class="btn btn-green btn-sm"
            onclick="document.getElementById('returnId').value=<?= $b['id'] ?>;openModal('returnModal')">
            Return
          </button>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════
     PAGE: RESERVATIONS / WAITLIST
════════════════════════════════════════ -->
<?php elseif ($action === 'reservations'): ?>
<div class="page-title">⏳ <?= isAdmin() ? 'All Member Reservations' : 'My Book Reservations' ?></div>

<div class="card">
  <?php if (empty($reservations)): ?>
  <div class="empty">
    <div class="empty-icon">⏳</div>
    No active reservations found.
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr>
        <th>#</th>
        <?php if (isAdmin()): ?><th>Member</th><?php endif; ?>
        <th>Book Title</th>
        <th>Reserved Date</th>
        <th>Availability</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
      <?php foreach ($reservations as $i => $r): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <?php if (isAdmin()): ?><td><strong><?= sanitize($r['user_name']) ?></strong></td><?php endif; ?>
        <td><?= sanitize($r['book_title']) ?></td>
        <td><?= date('M d, Y h:i A', strtotime($r['reserved_at'])) ?></td>
        <td>
          <?php if ($r['copies'] > 0): ?>
            <span class="badge badge-green">In Stock (Ready for pickup)</span>
          <?php else: ?>
            <span class="badge badge-yellow">On Hold / Waitlisted</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($r['status'] === 'pending'): ?>
            <span class="badge badge-yellow">Pending</span>
          <?php elseif ($r['status'] === 'claimed'): ?>
            <span class="badge badge-green">Claimed</span>
          <?php else: ?>
            <span class="badge badge-gray"><?= ucfirst($r['status']) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($r['status'] === 'pending'): ?>
            <a href="?action=cancel_reservation&id=<?= $r['id'] ?>" class="btn btn-red btn-sm" onclick="return confirm('Cancel this reservation?')">Cancel</a>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════
     PANEL 1: ADMIN SETTINGS PANEL (Admin only)
════════════════════════════════════════ -->
<?php elseif ($action === 'settings' && isAdmin()): ?>
<div class="page-title">⚙️ Admin Settings Panel</div>

<div class="card">
  <div class="section-title" style="margin-bottom:.8rem">📅 Loan Duration Setting</div>
  <p style="font-size:.88rem;color:var(--text-sm);margin-bottom:1rem">
    Configure how many days a book can be borrowed before it's due, instead of it being fixed in the code.
  </p>
  <div class="form-group" style="max-width:300px;margin-bottom:1rem">
    <label>Default Loan Duration (Days)</label>
    <input type="number" value="7" min="1" max="30">
  </div>
  <button class="btn btn-blue">Save Loan Settings</button>
</div>

<div class="card">
  <div class="section-title" style="margin-bottom:.8rem">📢 Announcement Banner Editor</div>
  <p style="font-size:.88rem;color:var(--text-sm);margin-bottom:1rem">
    Post a site-wide notice (e.g., "Library closed this weekend") that shows on every user's dashboard.
  </p>
  <div class="form-group" style="margin-bottom:1rem">
    <label>Banner Text</label>
    <textarea placeholder="Enter announcement text here..."></textarea>
  </div>
  <button class="btn btn-blue">Publish Notice</button>
</div>

<div class="card">
  <div class="section-title" style="margin-bottom:.8rem">📜 Admin Activity Log</div>
  <p style="font-size:.88rem;color:var(--text-sm);margin-bottom:1rem">
    A record of what each admin/librarian changed (books added, fines adjusted, roles changed) for accountability.
  </p>
  <div class="table-wrap">
    <table>
      <tr><th>Admin</th><th>Action</th><th>Timestamp</th></tr>
      <tr><td>Administrator</td><td>Added new copy for "1984"</td><td>2026-09-24 10:15 AM</td></tr>
      <tr><td>Administrator</td><td>Marked penalty paid for Kareeena</td><td>2026-09-23 02:40 PM</td></tr>
    </table>
  </div>
</div>

<!-- ═══════════════════════════════════════
     PANEL 3: ANALYTICS PANEL (Admin only)
════════════════════════════════════════ -->
<?php elseif ($action === 'analytics' && isAdmin()): ?>
<div class="page-title">📈 Analytics Panel</div>

<div class="card">
  <div class="section-title" style="margin-bottom:.8rem">📊 Overdue Trend Chart</div>
  <p style="font-size:.88rem;color:var(--text-sm);margin-bottom:1rem">
    Tracks how overdue return rates change month to month, to spot patterns.
  </p>
  <div style="padding:2rem;background:var(--gray-lt);border-radius:8px;text-align:center;font-weight:700;color:var(--gray)">
    [ Overdue Rate Analytics Chart Placeholder — Jan: 12% | Feb: 8% | Mar: 5% ]
  </div>
</div>

<div class="card">
  <div class="section-title" style="margin-bottom:.8rem">🏆 Most Active Members List</div>
  <p style="font-size:.88rem;color:var(--text-sm);margin-bottom:1rem">
    Highlights top borrowers, useful for engagement recognition or targeted outreach.
  </p>
  <div class="table-wrap">
    <table>
      <tr><th>Rank</th><th>Member Name</th><th>Total Borrowed Books</th></tr>
      <tr><td>#1</td><td>Doson Garcia</td><td>6 Books</td></tr>
      <tr><td>#2</td><td>Kareena</td><td>4 Books</td></tr>
      <tr><td>#3</td><td>Kai</td><td>2 Books</td></tr>
    </table>
  </div>
</div>

<div class="card">
  <div class="section-title" style="margin-bottom:.8rem">📥 Export to PDF/Excel</div>
  <p style="font-size:.88rem;color:var(--text-sm);margin-bottom:1rem">
    Lets admin download any report or chart for offline use or printing.
  </p>
  <div style="display:flex;gap:1rem">
    <button class="btn btn-blue">Export as PDF</button>
    <button class="btn btn-green">Export as Excel (.xlsx)</button>
  </div>
</div>

<!-- ═══════════════════════════════════════
     PANEL 4: MANAGE USERS PANEL (Admin only)
════════════════════════════════════════ -->
<?php elseif ($action === 'users' && isAdmin()): ?>
<div class="page-title">👥 Manage Users Panel</div>

<div class="card">
  <div class="section-title" style="margin-bottom:.8rem">🔍 Search / Filter Users</div>
  <p style="font-size:.88rem;color:var(--text-sm);margin-bottom:1rem">
    Lets admin quickly find an account by name, email, or role instead of scrolling a full list.
  </p>
  <div class="search-bar">
    <input type="text" placeholder="Search by name or email...">
    <button class="btn btn-blue">Search</button>
  </div>
</div>

<div class="card">
  <div class="section-header">
    <div class="section-title">⚡ Bulk Actions & Borrowing Limit per User</div>
    <button class="btn btn-red btn-sm">Suspend Selected Accounts</button>
  </div>
  <p style="font-size:.88rem;color:var(--text-sm);margin-bottom:1rem">
    Select multiple accounts at once to suspend, activate, or delete them together, and cap how many books a specific member can borrow at once.
  </p>
  <div class="table-wrap">
    <table>
      <tr>
        <th><input type="checkbox"></th>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Borrow Limit</th>
        <th>Action</th>
      </tr>
      <?php foreach ($all_users as $u): ?>
      <tr>
        <td><input type="checkbox"></td>
        <td><strong><?= sanitize($u['name']) ?></strong></td>
        <td><?= sanitize($u['email']) ?></td>
        <td><span class="badge <?= $u['role']==='admin'?'role-admin':'role-user' ?>"><?= $u['role'] ?></span></td>
        <td><input type="number" value="3" style="width:60px;padding:.2rem .4rem"> books</td>
        <td>
          <button class="btn btn-gray btn-sm">Edit</button>
          <button class="btn btn-red btn-sm">Suspend</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<!-- ═══════════════════════════════════════
     OTHER PAGES (Returned, Report, Profile)
════════════════════════════════════════ -->
<?php elseif ($action === 'returned' && isAdmin()): ?>
<div class="page-title">✅ Returned Books</div>
<div class="card">
  <?php if (empty($returned_books)): ?>
  <div class="empty"><div class="empty-icon">📬</div>No returned books yet.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <tr><th>#</th><th>Member</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Fee</th><th>Penalty</th><th>Date Paid</th></tr>
      <?php foreach ($returned_books as $i => $b): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><strong><?= sanitize($b['user_name']) ?></strong></td>
        <td><?= sanitize($b['book_title']) ?></td>
        <td><?= $b['date_borrowed'] ?></td>
        <td><?= $b['due_date'] ?></td>
        <td><?= $b['date_returned'] ?></td>
        <td>₱<?= number_format($b['fee'],2) ?></td>
        <td>
          <?php if ($b['penalty'] > 0): ?>
            <span style="color:var(--red);font-weight:700">₱<?= number_format($b['penalty'],2) ?></span>
          <?php else: ?>
            <span class="badge badge-green">None</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($b['date_paid']): ?>
            <span class="badge badge-green"><?= $b['date_paid'] ?></span>
          <?php elseif ($b['penalty'] > 0): ?>
            <span class="badge badge-red">Unpaid</span>
          <?php else: ?>
            <span class="badge badge-gray">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($action === 'report' && isAdmin()): ?>
<div class="page-title">📊 Full Report</div>
<div class="card">
  <div class="table-wrap">
    <table>
      <tr><th>#</th><th>Member</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Fee</th><th>Penalty</th><th>Action</th></tr>
      <?php foreach ($report_data as $i => $r): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><strong><?= sanitize($r['user_name']) ?></strong></td>
        <td><?= sanitize($r['book_title']) ?></td>
        <td><?= $r['date_borrowed'] ?></td>
        <td><?= $r['due_date'] ?></td>
        <td><?= $r['date_returned'] ?? '—' ?></td>
        <td>₱<?= number_format($r['fee'],2) ?></td>
        <td>₱<?= number_format($r['penalty'],2) ?></td>
        <td>
          <?php if ($r['penalty']>0 && !$r['date_paid']): ?>
            <a href="?action=mark_paid&id=<?= $r['id'] ?>" class="btn btn-green btn-sm" onclick="return confirm('Mark as paid?')">Mark Paid</a>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php elseif ($action === 'profile' && isLoggedIn()): ?>
<div class="page-title">👤 My Profile</div>
<div class="card">
  <div class="profile-top">
    <div class="avatar"><?= strtoupper(substr($current_user['name'],0,1)) ?></div>
    <div>
      <div style="font-weight:800;font-size:1.05rem"><?= sanitize($current_user['name']) ?></div>
      <div style="color:var(--text-sm);font-size:.85rem"><?= sanitize($current_user['email']) ?></div>
    </div>
  </div>
  <form method="POST" action="?action=update_profile">
    <div class="form-grid" style="margin-bottom:.8rem">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" value="<?= sanitize($current_user['name']) ?>" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" value="<?= sanitize($current_user['email']) ?>" disabled style="background:var(--gray-lt)">
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" value="<?= sanitize($current_user['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Address</label>
        <textarea name="address"><?= sanitize($current_user['address'] ?? '') ?></textarea>
      </div>
    </div>
    <button class="btn btn-blue" type="submit">💾 Save Changes</button>
  </form>
</div>

<?php endif; ?>

</div>

<?php endif; ?>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-bg').forEach(function(m) {
  m.addEventListener('click', function(e) {
    if (e.target === m) m.classList.remove('open');
  });
});
</script>
</body>
</html>