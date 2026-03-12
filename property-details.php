<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/enquiry-helpers.php';

$isLoggedIn = is_logged_in();
$currentUserName = (string) ($_SESSION['user_name'] ?? 'User');
$propertyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$requestMethod = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
const PROPERTY_FALLBACK_IMAGE = 'https://images.pexels.com/photos/30503925/pexels-photo-30503925.jpeg?auto=compress&cs=tinysrgb&w=1200';

function format_price_value($price): string
{
    return '₹' . number_format((float) $price);
}

$property = null;
$similarProperties = [];
$enquiryErrors = [];
$enquiryFlash = '';
$enquiryForm = enquiry_form_defaults($_SESSION);
$propertyImages = [];

if ($propertyId > 0) {
    $stmt = $pdo->prepare(
        'SELECT
            p.*,
            a.agent_code,
            a.full_name AS agent_name,
            a.phone AS agent_phone,
            a.service_area AS agent_service_area,
            a.experience_years AS agent_experience_years
         FROM properties p
         LEFT JOIN agents a ON a.user_id = p.owner_id
         WHERE p.id = ?
         LIMIT 1'
    );
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();

    if ($property) {
        $similarStmt = $pdo->prepare(
            'SELECT id, title, city, price, area, type, beds, furnish, image_path
             FROM properties
             WHERE id <> ?
             ORDER BY CASE WHEN type = ? THEN 0 ELSE 1 END, created_at DESC, id DESC
             LIMIT 3'
        );
        $similarStmt->execute([$propertyId, (string) ($property['type'] ?? '')]);
        $similarProperties = $similarStmt->fetchAll();
    }
}

$contactName = '';
$contactPhone = '';
$contactSubtitle = '';
$highlights = [];

if ($property) {
    $contactName = (string) ($property['agent_name'] ?? '');
    if ($contactName === '') {
        $contactName = (string) ($property['owner_name'] ?? 'Seller');
    }

    $contactPhone = (string) ($property['agent_phone'] ?? '');
    $contactSubtitle = (string) ($property['agent_code'] ?? '');
    if ($contactSubtitle === '') {
        $contactSubtitle = 'Owner-listed property';
    }

    $highlights = array_filter([
        (string) ($property['type'] ?? ''),
        'Bedrooms: ' . (string) ($property['beds'] ?? ''),
        'Furnishing: ' . (string) ($property['furnish'] ?? 'Not specified'),
        'Parking: ' . (string) ($property['parking'] ?? 'Not specified'),
        $property['age'] === null ? 'Age not specified' : 'Property age: ' . (string) $property['age'] . ' years',
        'City: ' . (string) ($property['city'] ?? ''),
    ]);

    $status = (string) ($_GET['status'] ?? '');
    if ($status === 'enquiry-sent') {
        $enquiryFlash = 'Your enquiry was sent successfully.';
    }

    if ($requestMethod === 'POST' && (string) ($_POST['action'] ?? '') === 'send_enquiry') {
        require_csrf_token();
        $enquiryForm = enquiry_form_from_post($_POST, $enquiryForm);
        $enquiryErrors = validate_enquiry_form($enquiryForm);

        if ($isLoggedIn && (int) ($_SESSION['user_id'] ?? 0) === (int) ($property['owner_id'] ?? 0)) {
            $enquiryErrors[] = 'You cannot send an enquiry to your own listing.';
        }

        if (!$enquiryErrors) {
            create_property_enquiry($pdo, (int) ($property['owner_id'] ?? 0), (int) ($property['id'] ?? 0), $enquiryForm);

            header('Location: property-details.php?id=' . (int) ($property['id'] ?? 0) . '&status=enquiry-sent');
            exit;
        }
    }

    $propertyImages = image_urls((string) ($property['image_path'] ?? ''), PROPERTY_FALLBACK_IMAGE);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Property Details | TrackMyProperty</title>
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

.emi-result{
  margin-top:12px;
  padding:14px;
  border-radius:18px;
  background:white;
  border:1px solid rgba(190,24,93,.12);
  display:none;
}

.emi-result.is-visible{
  display:block;
}

.emi-metrics{
  display:grid;
  grid-template-columns:repeat(3, minmax(0, 1fr));
  gap:12px;
  margin-top:10px;
}

.emi-metric{
  padding:12px;
  border-radius:16px;
  background:rgba(252,231,243,.55);
}

.emi-metric span{
  display:block;
  color:#6b7280;
  font-size:12px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.08em;
  margin-bottom:4px;
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
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </div>
</nav>

<main class="detail-page">
<?php if (!$property): ?>
  <section class="detail-shell">
    <div class="not-found-card">
      <h1>Property not found</h1>
      <p>The listing you opened is not available in the database right now.</p>
      <a class="view-btn not-found-link" href="buy.php">Back to Buy Properties</a>
    </div>
  </section>
<?php else: ?>
  <section class="detail-shell">
    <div class="detail-topbar">
      <a class="back-link" href="buy.php">Back to Buy Properties</a>
      <span class="detail-badge"><?php echo h((string) ($property['type'] ?? '')); ?></span>
    </div>

    <section class="detail-hero">
      <div class="detail-gallery">
        <img class="detail-main-image" id="detailMainImage" src="<?php echo h($propertyImages[0] ?? PROPERTY_FALLBACK_IMAGE); ?>" alt="<?php echo h((string) ($property['title'] ?? '')); ?>">
        <?php if (count($propertyImages) > 1): ?>
          <div class="detail-thumbs">
            <?php foreach ($propertyImages as $index => $img): ?>
              <button class="detail-thumb<?php echo $index === 0 ? ' is-active' : ''; ?>" type="button" data-image="<?php echo h($img); ?>">
                <img src="<?php echo h($img); ?>" alt="<?php echo h((string) ($property['title'] ?? '') . ' image ' . ($index + 1)); ?>">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <aside class="detail-summary">
        <p class="detail-eyebrow">Live Listing</p>
        <h1><?php echo h((string) ($property['title'] ?? '')); ?></h1>
        <div class="detail-price"><?php echo h(format_price_value($property['price'] ?? 0)); ?></div>
        <div class="detail-location">Located in <?php echo h((string) ($property['city'] ?? '')); ?></div>

        <div class="detail-metrics">
          <div class="metric-card">
            <span class="metric-value"><?php echo (int) ($property['beds'] ?? 0); ?></span>
            <span class="metric-label">Bedrooms</span>
          </div>
          <div class="metric-card">
            <span class="metric-value"><?php echo h(number_format((float) ($property['area'] ?? 0))); ?></span>
            <span class="metric-label">Sqft</span>
          </div>
          <div class="metric-card">
            <span class="metric-value"><?php echo h((string) ($property['parking'] ?? 'N/A')); ?></span>
            <span class="metric-label">Parking</span>
          </div>
        </div>

        <p class="detail-copy"><?php echo h(trim((string) ($property['description'] ?? '')) !== '' ? (string) $property['description'] : 'The seller has not added a long description yet, but the core listing details are available below.'); ?></p>

        <div class="detail-cta-row">
          <?php if ($contactPhone !== ''): ?>
            <a class="view-btn detail-primary-link" href="tel:<?php echo h($contactPhone); ?>">Call Now</a>
          <?php else: ?>
            <a class="view-btn detail-primary-link" href="agent.php">Browse Agents</a>
          <?php endif; ?>
          <a class="ghost-btn" href="buy.php">Back to Listings</a>
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
        <h2>Property Overview</h2>
        <p>Core listing fields from the live database, without demo-only extras.</p>
      </div>
      <div class="overview-grid">
        <div class="overview-card">
          <span class="overview-label">Property Type</span>
          <strong><?php echo h((string) ($property['type'] ?? '')); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Furnishing</span>
          <strong><?php echo h((string) ($property['furnish'] ?? 'Not specified')); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Parking</span>
          <strong><?php echo h((string) ($property['parking'] ?? 'Not specified')); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Area</span>
          <strong><?php echo h(number_format((float) ($property['area'] ?? 0))); ?> sqft</strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Bedrooms</span>
          <strong><?php echo (int) ($property['beds'] ?? 0); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Property Age</span>
          <strong><?php echo h($property['age'] === null ? 'Not specified' : (string) $property['age'] . ' years'); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">City</span>
          <strong><?php echo h((string) ($property['city'] ?? '')); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Listed On</span>
          <strong><?php echo h(date('d M Y', strtotime((string) ($property['created_at'] ?? 'now')))); ?></strong>
        </div>
        <div class="overview-card">
          <span class="overview-label">Seller / Agent</span>
          <strong><?php echo h($contactName); ?></strong>
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
        <p class="detail-panel-copy">This page is backed by the database, so everything shown here comes from the saved seller listing and the optional agent profile.</p>
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
            You can call directly if you want to ask about pricing, availability, or a site visit.
          <?php else: ?>
            The listing is live, but the seller has not added a public agent phone number yet. Creating an agent profile will expose direct contact here.
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
        <form class="detail-form" id="enquiryForm" method="POST" action="property-details.php?id=<?php echo (int) ($property['id'] ?? 0); ?>">
          <input type="hidden" name="action" value="send_enquiry">
          <?php echo csrf_input(); ?>
          <input type="text" name="name" placeholder="Your Name" value="<?php echo h($enquiryForm['name']); ?>" required>
          <input type="email" name="email" placeholder="Email (optional)" value="<?php echo h($enquiryForm['email']); ?>">
          <input type="text" name="phone" placeholder="Phone Number" value="<?php echo h($enquiryForm['phone']); ?>" required>
          <textarea name="message" placeholder="Message (optional)"><?php echo h($enquiryForm['message']); ?></textarea>
          <button type="submit" class="view-btn">Send Enquiry</button>
        </form>
      </div>

      <div class="detail-panel">
        <h3>Loan EMI Calculator</h3>
        <p class="detail-panel-copy">Powered by the Flask service for quick monthly EMI estimates.</p>
        <div class="detail-form" id="emiForm">
          <input type="number" step="0.01" min="1" name="principal" placeholder="Loan Amount (₹)" value="<?php echo h((string) ($property['price'] ?? '')); ?>">
          <input type="number" step="0.01" min="0" name="annual_rate" placeholder="Interest Rate (%)" value="8.5">
          <input type="number" step="0.1" min="1" name="tenure_years" placeholder="Tenure (Years)" value="20">
          <button type="button" class="view-btn" id="emiCalcBtn">Calculate EMI</button>
        </div>
        <div class="emi-result" id="emiResult" aria-live="polite"></div>
      </div>
    </section>

    <section class="detail-section">
      <div class="detail-section-head">
        <h2>Similar Properties</h2>
        <p>Other live listings from the current property database.</p>
      </div>
      <?php if (!$similarProperties): ?>
        <div class="empty-state">No similar properties available yet.</div>
      <?php else: ?>
        <div class="grid">
          <?php foreach ($similarProperties as $similar): ?>
            <div class="card">
              <a class="card-link" href="property-details.php?id=<?php echo (int) ($similar['id'] ?? 0); ?>">
                <img src="<?php echo h(image_first_url((string) ($similar['image_path'] ?? ''), PROPERTY_FALLBACK_IMAGE)); ?>" class="card-img" alt="<?php echo h((string) ($similar['title'] ?? '')); ?>">
                <div class="card-body">
                  <div class="card-price"><?php echo h(format_price_value($similar['price'] ?? 0)); ?></div>
                  <div class="card-title"><?php echo h((string) ($similar['title'] ?? '')); ?></div>
                  <div class="card-loc">📍 <?php echo h((string) ($similar['city'] ?? '')); ?></div>
                  <div class="card-stats">
                    <span><?php echo (int) ($similar['beds'] ?? 0); ?> Beds</span>
                    <span><?php echo h((string) ($similar['type'] ?? '')); ?></span>
                    <span><?php echo h(number_format((float) ($similar['area'] ?? 0))); ?> sqft</span>
                  </div>
                </div>
              </a>
              <div class="card-actions">
                <a class="view-btn details-link" href="property-details.php?id=<?php echo (int) ($similar['id'] ?? 0); ?>">View Details</a>
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
© 2026 TrackMyProperty. Live property details from the current database.
</div>
</footer>
<script>
const detailMainImage = document.getElementById('detailMainImage');
const detailThumbs = document.querySelectorAll('.detail-thumb');

if (detailMainImage && detailThumbs.length) {
  detailThumbs.forEach((thumb) => {
    thumb.addEventListener('click', () => {
      const nextImage = thumb.dataset.image;
      if (nextImage) {
        detailMainImage.src = nextImage;
      }
      detailThumbs.forEach((btn) => btn.classList.remove('is-active'));
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

const emiForm = document.getElementById('emiForm');
const emiBtn = document.getElementById('emiCalcBtn');
const emiResult = document.getElementById('emiResult');

if (emiForm && emiBtn && emiResult) {
  const formatMoney = (value) => {
    const number = Number(value || 0);
    return `₹${number.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
  };

  emiBtn.addEventListener('click', async () => {
    const principal = emiForm.querySelector('[name="principal"]')?.value || '';
    const annualRate = emiForm.querySelector('[name="annual_rate"]')?.value || '';
    const tenureYears = emiForm.querySelector('[name="tenure_years"]')?.value || '';

    emiResult.textContent = 'Calculating EMI via Flask...';
    emiResult.classList.add('is-visible');

    try {
      const response = await fetch(`${FLASK_BASE}/api/emi`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          principal,
          annual_rate: annualRate,
          tenure_years: tenureYears,
        }),
      });

      if (!response.ok) {
        throw new Error('Bad response');
      }

      const data = await response.json();
      emiResult.innerHTML = `
        <strong>Estimated EMI</strong>
        <div class="emi-metrics">
          <div class="emi-metric"><span>Monthly EMI</span><strong>${formatMoney(data.emi)}</strong></div>
          <div class="emi-metric"><span>Total Interest</span><strong>${formatMoney(data.total_interest)}</strong></div>
          <div class="emi-metric"><span>Total Payment</span><strong>${formatMoney(data.total_payment)}</strong></div>
        </div>
      `;
    } catch (error) {
      emiResult.textContent = 'Flask service is not reachable. Start the API to calculate EMI.';
    }
  });
}
</script>
</body>
</html>
