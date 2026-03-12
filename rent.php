<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
$isLoggedIn = is_logged_in();
$currentUserName = (string) ($_SESSION['user_name'] ?? 'User');
const RENT_FALLBACK_IMAGE = 'https://images.pexels.com/photos/17226654/pexels-photo-17226654.jpeg?auto=compress&cs=tinysrgb&w=1200';

$stmt = $pdo->query(
    'SELECT
        r.*,
        a.agent_code,
        a.full_name AS agent_name,
        a.phone AS agent_phone,
        a.service_area AS agent_service_area
     FROM rent_listings r
     LEFT JOIN agents a ON a.user_id = r.owner_id
     ORDER BY r.created_at DESC, r.id DESC'
);
$rows = $stmt->fetchAll();

$rentTypes = array_values(array_unique(array_filter(array_map(
    static fn(array $row): string => trim((string) ($row['type'] ?? '')),
    $rows
))));
sort($rentTypes);

$rentals = array_map(static function (array $row): array {
    $listedBy = (string) ($row['agent_name'] ?? '');
    if ($listedBy === '') {
        $listedBy = (string) ($row['owner_name'] ?? 'Owner');
    }

    $description = trim((string) ($row['description'] ?? ''));
    if ($description === '') {
        $description = 'Clean rental listing with direct terms available on the detail page.';
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'title' => (string) ($row['title'] ?? ''),
        'rent' => (float) ($row['rent'] ?? 0),
        'deposit' => (float) ($row['deposit'] ?? 0),
        'beds' => (int) ($row['beds'] ?? 0),
        'baths' => (int) ($row['baths'] ?? 0),
        'area' => (float) ($row['area'] ?? 0),
        'type' => (string) ($row['type'] ?? ''),
        'city' => (string) ($row['city'] ?? ''),
        'locality' => (string) ($row['locality'] ?? ''),
        'furnishing' => (string) ($row['furnish'] ?? ''),
        'availableFrom' => (string) ($row['available_from'] ?? ''),
        'tenantType' => (string) ($row['tenant_type'] ?? ''),
        'parking' => (string) ($row['parking'] ?? ''),
        'petFriendly' => (string) ($row['pet_friendly'] ?? ''),
        'img' => image_first_url((string) ($row['image_path'] ?? ''), RENT_FALLBACK_IMAGE),
        'desc' => $description,
        'listedBy' => $listedBy,
        'phone' => (string) ($row['agent_phone'] ?? ''),
        'agentCode' => (string) ($row['agent_code'] ?? ''),
        'serviceArea' => (string) ($row['agent_service_area'] ?? ''),
    ];
}, $rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rent Homes | TrackMyProperty</title>
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

.filter-strip input{
  min-width:220px;
  padding:12px 16px;
  border:1px solid rgba(236,72,153,.18);
  border-radius:16px;
  background:rgba(255,255,255,.92);
  color:var(--dark);
}

.card-note{
  margin:12px 0 14px;
  color:#6b7280;
  line-height:1.65;
  font-size:14px;
}

.stat-pill-row{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-bottom:14px;
}

.stat-pill{
  padding:8px 12px;
  border-radius:999px;
  background:rgba(190,24,93,.08);
  color:#9d174d;
  font-size:12px;
  font-weight:600;
}

.modal-copy{
  margin-top:10px;
  color:#6b7280;
  line-height:1.7;
}

.modal-info ul{
  margin:18px 0 0 18px;
}

.empty-link{
  display:inline-flex;
  margin-top:16px;
  text-decoration:none;
  justify-content:center;
}

@media (max-width: 768px){
  .filter-strip input{
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
  <h1>Rent A Home Without The Noise</h1>
  <p>Live rental listings pulled directly from the current database.</p>
</header>

<section class="container">
  <div class="filter-strip" aria-label="Rent property filters">
    <span class="filter-label">Quick Filter</span>

    <input type="text" id="searchInput" placeholder="Search city, locality or title">

    <select id="typeFilter" aria-label="Filter by property type">
      <option value="">Type</option>
      <?php foreach ($rentTypes as $type): ?>
        <option value="<?php echo h($type); ?>"><?php echo h($type); ?></option>
      <?php endforeach; ?>
    </select>

    <select id="bedFilter" aria-label="Filter by bedrooms">
      <option value="">Beds</option>
      <option value="1">1+ Beds</option>
      <option value="2">2+ Beds</option>
      <option value="3">3+ Beds</option>
      <option value="4">4+ Beds</option>
    </select>

    <select id="furnishFilter" aria-label="Filter by furnishing">
      <option value="">Furnishing</option>
      <option value="Furnished">Furnished</option>
      <option value="Semi Furnished">Semi Furnished</option>
      <option value="Semi-Furnished">Semi-Furnished</option>
      <option value="Unfurnished">Unfurnished</option>
    </select>

    <select id="budgetFilter" aria-label="Filter by monthly rent">
      <option value="">Budget</option>
      <option value="15000">Up to 15,000</option>
      <option value="25000">Up to 25,000</option>
      <option value="40000">Up to 40,000</option>
      <option value="60000">Up to 60,000</option>
    </select>

    <button type="button" class="filter-apply" onclick="applyFilters()">Apply</button>
    <button type="button" class="filter-reset" onclick="resetFilters()">Reset</button>
    <span class="result-count" id="resultCount">0 rentals</span>
  </div>

  <div class="section-header">
    <h2>Rent Listings</h2>
    <p>Published rental listings from live owner and agent accounts.</p>
  </div>

  <div class="grid" id="rentGrid"></div>
</section>

<div id="rentModal" class="modal" onclick="closeModal(event)">
  <div class="modal-content" onclick="event.stopPropagation()">
    <span class="close-btn" onclick="closeModal()">&times;</span>
    <img id="modalImg" class="modal-img" alt="">
    <div class="modal-info">
      <h2 id="modalTitle"></h2>
      <h3 id="modalPrice"></h3>
      <p class="modal-copy" id="modalDesc"></p>
      <ul>
        <li id="modalDeposit"></li>
        <li id="modalFurnishing"></li>
        <li id="modalAvailable"></li>
        <li id="modalTenant"></li>
      </ul>
      <div class="modal-actions">
        <a id="modalDetailsLink" class="view-btn modal-link-btn" href="rent-details.php">View Full Details</a>
      </div>
    </div>
  </div>
</div>

<footer>
  <div class="copyright">
    © 2026 TrackMyProperty. Live rental data from the current database.
  </div>
</footer>

<script>
const rentals = <?php echo json_encode($rentals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const grid = document.getElementById("rentGrid");
const resultCount = document.getElementById("resultCount");
const modal = document.getElementById("rentModal");
const modalImg = document.getElementById("modalImg");
const modalTitle = document.getElementById("modalTitle");
const modalPrice = document.getElementById("modalPrice");
const modalDesc = document.getElementById("modalDesc");
const modalDeposit = document.getElementById("modalDeposit");
const modalFurnishing = document.getElementById("modalFurnishing");
const modalAvailable = document.getElementById("modalAvailable");
const modalTenant = document.getElementById("modalTenant");
const modalDetailsLink = document.getElementById("modalDetailsLink");

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function formatMoney(value) {
  return `₹${Number(value || 0).toLocaleString()}`;
}

function detailHref(id) {
  return `rent-details.php?id=${id}`;
}

function render(list) {
  grid.innerHTML = "";

  if (!list.length) {
    grid.innerHTML = `
      <div class="empty-state">
        No rentals match this filter yet.
        <br>
        <a class="view-btn modal-link-btn empty-link" href="agent.php">Browse Agents</a>
      </div>`;
    resultCount.innerText = "0 rentals";
    return;
  }

  list.forEach((item) => {
    grid.innerHTML += `
      <div class="card">
        <a class="card-link" href="${detailHref(item.id)}">
          <img src="${escapeHtml(item.img)}" class="card-img" alt="${escapeHtml(item.title)}">
          <div class="card-body">
            <div class="card-price">${formatMoney(item.rent)} / month</div>
            <div class="card-title">${escapeHtml(item.title)}</div>
            <div class="card-loc">${escapeHtml(item.locality)}, ${escapeHtml(item.city)}</div>
            <p class="card-note">${escapeHtml(item.desc)}</p>
            <div class="stat-pill-row">
              <span class="stat-pill">Deposit ${formatMoney(item.deposit)}</span>
              <span class="stat-pill">${escapeHtml(item.furnishing)}</span>
              <span class="stat-pill">${escapeHtml(item.availableFrom)}</span>
            </div>
            <div class="card-stats">
              <span>${escapeHtml(item.beds)} Beds</span>
              <span>${escapeHtml(item.baths)} Baths</span>
              <span>${Number(item.area || 0).toLocaleString()} sqft</span>
            </div>
          </div>
        </a>
        <div class="card-actions">
          <a class="view-btn details-link" href="${detailHref(item.id)}">View Details</a>
          <button type="button" class="view-btn quick-view-btn" onclick="openModal(event, ${item.id})">Quick View</button>
        </div>
      </div>`;
  });

  resultCount.innerText = `${list.length} ${list.length === 1 ? "rental" : "rentals"}`;
}

function applyFilters() {
  const query = document.getElementById("searchInput").value.trim().toLowerCase();
  const type = document.getElementById("typeFilter").value;
  const beds = Number(document.getElementById("bedFilter").value);
  const furnishing = document.getElementById("furnishFilter").value;
  const budget = Number(document.getElementById("budgetFilter").value);

  const filtered = rentals.filter((item) =>
    (!query || item.title.toLowerCase().includes(query) || item.city.toLowerCase().includes(query) || item.locality.toLowerCase().includes(query)) &&
    (!type || item.type === type) &&
    (!beds || item.beds >= beds) &&
    (!furnishing || item.furnishing === furnishing) &&
    (!budget || item.rent <= budget)
  );

  render(filtered);
}

function resetFilters() {
  document.getElementById("searchInput").value = "";
  document.getElementById("typeFilter").value = "";
  document.getElementById("bedFilter").value = "";
  document.getElementById("furnishFilter").value = "";
  document.getElementById("budgetFilter").value = "";
  render(rentals);
}

function openModal(event, id) {
  if (event) {
    event.stopPropagation();
  }

  const item = rentals.find((entry) => entry.id === id);
  if (!item) {
    return;
  }

  modalImg.src = item.img;
  modalImg.alt = item.title;
  modalTitle.innerText = item.title;
  modalPrice.innerText = `${formatMoney(item.rent)} / month`;
  modalDesc.innerText = item.desc;
  modalDeposit.innerText = `Deposit: ${formatMoney(item.deposit)}`;
  modalFurnishing.innerText = `Furnishing: ${item.furnishing}`;
  modalAvailable.innerText = `Available: ${item.availableFrom}`;
  modalTenant.innerText = `Tenant Type: ${item.tenantType}`;
  modalDetailsLink.href = detailHref(item.id);
  modal.style.display = "flex";
}

function closeModal(event) {
  if (!event || event.target.id === "rentModal") {
    modal.style.display = "none";
  }
}

document.getElementById("searchInput").addEventListener("keyup", (event) => {
  if (event.key === "Enter") {
    applyFilters();
  }
});

render(rentals);
</script>
</body>
</html>
