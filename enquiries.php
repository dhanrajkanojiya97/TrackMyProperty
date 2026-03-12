<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

require_login();

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentUserName = (string) ($_SESSION['user_name'] ?? 'User');

$stmt = $pdo->prepare(
    'SELECT
        e.*,
        p.title AS property_title,
        p.city AS property_city,
        r.title AS rent_title,
        r.city AS rent_city,
        r.locality AS rent_locality
     FROM enquiries e
     LEFT JOIN properties p ON p.id = e.property_id
     LEFT JOIN rent_listings r ON r.id = e.rent_listing_id
     WHERE e.owner_id = ?
     ORDER BY e.created_at DESC, e.id DESC'
);
$stmt->execute([$currentUserId]);
$enquiries = $stmt->fetchAll();

$saleCount = 0;
$rentCount = 0;

foreach ($enquiries as $enquiry) {
    if (($enquiry['listing_type'] ?? '') === 'rent') {
        $rentCount++;
    } else {
        $saleCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enquiries | TrackMyProperty</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --brand:#be185d;
  --muted:#6b7280;
  --dark:#374151;
}

*{
  margin:0;
  padding:0;
  box-sizing:border-box;
  font-family:"Outfit", sans-serif;
}

body{
  background:linear-gradient(120deg,#fff1f2,#fff7ed);
  color:var(--dark);
}

nav{
  background:rgba(255,255,255,.84);
  backdrop-filter:blur(10px);
  padding:15px 60px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:16px;
  flex-wrap:wrap;
  box-shadow:0 10px 25px rgba(0,0,0,.05);
}

.logo{
  font-size:26px;
  font-weight:700;
  color:var(--brand);
}

.logo span{
  color:var(--brand);
}

.nav-links{
  display:flex;
  align-items:center;
  gap:10px 18px;
  flex-wrap:wrap;
}

.nav-links a{
  display:inline-flex;
  align-items:center;
  text-decoration:none;
  color:#444;
}

.nav-links a:hover,
.nav-links a.is-active{
  color:var(--brand);
}

.user-chip{
  padding:8px 14px;
  border-radius:999px;
  background:rgba(190,24,93,.08);
  color:var(--brand);
  font-size:14px;
  font-weight:600;
}

.logout-link{
  padding:8px 14px;
  border-radius:999px;
  border:1px solid rgba(190,24,93,.16);
}

header{
  text-align:center;
  padding:84px 20px 62px;
  background:linear-gradient(135deg,#fce7f3,#ffedd5);
  border-bottom-left-radius:80px;
  border-bottom-right-radius:80px;
}

header h1{
  font-size:44px;
  color:var(--brand);
}

header p{
  max-width:760px;
  margin:14px auto 0;
  color:var(--muted);
}

.container{
  max-width:1180px;
  margin:0 auto;
  padding:52px 24px 80px;
}

.stats-grid{
  display:grid;
  grid-template-columns:repeat(3, minmax(0, 1fr));
  gap:16px;
  margin-bottom:26px;
}

.stat-card{
  padding:22px;
  border-radius:24px;
  background:rgba(255,255,255,.82);
  box-shadow:0 18px 34px rgba(0,0,0,.06);
}

.stat-card strong{
  display:block;
  font-size:30px;
  color:var(--brand);
}

.stat-card span{
  color:var(--muted);
}

.panel{
  background:rgba(255,255,255,.82);
  border-radius:28px;
  padding:28px;
  box-shadow:0 20px 40px rgba(0,0,0,.07);
}

.panel-head{
  display:flex;
  justify-content:space-between;
  align-items:end;
  gap:20px;
  margin-bottom:18px;
}

.panel-head h2{
  color:var(--brand);
}

.panel-head p{
  color:var(--muted);
}

.enquiry-list{
  display:grid;
  gap:16px;
}

.enquiry-card{
  padding:22px;
  border-radius:24px;
  background:linear-gradient(135deg,rgba(255,255,255,.96),rgba(252,231,243,.7));
  border:1px solid rgba(190,24,93,.08);
}

.enquiry-top{
  display:flex;
  justify-content:space-between;
  align-items:start;
  gap:14px;
  margin-bottom:12px;
}

.type-pill{
  display:inline-flex;
  padding:8px 12px;
  border-radius:999px;
  background:rgba(190,24,93,.1);
  color:#9d174d;
  font-size:12px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.05em;
}

.enquiry-meta{
  color:var(--muted);
  font-size:14px;
}

.enquiry-card h3{
  margin:8px 0 6px;
  font-size:24px;
  color:#111827;
}

.listing-link{
  display:inline-flex;
  margin-top:12px;
  text-decoration:none;
  color:var(--brand);
  font-weight:700;
}

.contact-grid{
  display:grid;
  grid-template-columns:repeat(3, minmax(0, 1fr));
  gap:12px;
  margin-top:16px;
}

.contact-box{
  padding:14px 16px;
  border-radius:18px;
  background:white;
}

.contact-box span{
  display:block;
  margin-bottom:6px;
  font-size:12px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.05em;
  color:#9d174d;
}

.enquiry-message{
  margin-top:16px;
  color:var(--muted);
  line-height:1.7;
}

.empty{
  padding:30px 20px;
  border-radius:24px;
  text-align:center;
  background:rgba(255,255,255,.76);
  color:var(--muted);
}

footer{
  margin-top:80px;
  background:#0f0f0f;
  color:#d1d5db;
  text-align:center;
  padding:44px 20px;
}

@media (max-width: 768px){
  nav{
    padding:14px 16px;
    align-items:stretch;
  }

  .logo{
    width:100%;
    font-size:22px;
    line-height:1.1;
  }

  .nav-links{
    width:100%;
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:10px;
  }

  .nav-links a,
  .user-chip{
    width:100%;
    min-height:44px;
    padding:10px 12px;
    justify-content:center;
    text-align:center;
    border-radius:14px;
    background:rgba(255,255,255,.94);
    border:1px solid rgba(190,24,93,.14);
  }

  .user-chip{
    display:flex;
    align-items:center;
    justify-content:center;
    grid-column:1 / -1;
    white-space:normal;
    word-break:break-word;
  }

  .container{
    padding:32px 20px 56px;
  }

  header{
    padding:70px 20px 56px;
    border-bottom-left-radius:42px;
    border-bottom-right-radius:42px;
  }

  .stats-grid,
  .contact-grid{
    grid-template-columns:1fr;
  }

  .panel-head,
  .enquiry-top{
    flex-direction:column;
    align-items:flex-start;
  }

  .listing-link{
    width:100%;
  }
}

@media (max-width: 480px){
  .nav-links{
    grid-template-columns:1fr;
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
    <a class="is-active" href="enquiries.php">Enquiries</a>
    <a class="user-chip" href="profile.php"><?php echo h($currentUserName); ?></a>
    <a class="logout-link" href="logout.php">Logout</a>
  </div>
</nav>

<header>
  <h1>Received Enquiries</h1>
  <p>All incoming sale and rent leads for your listings are collected here in one simple view.</p>
</header>

<div class="container">
  <div class="stats-grid">
    <div class="stat-card">
      <strong><?php echo count($enquiries); ?></strong>
      <span>Total enquiries</span>
    </div>
    <div class="stat-card">
      <strong><?php echo $saleCount; ?></strong>
      <span>Sale enquiries</span>
    </div>
    <div class="stat-card">
      <strong><?php echo $rentCount; ?></strong>
      <span>Rent enquiries</span>
    </div>
  </div>

  <section class="panel">
    <div class="panel-head">
      <div>
        <h2>Latest Leads</h2>
        <p>Each enquiry is tied directly to one of your saved listings.</p>
      </div>
    </div>

    <?php if (!$enquiries): ?>
      <div class="empty">No enquiries have been received yet.</div>
    <?php else: ?>
      <div class="enquiry-list">
        <?php foreach ($enquiries as $enquiry): ?>
          <?php
            $isRent = (string) ($enquiry['listing_type'] ?? '') === 'rent';
            $listingTitle = $isRent
              ? (string) ($enquiry['rent_title'] ?? 'Rental listing')
              : (string) ($enquiry['property_title'] ?? 'Property listing');
            $locationLabel = $isRent
              ? trim((string) ($enquiry['rent_locality'] ?? '') . ', ' . (string) ($enquiry['rent_city'] ?? ''))
              : (string) ($enquiry['property_city'] ?? '');
            $detailHref = $isRent
              ? 'rent-details.php?id=' . (int) ($enquiry['rent_listing_id'] ?? 0)
              : 'property-details.php?id=' . (int) ($enquiry['property_id'] ?? 0);
            $createdLabel = date('d M Y, h:i A', strtotime((string) ($enquiry['created_at'] ?? 'now')));
            $message = trim((string) ($enquiry['message'] ?? ''));
          ?>
          <article class="enquiry-card">
            <div class="enquiry-top">
              <div>
                <span class="type-pill"><?php echo $isRent ? 'Rent Lead' : 'Sale Lead'; ?></span>
                <h3><?php echo h($listingTitle); ?></h3>
                <p class="enquiry-meta"><?php echo h($locationLabel !== '' ? $locationLabel : 'Location not available'); ?> • Received <?php echo h($createdLabel); ?></p>
              </div>
              <a class="listing-link" href="<?php echo h($detailHref); ?>">Open Listing</a>
            </div>

            <div class="contact-grid">
              <div class="contact-box">
                <span>Name</span>
                <strong><?php echo h((string) ($enquiry['sender_name'] ?? '')); ?></strong>
              </div>
              <div class="contact-box">
                <span>Phone</span>
                <strong><?php echo h((string) ($enquiry['sender_phone'] ?? '')); ?></strong>
              </div>
              <div class="contact-box">
                <span>Email</span>
                <strong><?php echo h((string) ($enquiry['sender_email'] ?? 'Not provided')); ?></strong>
              </div>
            </div>

            <p class="enquiry-message"><?php echo h($message !== '' ? $message : 'No message added.'); ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<footer>
  <p>© 2026 TrackMyProperty. Lead capture and review from the live database.</p>
</footer>
</body>
</html>
