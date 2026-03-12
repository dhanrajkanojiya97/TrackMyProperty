<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/enquiry-helpers.php';

$isLoggedIn = is_logged_in();
$currentUserName = (string) ($_SESSION['user_name'] ?? 'User');
$rentListingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$requestMethod = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
const RENT_FALLBACK_IMAGE = 'https://images.pexels.com/photos/17226654/pexels-photo-17226654.jpeg?auto=compress&cs=tinysrgb&w=1200';

function format_money_value($amount): string
{
    return '₹' . number_format((float) $amount);
}

$rental = null;
$similarRentals = [];
$enquiryErrors = [];
$enquiryFlash = '';
$enquiryForm = enquiry_form_defaults($_SESSION);
$rentalImages = [];

if ($rentListingId > 0) {
    $stmt = $pdo->prepare(
        'SELECT
            r.*,
            a.agent_code,
            a.full_name AS agent_name,
            a.phone AS agent_phone,
            a.service_area AS agent_service_area,
            a.experience_years AS agent_experience_years
         FROM rent_listings r
         LEFT JOIN agents a ON a.user_id = r.owner_id
         WHERE r.id = ?
         LIMIT 1'
    );
    $stmt->execute([$rentListingId]);
    $rental = $stmt->fetch();

    if ($rental) {
        $similarStmt = $pdo->prepare(
            'SELECT id, title, city, locality, rent, beds, baths, area, type, furnish, image_path
             FROM rent_listings
             WHERE id <> ?
             ORDER BY CASE WHEN type = ? THEN 0 ELSE 1 END, created_at DESC, id DESC
             LIMIT 3'
        );
        $similarStmt->execute([$rentListingId, (string) ($rental['type'] ?? '')]);
        $similarRentals = $similarStmt->fetchAll();
    }
}

$contactName = '';
$contactPhone = '';
$contactSubtitle = '';
$highlights = [];

if ($rental) {
    $contactName = (string) ($rental['agent_name'] ?? '');
    if ($contactName === '') {
        $contactName = (string) ($rental['owner_name'] ?? 'Owner');
    }

    $contactPhone = (string) ($rental['agent_phone'] ?? '');
    $contactSubtitle = (string) ($rental['agent_code'] ?? '');
    if ($contactSubtitle === '') {
        $contactSubtitle = 'Owner-listed rental';
    }

    $highlights = array_filter([
        (string) ($rental['type'] ?? ''),
        'Bedrooms: ' . (string) ($rental['beds'] ?? ''),
        'Bathrooms: ' . (string) ($rental['baths'] ?? ''),
        'Furnishing: ' . (string) ($rental['furnish'] ?? 'Not specified'),
        'Parking: ' . (string) ($rental['parking'] ?? 'Not specified'),
        'Pet friendly: ' . (string) ($rental['pet_friendly'] ?? 'Not specified'),
        'Available: ' . (string) ($rental['available_from'] ?? 'Not specified'),
        'Tenant type: ' . (string) ($rental['tenant_type'] ?? 'Not specified'),
        'Locality: ' . (string) ($rental['locality'] ?? ''),
    ]);

    $status = (string) ($_GET['status'] ?? '');
    if ($status === 'enquiry-sent') {
        $enquiryFlash = 'Your enquiry was sent successfully.';
    }

    if ($requestMethod === 'POST' && (string) ($_POST['action'] ?? '') === 'send_enquiry') {
        require_csrf_token();
        $enquiryForm = enquiry_form_from_post($_POST, $enquiryForm);
        $enquiryErrors = validate_enquiry_form($enquiryForm);

        if ($isLoggedIn && (int) ($_SESSION['user_id'] ?? 0) === (int) ($rental['owner_id'] ?? 0)) {
            $enquiryErrors[] = 'You cannot send an enquiry to your own rental listing.';
        }

        if (!$enquiryErrors) {
            create_rent_enquiry($pdo, (int) ($rental['owner_id'] ?? 0), (int) ($rental['id'] ?? 0), $enquiryForm);

            header('Location: rent-details.php?id=' . (int) ($rental['id'] ?? 0) . '&status=enquiry-sent');
            exit;
        }
    }

    $rentalImages = image_urls((string) ($rental['image_path'] ?? ''), RENT_FALLBACK_IMAGE);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Rental Details | TrackMyProperty</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<style>
.user-chip{
  padding:8px 14px;
  border-radius:999px;
  background:rgba(190,24,93,.08);
  color:#be185d;
  font-size:14px;
  font-weight:600;
}

.contact-note{
  margin-top:14px;
  color:#6b7280;
  line-height:1.7;
}

.detail-contact-stack{
  display:grid;
  gap:12px;
}

.detail-form-alert,
.detail-form-success{
  margin-bottom:14px;
  padding:12px 14px;
  border-radius:16px;
  font-size:14px;
}

.detail-form-alert{
  background:#fff1f2;
  color:#9f1239;
}

.detail-form-success{
  background:#ecfdf5;
  color:#047857;
}

.detail-form-alert ul{
  margin-left:18px;
}

.spam-status{
  margin-bottom:14px;
  padding:12px 14px;
  border-radius:16px;
  font-size:14px;
  display:none;
}

.spam-status.is-warning{
  display:block;
  background:#fff7ed;
  color:#9a3412;
}

.spam-status.is-ok{
  display:block;
  background:#ecfdf5;
  color:#047857;
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

<main class="detail-page">
<?php if (!$rental): ?>
  <section class="detail-shell">
    <div class="not-found-card">
      <h1>Rental not found</h1>
      <p>The rental you opened is not available in the database right now.</p>
      <a class="view-btn not-found-link" href="rent.php">Back to Rent Listings</a>
    </div>
  </section>
<?php else: ?>
  <section class="detail-shell">
    <div class="detail-topbar">
      <a class="back-link" href="rent.php">Back to Rent Listings</a>
      <span class="detail-badge"><?php echo h((string) ($rental['type'] ?? '')); ?></span>
    </div>

    <section class="detail-hero">
      <div class="detail-gallery">
        <img class="detail-main-image" id="rentalMainImage" src="<?php echo h($rentalImages[0] ?? RENT_FALLBACK_IMAGE); ?>" alt="<?php echo h((string) ($rental['title'] ?? '')); ?>">
        <?php if (count($rentalImages) > 1): ?>
          <div class="detail-thumbs">
            <?php foreach ($rentalImages as $index => $img): ?>
              <button class="detail-thumb<?php echo $index === 0 ? ' is-active' : ''; ?>" type="button" data-image="<?php echo h($img); ?>">
                <img src="<?php echo h($img); ?>" alt="<?php echo h((string) ($rental['title'] ?? '') . ' image ' . ($index + 1)); ?>">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <aside class="detail-summary">
        <p class="detail-eyebrow">Live Rental</p>
        <h1><?php echo h((string) ($rental['title'] ?? '')); ?></h1>
        <div class="detail-price"><?php echo h(format_money_value($rental['rent'] ?? 0)); ?> / month</div>
        <div class="detail-location"><?php echo h((string) ($rental['locality'] ?? '')); ?>, <?php echo h((string) ($rental['city'] ?? '')); ?></div>

        <div class="detail-metrics">
          <div class="metric-card">
            <span class="metric-value"><?php echo (int) ($rental['beds'] ?? 0); ?></span>
            <span class="metric-label">Bedrooms</span>
          </div>
          <div class="metric-card">
            <span class="metric-value"><?php echo (int) ($rental['baths'] ?? 0); ?></span>
            <span class="metric-label">Bathrooms</span>
          </div>
          <div class="metric-card">
            <span class="metric-value"><?php echo h(number_format((float) ($rental['area'] ?? 0))); ?></span>
            <span class="metric-label">Sqft</span>
          </div>
        </div>

        <p class="detail-copy"><?php echo h(trim((string) ($rental['description'] ?? '')) !== '' ? (string) $rental['description'] : 'The owner has not added a long description yet, but the core rent terms are available below.'); ?></p>

        <div class="detail-cta-row">
          <?php if ($contactPhone !== ''): ?>
            <a class="view-btn detail-primary-link" href="tel:<?php echo h($contactPhone); ?>">Call Now</a>
          <?php else: ?>
            <a class="view-btn detail-primary-link" href="agent.php">Browse Agents</a>
          <?php endif; ?>
          <a class="ghost-btn" href="rent.php">Back to Listings</a>
        </div>

        <div class="agent-strip">
          <div>
            <span class="agent-label">Listed by</span>
            <strong><?php echo h($contactName); ?></strong>
          </div>
          <div>
            <span class="agent-label"><?php echo $contactPhone !== '' ? 'Call' : 'Profile'; ?></span>
            <strong><?php echo h($contactPhone !== '' ? $contactPhone : $contactSubtitle); ?></strong>
          </div>
        </div>
      </aside>
    </section>

    <section class="detail-section">
      <div class="detail-section-head">
        <h2>Rental Overview</h2>
        <p>Core rental terms pulled from the live database.</p>
      </div>
      <div class="overview-grid">
        <div class="overview-card">
          <span class="overview-label">Monthly Rent</span>
          <strong><?php echo h(format_money_value($rental['rent'] ?? 0)); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Deposit</span>
          <strong><?php echo h(format_money_value($rental['deposit'] ?? 0)); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Furnishing</span>
          <strong><?php echo h((string) ($rental['furnish'] ?? 'Not specified')); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Available From</span>
          <strong><?php echo h((string) ($rental['available_from'] ?? 'Not specified')); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Tenant Type</span>
          <strong><?php echo h((string) ($rental['tenant_type'] ?? 'Not specified')); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Pet Friendly</span>
          <strong><?php echo h((string) ($rental['pet_friendly'] ?? 'Not specified')); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Parking</span>
          <strong><?php echo h((string) ($rental['parking'] ?? 'Not specified')); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Area</span>
          <strong><?php echo h(number_format((float) ($rental['area'] ?? 0))); ?> sqft</strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Listed On</span>
          <strong><?php echo h(date('d M Y', strtotime((string) ($rental['created_at'] ?? 'now')))); ?></strong>
        </div>
      </div>
    </section>

    <section class="detail-content-grid">
      <div class="detail-panel">
        <h3>Highlights</h3>
        <div class="amenity-list">
          <?php foreach ($highlights as $highlight): ?>
            <span class="amenity-pill"><?php echo h($highlight); ?></span>
          <?php endforeach; ?>
        </div>
        <p class="detail-panel-copy">This page is database-backed, so the rent, deposit, availability, and contact details come from the stored listing.</p>
      </div>

      <div class="detail-panel">
        <h3>Contact And Enquiry</h3>
        <div class="detail-contact-stack">
          <div class="overview-card">
            <span class="overview-label">Name</span>
            <strong><?php echo h($contactName); ?></strong>
          </div>
          <div class="overview-card">
            <span class="overview-label"><?php echo $contactPhone !== '' ? 'Phone' : 'Profile Status'; ?></span>
            <strong><?php echo h($contactPhone !== '' ? $contactPhone : 'No public phone yet'); ?></strong>
          </div>
        </div>
        <p class="contact-note">
          <?php if ($contactPhone !== ''): ?>
            You can call directly to ask about rent terms, move-in timing, or a site visit.
          <?php else: ?>
            The rental is live, but the owner has not added a public agent phone number yet.
          <?php endif; ?>
        </p>

        <?php if ($enquiryFlash !== ''): ?>
          <div class="detail-form-success"><?php echo h($enquiryFlash); ?></div>
        <?php endif; ?>

        <?php if ($enquiryErrors): ?>
          <div class="detail-form-alert">
            <ul>
              <?php foreach ($enquiryErrors as $error): ?>
                <li><?php echo h($error); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div id="spamStatus" class="spam-status" aria-live="polite"></div>
        <form class="detail-form" id="enquiryForm" method="POST" action="rent-details.php?id=<?php echo (int) ($rental['id'] ?? 0); ?>">
          <input type="hidden" name="action" value="send_enquiry">
          <?php echo csrf_input(); ?>
          <input type="text" name="name" placeholder="Your Name" value="<?php echo h($enquiryForm['name']); ?>" required>
          <input type="email" name="email" placeholder="Email (optional)" value="<?php echo h($enquiryForm['email']); ?>">
          <input type="text" name="phone" placeholder="Phone Number" value="<?php echo h($enquiryForm['phone']); ?>" required>
          <textarea name="message" placeholder="Message (optional)"><?php echo h($enquiryForm['message']); ?></textarea>
          <button type="submit" class="view-btn">Send Enquiry</button>
        </form>
      </div>
    </section>

    <section class="detail-section">
      <div class="detail-section-head">
        <h2>Similar Rentals</h2>
        <p>Other live rental listings from the current database.</p>
      </div>
      <?php if (!$similarRentals): ?>
        <div class="empty-state">No similar rentals available yet.</div>
      <?php else: ?>
        <div class="grid">
          <?php foreach ($similarRentals as $similar): ?>
            <div class="card">
              <a class="card-link" href="rent-details.php?id=<?php echo (int) ($similar['id'] ?? 0); ?>">
                <img src="<?php echo h(image_first_url((string) ($similar['image_path'] ?? ''), RENT_FALLBACK_IMAGE)); ?>" class="card-img" alt="<?php echo h((string) ($similar['title'] ?? '')); ?>">
                <div class="card-body">
                  <div class="card-price"><?php echo h(format_money_value($similar['rent'] ?? 0)); ?> / month</div>
                  <div class="card-title"><?php echo h((string) ($similar['title'] ?? '')); ?></div>
                  <div class="card-loc"><?php echo h((string) ($similar['locality'] ?? '')); ?>, <?php echo h((string) ($similar['city'] ?? '')); ?></div>
                  <div class="card-stats">
                    <span><?php echo (int) ($similar['beds'] ?? 0); ?> Beds</span>
                    <span><?php echo (int) ($similar['baths'] ?? 0); ?> Baths</span>
                    <span><?php echo h(number_format((float) ($similar['area'] ?? 0))); ?> sqft</span>
                  </div>
                </div>
              </a>
              <div class="card-actions">
                <a class="view-btn details-link" href="rent-details.php?id=<?php echo (int) ($similar['id'] ?? 0); ?>">View Details</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </section>
<?php endif; ?>
</main>

<footer>
  <div class="copyright">
    © 2026 TrackMyProperty. Live rental details from the current database.
  </div>
</footer>
<script>
const rentalMainImage = document.getElementById('rentalMainImage');
const rentalThumbs = document.querySelectorAll('.detail-thumb');

if (rentalMainImage && rentalThumbs.length) {
  rentalThumbs.forEach((thumb) => {
    thumb.addEventListener('click', () => {
      const nextImage = thumb.dataset.image;
      if (nextImage) {
        rentalMainImage.src = nextImage;
      }
      rentalThumbs.forEach((btn) => btn.classList.remove('is-active'));
      thumb.classList.add('is-active');
    });
  });
}

const FLASK_BASE = 'http://127.0.0.1:5000';
const enquiryForm = document.getElementById('enquiryForm');
const spamStatus = document.getElementById('spamStatus');

const setSpamStatus = (message, tone) => {
  if (!spamStatus) {
    return;
  }
  spamStatus.textContent = message;
  spamStatus.classList.remove('is-warning', 'is-ok');
  if (tone === 'ok') {
    spamStatus.classList.add('is-ok');
  } else if (tone === 'warning') {
    spamStatus.classList.add('is-warning');
  }
  spamStatus.style.display = 'block';
};

if (enquiryForm) {
  let bypass = false;
  enquiryForm.addEventListener('submit', async (event) => {
    if (bypass) {
      return;
    }
    event.preventDefault();

    const payload = {
      name: enquiryForm.querySelector('[name="name"]')?.value || '',
      email: enquiryForm.querySelector('[name="email"]')?.value || '',
      phone: enquiryForm.querySelector('[name="phone"]')?.value || '',
      message: enquiryForm.querySelector('[name="message"]')?.value || '',
    };

    setSpamStatus('Checking your message with the Flask spam service...', 'warning');

    try {
      const response = await fetch(`${FLASK_BASE}/api/spam-check`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        throw new Error('Bad response');
      }

      const data = await response.json();
      if (data.is_spam) {
        const reasons = Array.isArray(data.reasons) && data.reasons.length
          ? ` (${data.reasons.join(', ')})`
          : '';
        setSpamStatus(`Message looks like spam. Please revise before sending${reasons}.`, 'warning');
        return;
      }

      setSpamStatus('Spam check passed. Sending enquiry...', 'ok');
      bypass = true;
      enquiryForm.submit();
    } catch (error) {
      setSpamStatus('Spam check is unavailable right now. Sending enquiry anyway.', 'warning');
      bypass = true;
      enquiryForm.submit();
    }
  });
}
</script>
</body>
</html>
