<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

$specializations = ['Buy', 'Rent', 'Sell'];
$isLoggedIn = is_logged_in();
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentUserName = (string) ($_SESSION['user_name'] ?? 'User');

function fetch_agent_by_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM agents WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $agent = $stmt->fetch();

    return $agent ?: null;
}

function generate_agent_code(PDO $pdo): string
{
    do {
        $code = 'AGT-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare('SELECT id FROM agents WHERE agent_code = ? LIMIT 1');
        $stmt->execute([$code]);
        $exists = $stmt->fetchColumn();
    } while ($exists);

    return $code;
}

function agent_listing_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM properties WHERE owner_id = ?');
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

$agentProfile = $isLoggedIn ? fetch_agent_by_user($pdo, $currentUserId) : null;
$hasAgentProfile = $agentProfile !== null;
$errors = [];
$flashMessage = '';
$flashType = '';
$form = [
    'full_name' => $hasAgentProfile ? (string) ($agentProfile['full_name'] ?? '') : $currentUserName,
    'phone' => $hasAgentProfile ? (string) ($agentProfile['phone'] ?? '') : '',
    'city' => $hasAgentProfile ? (string) ($agentProfile['city'] ?? '') : '',
    'service_area' => $hasAgentProfile ? (string) ($agentProfile['service_area'] ?? '') : '',
    'specialization' => $hasAgentProfile ? (string) ($agentProfile['specialization'] ?? '') : '',
    'experience_years' => $hasAgentProfile ? (string) ($agentProfile['experience_years'] ?? '0') : '',
    'bio' => $hasAgentProfile ? (string) ($agentProfile['bio'] ?? '') : '',
];

$status = (string) ($_GET['status'] ?? '');
$statusMessages = [
    'created' => ['Agent profile created successfully.', 'success'],
    'updated' => ['Agent profile updated successfully.', 'success'],
];
if (isset($statusMessages[$status])) {
    [$flashMessage, $flashType] = $statusMessages[$status];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    if (!$isLoggedIn) {
        header('Location: login.php');
        exit;
    }

    $agentProfile = fetch_agent_by_user($pdo, $currentUserId);
    $hasAgentProfile = $agentProfile !== null;
    $form = [
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'city' => trim((string) ($_POST['city'] ?? '')),
        'service_area' => trim((string) ($_POST['service_area'] ?? '')),
        'specialization' => trim((string) ($_POST['specialization'] ?? '')),
        'experience_years' => trim((string) ($_POST['experience_years'] ?? '')),
        'bio' => trim((string) ($_POST['bio'] ?? '')),
    ];

    if ($form['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($form['phone'] === '' || !preg_match('/^\+?[0-9][0-9\s-]{8,17}$/', $form['phone'])) {
        $errors[] = 'Enter a valid phone number.';
    }
    if ($form['city'] === '') {
        $errors[] = 'City is required.';
    }
    if ($form['service_area'] === '') {
        $errors[] = 'Service area is required.';
    }
    if ($form['specialization'] === '' || !in_array($form['specialization'], $specializations, true)) {
        $errors[] = 'Select a valid specialization.';
    }
    if ($form['experience_years'] === '' || !ctype_digit($form['experience_years'])) {
        $errors[] = 'Experience must be a whole number.';
    } elseif ((int) $form['experience_years'] > 60) {
        $errors[] = 'Experience looks too high. Keep it realistic.';
    }
    if (strlen($form['bio']) > 500) {
        $errors[] = 'Bio must be 500 characters or fewer.';
    }

    if (!$errors) {
        if ($hasAgentProfile) {
            $stmt = $pdo->prepare(
                'UPDATE agents
                    SET full_name = ?, phone = ?, city = ?, service_area = ?, specialization = ?, experience_years = ?, bio = ?
                  WHERE user_id = ?'
            );
            $stmt->execute([
                $form['full_name'],
                $form['phone'],
                $form['city'],
                $form['service_area'],
                $form['specialization'],
                (int) $form['experience_years'],
                $form['bio'],
                $currentUserId,
            ]);

            header('Location: agent.php?status=updated');
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO agents
                (user_id, agent_code, full_name, phone, city, service_area, specialization, experience_years, bio)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $currentUserId,
            generate_agent_code($pdo),
            $form['full_name'],
            $form['phone'],
            $form['city'],
            $form['service_area'],
            $form['specialization'],
            (int) $form['experience_years'],
            $form['bio'],
        ]);

        header('Location: agent.php?status=created');
        exit;
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$selectedSpecialization = trim((string) ($_GET['specialization'] ?? ''));

$sql = 'SELECT
            a.*,
            (SELECT COUNT(*) FROM properties p WHERE p.owner_id = a.user_id) AS listing_count
        FROM agents a';
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(a.full_name LIKE ? OR a.city LIKE ? OR a.service_area LIKE ? OR a.agent_code LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($selectedSpecialization !== '' && in_array($selectedSpecialization, $specializations, true)) {
    $where[] = 'a.specialization = ?';
    $params[] = $selectedSpecialization;
} else {
    $selectedSpecialization = '';
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY a.created_at DESC, a.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agents = $stmt->fetchAll();
$resultCount = count($agents);
$myListingCount = $isLoggedIn ? agent_listing_count($pdo, $currentUserId) : 0;
$profileAgentCode = $hasAgentProfile ? (string) ($agentProfile['agent_code'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agents | TrackMyProperty</title>
<link rel="stylesheet" href="style.css">
<style>
.agent-page{
  max-width:1200px;
  margin:0 auto;
  padding:48px 60px 0;
}

.user-chip{
  padding:8px 14px;
  border-radius:999px;
  background:rgba(190,24,93,.08);
  color:#be185d;
  font-size:14px;
  font-weight:600;
}

.agent-layout{
  display:grid;
  grid-template-columns:minmax(0, 1.2fr) minmax(320px, .85fr);
  gap:28px;
  align-items:start;
}

.agent-panel{
  background:rgba(255,255,255,.82);
  border-radius:28px;
  padding:26px;
  box-shadow:0 20px 40px rgba(0,0,0,.07);
}

.agent-panel h2{
  color:#be185d;
  margin-bottom:8px;
}

.agent-panel-copy{
  color:#6b7280;
  margin-bottom:18px;
}

.filter-strip input{
  min-width:220px;
  padding:12px 16px;
  border:1px solid rgba(236,72,153,.18);
  border-radius:16px;
  background:rgba(255,255,255,.92);
  color:var(--dark);
}

.result-meta{
  margin-bottom:18px;
  color:#6b7280;
  font-size:14px;
}

.agent-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
  gap:22px;
}

.agent-card{
  background:rgba(255,255,255,.86);
  border-radius:24px;
  padding:22px;
  box-shadow:0 14px 28px rgba(0,0,0,.06);
}

.agent-card-top{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  margin-bottom:16px;
}

.agent-code{
  padding:8px 12px;
  border-radius:999px;
  background:rgba(190,24,93,.1);
  color:#9d174d;
  font-size:12px;
  font-weight:700;
  letter-spacing:.04em;
}

.agent-specialization{
  color:#be185d;
  font-weight:700;
}

.agent-name{
  font-size:22px;
  color:#111827;
  margin-bottom:4px;
}

.agent-location{
  color:#6b7280;
  margin-bottom:14px;
}

.agent-facts{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-bottom:14px;
}

.agent-facts span,
.agent-chip{
  padding:8px 12px;
  border-radius:999px;
  background:rgba(252,231,243,.78);
  color:#9d174d;
  font-size:12px;
  font-weight:600;
}

.agent-bio{
  color:#6b7280;
  line-height:1.7;
  min-height:72px;
}

.agent-actions{
  margin-top:16px;
}

.profile-meta{
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:12px;
  margin-bottom:18px;
}

.meta-card{
  padding:16px;
  border-radius:20px;
  background:linear-gradient(135deg, rgba(252,231,243,.95), rgba(255,237,213,.95));
}

.meta-card strong{
  display:block;
  color:#be185d;
  font-size:22px;
}

.meta-card span{
  color:#6b7280;
  font-size:14px;
}

.agent-form{
  display:grid;
  gap:12px;
}

.agent-form input,
.agent-form select,
.agent-form textarea{
  width:100%;
  padding:14px 16px;
  border-radius:16px;
  border:1px solid #f1f5f9;
  background:white;
}

.agent-form textarea{
  min-height:130px;
  resize:vertical;
}

.agent-form input:focus,
.agent-form select:focus,
.agent-form textarea:focus{
  outline:none;
  border-color:#be185d;
  box-shadow:0 0 0 3px rgba(190,24,93,.14);
}

.flash,
.error-list{
  margin-bottom:16px;
  padding:14px 16px;
  border-radius:16px;
  font-size:14px;
}

.flash.success{
  background:#ecfdf5;
  color:#047857;
}

.error-list{
  background:#fff1f2;
  color:#9f1239;
}

.error-list ul{
  margin-left:18px;
}

.agent-code-box{
  padding:14px 16px;
  border-radius:18px;
  background:rgba(252,231,243,.65);
  color:#9d174d;
  font-weight:700;
}

.cta-stack{
  display:flex;
  gap:12px;
  flex-wrap:wrap;
}

.cta-stack .view-btn,
.cta-stack .ghost-btn{
  flex:1;
}

@media (max-width: 980px){
  .agent-layout{
    grid-template-columns:1fr;
  }
}

@media (max-width: 768px){
  .agent-page{
    padding:32px 20px 0;
  }

  .agent-panel{
    padding:22px;
  }

  .filter-strip input{
    width:100%;
  }

  .agent-card-top,
  .cta-stack{
    flex-direction:column;
    align-items:flex-start;
  }

  .profile-meta{
    grid-template-columns:1fr;
  }

  .cta-stack .view-btn,
  .cta-stack .ghost-btn{
    width:100%;
  }
}
</style>
</head>
<body>

<nav>
  <div class="logo">TrackMy<span>Property</span></div>
  <div class="nav-links">
    <a href="buy.php">Buy</a>
    <a href="rent.php">Rent</a>
    <a href="sell.php">Sell</a>
    <a href="agent.php">Agents</a>
    <?php if ($isLoggedIn): ?>
      <a href="enquiries.php">Enquiries</a>
      <a class="user-chip" href="profile.php"><?php echo h($currentUserName); ?></a>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a>
      <a href="register.php">Register</a>
    <?php endif; ?>
  </div>
</nav>

<header>
  <h1>Find Local Agents</h1>
  <p>Search city, service area, or agent code. If you already have an account, you can create a public agent profile without typing a manual ID.</p>
</header>

<main class="agent-page">
  <div class="agent-layout">
    <section>
      <div class="agent-panel">
        <form class="filter-strip" method="GET" action="agent.php">
          <span class="filter-label">Search Agents</span>
          <input type="text" name="q" value="<?php echo h($search); ?>" placeholder="Search by name, city, service area or code">
          <select name="specialization" aria-label="Filter by specialization">
            <option value="">Specialization</option>
            <?php foreach ($specializations as $option): ?>
              <option value="<?php echo h($option); ?>" <?php echo $selectedSpecialization === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="filter-apply">Apply</button>
          <a class="filter-reset ghost-btn" href="agent.php">Reset</a>
          <span class="result-count"><?php echo $resultCount; ?> <?php echo $resultCount === 1 ? 'agent' : 'agents'; ?></span>
        </form>

        <div class="section-header">
          <h2>Available Agents</h2>
          <p>Minimal public directory with just the essentials.</p>
        </div>

        <?php if (!$agents): ?>
          <div class="empty-state">No agents match this search yet.</div>
        <?php else: ?>
          <div class="agent-grid">
            <?php foreach ($agents as $agent): ?>
              <?php
                $bio = trim((string) ($agent['bio'] ?? ''));
                $listingCount = (int) ($agent['listing_count'] ?? 0);
              ?>
              <article class="agent-card">
                <div class="agent-card-top">
                  <span class="agent-code"><?php echo h((string) ($agent['agent_code'] ?? '')); ?></span>
                  <span class="agent-specialization"><?php echo h((string) ($agent['specialization'] ?? '')); ?></span>
                </div>
                <h3 class="agent-name"><?php echo h((string) ($agent['full_name'] ?? '')); ?></h3>
                <p class="agent-location"><?php echo h((string) ($agent['service_area'] ?? '')); ?>, <?php echo h((string) ($agent['city'] ?? '')); ?></p>
                <div class="agent-facts">
                  <span><?php echo (int) ($agent['experience_years'] ?? 0); ?> yrs exp</span>
                  <span><?php echo $listingCount; ?> <?php echo $listingCount === 1 ? 'listing' : 'listings'; ?></span>
                </div>
                <p class="agent-bio"><?php echo h($bio !== '' ? $bio : 'No bio added yet. Contact the agent directly for local market help.'); ?></p>
                <div class="agent-actions">
                  <a class="view-btn details-link" href="tel:<?php echo h((string) ($agent['phone'] ?? '')); ?>">Call Agent</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <aside class="agent-panel">
      <?php if ($isLoggedIn): ?>
        <h2><?php echo $hasAgentProfile ? 'Your Agent Profile' : 'Become An Agent'; ?></h2>
        <p class="agent-panel-copy"><?php echo $hasAgentProfile ? 'Your profile is public now. Update it here whenever your service area or contact details change.' : 'Create one clean public profile. The agent ID is generated automatically by the system.'; ?></p>

        <div class="profile-meta">
          <div class="meta-card">
            <strong><?php echo $myListingCount; ?></strong>
            <span>Your property listings</span>
          </div>
          <div class="meta-card">
            <strong><?php echo $hasAgentProfile ? h($profileAgentCode) : 'Pending'; ?></strong>
            <span><?php echo $hasAgentProfile ? 'Your agent code' : 'Generated after save'; ?></span>
          </div>
        </div>

        <?php if ($flashMessage !== ''): ?>
          <div class="flash <?php echo h($flashType); ?>"><?php echo h($flashMessage); ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
          <div class="error-list">
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?php echo h($error); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($hasAgentProfile): ?>
          <div class="agent-code-box"><?php echo h($profileAgentCode); ?></div>
        <?php endif; ?>

        <form class="agent-form" method="POST" action="agent.php">
          <?php echo csrf_input(); ?>
          <input type="text" name="full_name" placeholder="Full Name" value="<?php echo h($form['full_name']); ?>" required>
          <input type="text" name="phone" placeholder="Phone Number" value="<?php echo h($form['phone']); ?>" required>
          <input type="text" name="city" placeholder="City" value="<?php echo h($form['city']); ?>" required>
          <input type="text" name="service_area" placeholder="Service Area / Locality" value="<?php echo h($form['service_area']); ?>" required>

          <select name="specialization" required>
            <option value="">Specialization</option>
            <?php foreach ($specializations as $option): ?>
              <option value="<?php echo h($option); ?>" <?php echo $form['specialization'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
            <?php endforeach; ?>
          </select>

          <input type="number" name="experience_years" min="0" max="60" placeholder="Years of Experience" value="<?php echo h($form['experience_years']); ?>" required>
          <textarea name="bio" placeholder="Short bio (optional)"><?php echo h($form['bio']); ?></textarea>
          <button type="submit"><?php echo $hasAgentProfile ? 'Update Profile' : 'Create Agent Profile'; ?></button>
        </form>
      <?php else: ?>
        <h2>Become An Agent</h2>
        <p class="agent-panel-copy">You need an account before creating a public agent profile. Once logged in, the form stays simple and the ID is generated automatically.</p>
        <div class="cta-stack">
          <a class="view-btn details-link" href="login.php">Login</a>
          <a class="ghost-btn" href="register.php">Register</a>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</main>

<footer>
  <div class="copyright">© 2026 TrackMyProperty. Clean agent search with minimal profile validation.</div>
</footer>
</body>
</html>
