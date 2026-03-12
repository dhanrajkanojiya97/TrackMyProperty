<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
$isLoggedIn = is_logged_in();
$currentUserName = (string) ($_SESSION['user_name'] ?? 'User');
$propertyTypes = ['Flat', 'Villa', 'House', 'Plot'];
const BUY_FALLBACK_IMAGE = 'https://images.pexels.com/photos/30503925/pexels-photo-30503925.jpeg?auto=compress&cs=tinysrgb&w=1200';

$stmt = $pdo->query(
    'SELECT
        p.*,
        a.agent_code,
        a.full_name AS agent_name,
        a.phone AS agent_phone,
        a.service_area AS agent_service_area
     FROM properties p
     LEFT JOIN agents a ON a.user_id = p.owner_id
     ORDER BY p.created_at DESC, p.id DESC'
);
$rows = $stmt->fetchAll();

$properties = array_map(static function (array $row): array {
    $listedBy = (string) ($row['agent_name'] ?? '');
    if ($listedBy === '') {
        $listedBy = (string) ($row['owner_name'] ?? 'Seller');
    }

    $description = trim((string) ($row['description'] ?? ''));
    if ($description === '') {
        $description = 'Clean listing with seller-provided details available on the property page.';
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'title' => (string) ($row['title'] ?? ''),
        'price' => (float) ($row['price'] ?? 0),
        'beds' => (int) ($row['beds'] ?? 0),
        'sqft' => (float) ($row['area'] ?? 0),
        'type' => (string) ($row['type'] ?? ''),
        'location' => (string) ($row['city'] ?? ''),
        'img' => image_first_url((string) ($row['image_path'] ?? ''), BUY_FALLBACK_IMAGE),
        'desc' => $description,
        'furnish' => (string) ($row['furnish'] ?? ''),
        'parking' => (string) ($row['parking'] ?? ''),
        'age' => $row['age'] === null ? null : (int) $row['age'],
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
<title>Buy Properties | TrackMyProperty</title>
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

.modal-copy{
  margin-top:12px;
  color:#6b7280;
  line-height:1.7;
}

.modal-meta{
  margin-top:16px;
  color:#6b7280;
  font-size:14px;
}

.empty-link{
  display:inline-flex;
  margin-top:16px;
  text-decoration:none;
  justify-content:center;
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
    <h1>Find Your Dream Home</h1>
    <p>Real listings pulled directly from the current property database.</p>
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search city or title">
        <button onclick="applyFilters()">Search</button>
    </div>
</header>

<section id="buy" class="container">
    <div class="filter-strip" aria-label="Buy property filters">
        <span class="filter-label">Quick Filter</span>

        <select id="bedFilter" aria-label="Filter by bedrooms">
            <option value="">Beds</option>
            <option value="1">1+ Beds</option>
            <option value="2">2+ Beds</option>
            <option value="3">3+ Beds</option>
            <option value="4">4+ Beds</option>
        </select>

        <select id="priceFilter" aria-label="Filter by price">
            <option value="">Budget</option>
            <option value="500000">Up to 5 Lakh</option>
            <option value="1000000">Up to 10 Lakh</option>
            <option value="2000000">Up to 20 Lakh</option>
            <option value="5000000">Up to 50 Lakh</option>
        </select>

        <select id="typeFilter" aria-label="Filter by property type">
            <option value="">Type</option>
            <?php foreach ($propertyTypes as $type): ?>
                <option value="<?php echo h($type); ?>"><?php echo h($type); ?></option>
            <?php endforeach; ?>
        </select>

        <button type="button" class="filter-apply" onclick="applyFilters()">Apply</button>
        <button type="button" class="filter-reset" onclick="resetFilters()">Reset</button>
        <span class="result-count" id="resultCount">0 homes</span>
    </div>

    <div class="section-header">
        <h2>Buy Properties</h2>
        <p>Published listings from real seller accounts.</p>
    </div>

    <div class="grid" id="propertyGrid"></div>
</section>

<div id="propertyModal" class="modal" onclick="closeModal(event)">
  <div class="modal-content" onclick="event.stopPropagation()">
    <span class="close-btn" onclick="closeModal()">&times;</span>
    <img id="modalImg" class="modal-img" alt="">
    <div class="modal-info">
      <h2 id="modalTitle"></h2>
      <h3 id="modalPrice"></h3>
      <p id="modalDesc" class="modal-copy"></p>
      <ul>
        <li id="modalBeds"></li>
        <li id="modalArea"></li>
        <li id="modalFurnish"></li>
      </ul>
      <p id="modalMeta" class="modal-meta"></p>
      <div class="modal-actions">
        <a id="modalDetailsLink" class="view-btn modal-link-btn" href="property-details.php">View Full Details</a>
      </div>
    </div>
  </div>
</div>

<footer>
<div class="copyright">
© 2026 TrackMyProperty. Live property data from the current database.
</div>
</footer>

<script>
const properties = <?php echo json_encode($properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const grid = document.getElementById("propertyGrid");
const resultCount = document.getElementById("resultCount");
const modal = document.getElementById("propertyModal");
const modalImg = document.getElementById("modalImg");
const modalTitle = document.getElementById("modalTitle");
const modalPrice = document.getElementById("modalPrice");
const modalDesc = document.getElementById("modalDesc");
const modalBeds = document.getElementById("modalBeds");
const modalArea = document.getElementById("modalArea");
const modalFurnish = document.getElementById("modalFurnish");
const modalMeta = document.getElementById("modalMeta");
const modalDetailsLink = document.getElementById("modalDetailsLink");

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function formatPrice(value) {
  return `₹${Number(value || 0).toLocaleString()}`;
}

function formatArea(value) {
  return `${Number(value || 0).toLocaleString()} sqft`;
}

function detailHref(id) {
  return `property-details.php?id=${id}`;
}

function render(data) {
  grid.innerHTML = "";

  if (!data.length) {
    grid.innerHTML = `
      <div class="empty-state">
        No properties are available right now.
        <br>
        <a class="view-btn modal-link-btn empty-link" href="sell.php">Publish a listing</a>
      </div>`;
    resultCount.innerText = "0 homes";
    return;
  }

  data.forEach((property) => {
    grid.innerHTML += `
      <div class="card">
        <a class="card-link" href="${detailHref(property.id)}">
          <img src="${escapeHtml(property.img)}" class="card-img" alt="${escapeHtml(property.title)}">
          <div class="card-body">
            <div class="card-price">${formatPrice(property.price)}</div>
            <div class="card-title">${escapeHtml(property.title)}</div>
            <div class="card-loc">📍 ${escapeHtml(property.location)}</div>
            <div class="card-stats">
              <span>${escapeHtml(property.beds)} Beds</span>
              <span>${escapeHtml(property.type)}</span>
              <span>${formatArea(property.sqft)}</span>
            </div>
          </div>
        </a>
        <div class="card-actions">
          <a class="view-btn details-link" href="${detailHref(property.id)}">View Details</a>
          <button type="button" class="view-btn quick-view-btn" onclick="openModal(event, ${property.id})">Quick View</button>
        </div>
      </div>`;
  });

  resultCount.innerText = `${data.length} ${data.length === 1 ? "home" : "homes"}`;
}

function applyFilters() {
  const query = document.getElementById("searchInput").value.trim().toLowerCase();
  const beds = Number(document.getElementById("bedFilter").value);
  const price = Number(document.getElementById("priceFilter").value);
  const type = document.getElementById("typeFilter").value;

  const filtered = properties.filter((property) =>
    (!query || property.title.toLowerCase().includes(query) || property.location.toLowerCase().includes(query)) &&
    (!beds || property.beds >= beds) &&
    (!price || property.price <= price) &&
    (!type || property.type === type)
  );

  render(filtered);
}

function resetFilters() {
  document.getElementById("searchInput").value = "";
  document.getElementById("bedFilter").value = "";
  document.getElementById("priceFilter").value = "";
  document.getElementById("typeFilter").value = "";
  render(properties);
}

function openModal(event, id) {
  if (event) {
    event.stopPropagation();
  }

  const property = properties.find((item) => item.id === id);
  if (!property) {
    return;
  }

  modalImg.src = property.img;
  modalImg.alt = property.title;
  modalTitle.innerText = property.title;
  modalPrice.innerText = formatPrice(property.price);
  modalDesc.innerText = property.desc;
  modalBeds.innerText = `${property.beds} Bedrooms`;
  modalArea.innerText = formatArea(property.sqft);
  modalFurnish.innerText = `Furnishing: ${property.furnish || "Not specified"}`;
  modalMeta.innerText = property.phone
    ? `Listed by ${property.listedBy} • Call ${property.phone}`
    : `Listed by ${property.listedBy}`;
  modalDetailsLink.href = detailHref(property.id);
  modal.style.display = "flex";
}

function closeModal(event) {
  if (!event || event.target.id === "propertyModal") {
    modal.style.display = "none";
  }
}

document.getElementById("searchInput").addEventListener("keyup", (event) => {
  if (event.key === "Enter") {
    applyFilters();
  }
});

render(properties);
</script>

</body>
</html>
