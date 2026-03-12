<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

require_login();

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentUserName = (string) ($_SESSION['user_name'] ?? 'Owner');

$salePropertyTypes = ['Flat', 'Villa', 'House', 'Plot'];
$saleBedOptions = ['1', '2', '3', '4', '5'];
$saleFurnishOptions = ['Furnished', 'Semi-Furnished', 'Unfurnished'];
$saleParkingOptions = ['Yes', 'No'];

$rentTypes = ['Flat', 'Studio', 'Villa', 'House'];
$rentBedOptions = ['1', '2', '3', '4', '5'];
$rentBathOptions = ['1', '2', '3', '4'];
$rentFurnishOptions = ['Furnished', 'Semi Furnished', 'Unfurnished'];
$rentAvailableOptions = ['Immediate', 'This Week', 'Next Month'];
$rentTenantOptions = ['Family', 'Working Professional', 'Family / Working', 'Bachelor / Working', 'Family / Anyone'];
$rentParkingOptions = ['1 Car', '2 Cars', 'Bike Parking', 'No Parking'];
$rentPetOptions = ['Yes', 'No'];
const SALE_FALLBACK_IMAGE = 'https://images.pexels.com/photos/30503925/pexels-photo-30503925.jpeg?auto=compress&cs=tinysrgb&w=1200';
const RENT_FALLBACK_IMAGE = 'https://images.pexels.com/photos/17226654/pexels-photo-17226654.jpeg?auto=compress&cs=tinysrgb&w=1200';

function dashboard_url(array $params = [], string $fragment = ''): string
{
    $url = 'sell.php';
    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    if ($fragment !== '') {
        $url .= '#' . $fragment;
    }

    return $url;
}

function dashboard_redirect(array $params = [], string $fragment = ''): void
{
    header('Location: ' . dashboard_url($params, $fragment));
    exit;
}

function fetch_owned_property(PDO $pdo, int $propertyId, int $ownerId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = ? AND owner_id = ? LIMIT 1');
    $stmt->execute([$propertyId, $ownerId]);
    $property = $stmt->fetch();

    return $property ?: null;
}

function fetch_owned_rent_listing(PDO $pdo, int $listingId, int $ownerId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM rent_listings WHERE id = ? AND owner_id = ? LIMIT 1');
    $stmt->execute([$listingId, $ownerId]);
    $listing = $stmt->fetch();

    return $listing ?: null;
}

function remove_uploaded_asset(string $imagePath): void
{
    if ($imagePath === '' || !str_starts_with($imagePath, 'uploads/')) {
        return;
    }

    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath);
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function upload_listing_image(array $file, array &$errors, string $label): ?string
{
    if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Failed to upload the ' . $label . ' image.';
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $errors[] = 'Please upload a valid image file.';
        return null;
    }

    $info = getimagesize($tmp);
    if ($info === false) {
        $errors[] = 'Please upload a valid image file.';
        return null;
    }

    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!in_array($info[2], $allowed, true)) {
        $errors[] = 'Only JPG, PNG, GIF, or WEBP images are allowed.';
        return null;
    }

    $ext = image_type_to_extension($info[2], false);
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $filename = bin2hex(random_bytes(10)) . '.' . $ext;
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        $errors[] = 'Failed to save uploaded image.';
        return null;
    }

    return 'uploads/' . $filename;
}

function format_money_display($amount): string
{
    return '₹' . number_format((float) $amount);
}

$activeMode = trim((string) ($_GET['mode'] ?? 'sale'));
if (!in_array($activeMode, ['sale', 'rent'], true)) {
    $activeMode = 'sale';
}

$saleErrors = [];
$rentErrors = [];
$saleFlashMessage = '';
$rentFlashMessage = '';

$saleForm = [
    'title' => '',
    'city' => '',
    'price' => '',
    'area' => '',
    'type' => '',
    'beds' => '',
    'furnish' => '',
    'parking' => '',
    'age' => '',
    'desc' => '',
];

$rentForm = [
    'title' => '',
    'city' => '',
    'locality' => '',
    'rent' => '',
    'deposit' => '',
    'area' => '',
    'type' => '',
    'beds' => '',
    'baths' => '',
    'furnish' => '',
    'available_from' => '',
    'tenant_type' => '',
    'parking' => '',
    'pet_friendly' => '',
    'desc' => '',
];

$saleEditingProperty = null;
$rentEditingListing = null;
$saleEditId = isset($_GET['sale_edit']) ? (int) $_GET['sale_edit'] : (isset($_GET['edit']) ? (int) $_GET['edit'] : 0);
$rentEditId = isset($_GET['rent_edit']) ? (int) $_GET['rent_edit'] : 0;

$status = trim((string) ($_GET['status'] ?? ''));
$statusMessages = [
    'sale-saved' => 'Sale listing published successfully.',
    'sale-updated' => 'Sale listing updated successfully.',
    'sale-deleted' => 'Sale listing deleted successfully.',
    'rent-saved' => 'Rent listing published successfully.',
    'rent-updated' => 'Rent listing updated successfully.',
    'rent-deleted' => 'Rent listing deleted successfully.',
];

if (isset($statusMessages[$status])) {
    if (str_starts_with($status, 'rent-')) {
        $rentFlashMessage = $statusMessages[$status];
        $activeMode = 'rent';
    } else {
        $saleFlashMessage = $statusMessages[$status];
        $activeMode = 'sale';
    }
}

if ($saleEditId > 0) {
    $activeMode = 'sale';
    $saleEditingProperty = fetch_owned_property($pdo, $saleEditId, $currentUserId);
    if ($saleEditingProperty === null) {
        $saleErrors[] = 'Sale listing not found or you do not have permission to edit it.';
    } else {
        $saleForm = [
            'title' => (string) ($saleEditingProperty['title'] ?? ''),
            'city' => (string) ($saleEditingProperty['city'] ?? ''),
            'price' => (string) ($saleEditingProperty['price'] ?? ''),
            'area' => (string) ($saleEditingProperty['area'] ?? ''),
            'type' => (string) ($saleEditingProperty['type'] ?? ''),
            'beds' => (string) ($saleEditingProperty['beds'] ?? ''),
            'furnish' => (string) ($saleEditingProperty['furnish'] ?? ''),
            'parking' => (string) ($saleEditingProperty['parking'] ?? ''),
            'age' => (string) ($saleEditingProperty['age'] ?? ''),
            'desc' => (string) ($saleEditingProperty['description'] ?? ''),
        ];
    }
}

if ($rentEditId > 0) {
    $activeMode = 'rent';
    $rentEditingListing = fetch_owned_rent_listing($pdo, $rentEditId, $currentUserId);
    if ($rentEditingListing === null) {
        $rentErrors[] = 'Rent listing not found or you do not have permission to edit it.';
    } else {
        $rentForm = [
            'title' => (string) ($rentEditingListing['title'] ?? ''),
            'city' => (string) ($rentEditingListing['city'] ?? ''),
            'locality' => (string) ($rentEditingListing['locality'] ?? ''),
            'rent' => (string) ($rentEditingListing['rent'] ?? ''),
            'deposit' => (string) ($rentEditingListing['deposit'] ?? ''),
            'area' => (string) ($rentEditingListing['area'] ?? ''),
            'type' => (string) ($rentEditingListing['type'] ?? ''),
            'beds' => (string) ($rentEditingListing['beds'] ?? ''),
            'baths' => (string) ($rentEditingListing['baths'] ?? ''),
            'furnish' => (string) ($rentEditingListing['furnish'] ?? ''),
            'available_from' => (string) ($rentEditingListing['available_from'] ?? ''),
            'tenant_type' => (string) ($rentEditingListing['tenant_type'] ?? ''),
            'parking' => (string) ($rentEditingListing['parking'] ?? ''),
            'pet_friendly' => (string) ($rentEditingListing['pet_friendly'] ?? ''),
            'desc' => (string) ($rentEditingListing['description'] ?? ''),
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = trim((string) ($_POST['action'] ?? ''));

    if (str_starts_with($action, 'sale_')) {
        $activeMode = 'sale';
    } elseif (str_starts_with($action, 'rent_')) {
        $activeMode = 'rent';
    }

    if ($action === 'sale_delete') {
        $propertyId = (int) ($_POST['property_id'] ?? 0);
        $propertyToDelete = fetch_owned_property($pdo, $propertyId, $currentUserId);
        if ($propertyToDelete === null) {
            $saleErrors[] = 'Sale listing not found or you do not have permission to delete it.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM properties WHERE id = ? AND owner_id = ?');
            $stmt->execute([$propertyId, $currentUserId]);
            remove_uploaded_asset((string) ($propertyToDelete['image_path'] ?? ''));
            dashboard_redirect(['mode' => 'sale', 'status' => 'sale-deleted'], 'listing-panel');
        }
    }

    if ($action === 'sale_create' || $action === 'sale_update') {
        $propertyId = (int) ($_POST['property_id'] ?? 0);

        if ($action === 'sale_update') {
            $saleEditingProperty = fetch_owned_property($pdo, $propertyId, $currentUserId);
            if ($saleEditingProperty === null) {
                $saleErrors[] = 'Sale listing not found or you do not have permission to update it.';
            }
        } else {
            $saleEditingProperty = null;
        }

        $saleForm = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'price' => trim((string) ($_POST['price'] ?? '')),
            'area' => trim((string) ($_POST['area'] ?? '')),
            'type' => trim((string) ($_POST['type'] ?? '')),
            'beds' => trim((string) ($_POST['beds'] ?? '')),
            'furnish' => trim((string) ($_POST['furnish'] ?? '')),
            'parking' => trim((string) ($_POST['parking'] ?? '')),
            'age' => trim((string) ($_POST['age'] ?? '')),
            'desc' => trim((string) ($_POST['desc'] ?? '')),
        ];

        if ($saleForm['title'] === '') {
            $saleErrors[] = 'Property title is required.';
        }
        if ($saleForm['city'] === '') {
            $saleErrors[] = 'City is required.';
        }
        if ($saleForm['price'] === '' || !is_numeric($saleForm['price']) || (float) $saleForm['price'] <= 0) {
            $saleErrors[] = 'Enter a valid price greater than zero.';
        }
        if ($saleForm['area'] === '' || !is_numeric($saleForm['area']) || (float) $saleForm['area'] <= 0) {
            $saleErrors[] = 'Enter a valid area greater than zero.';
        }
        if ($saleForm['beds'] === '' || !ctype_digit($saleForm['beds']) || (int) $saleForm['beds'] <= 0) {
            $saleErrors[] = 'Select a valid bedroom count.';
        }
        if ($saleForm['type'] === '' || !in_array($saleForm['type'], $salePropertyTypes, true)) {
            $saleErrors[] = 'Select a valid property type.';
        }
        if ($saleForm['furnish'] === '' || !in_array($saleForm['furnish'], $saleFurnishOptions, true)) {
            $saleErrors[] = 'Select a valid furnishing option.';
        }
        if ($saleForm['parking'] === '' || !in_array($saleForm['parking'], $saleParkingOptions, true)) {
            $saleErrors[] = 'Select a valid parking option.';
        }
        if ($saleForm['age'] !== '' && (!ctype_digit($saleForm['age']) || (int) $saleForm['age'] < 0)) {
            $saleErrors[] = 'Property age must be zero or a positive number.';
        }

        $uploadedImage = null;
        $fileError = (int) ($_FILES['sale_image']['error'] ?? UPLOAD_ERR_NO_FILE);

        if (!$saleErrors) {
            if ($action === 'sale_create' && $fileError === UPLOAD_ERR_NO_FILE) {
                $saleErrors[] = 'Property image is required for a new listing.';
            } elseif ($fileError !== UPLOAD_ERR_NO_FILE) {
                $uploadedImage = upload_listing_image($_FILES['sale_image'], $saleErrors, 'property');
            }
        }

        if (!$saleErrors) {
            if ($action === 'sale_update' && $saleEditingProperty !== null) {
                $imagePath = $uploadedImage ?? (string) ($saleEditingProperty['image_path'] ?? '');

                $stmt = $pdo->prepare(
                    'UPDATE properties
                        SET title = ?, city = ?, price = ?, area = ?, type = ?, beds = ?, furnish = ?, parking = ?, age = ?, description = ?, image_path = ?, owner_name = ?
                      WHERE id = ? AND owner_id = ?'
                );
                $stmt->execute([
                    $saleForm['title'],
                    $saleForm['city'],
                    (float) $saleForm['price'],
                    (float) $saleForm['area'],
                    $saleForm['type'],
                    (int) $saleForm['beds'],
                    $saleForm['furnish'],
                    $saleForm['parking'],
                    $saleForm['age'] === '' ? null : (int) $saleForm['age'],
                    $saleForm['desc'],
                    $imagePath,
                    $currentUserName,
                    $propertyId,
                    $currentUserId,
                ]);

                if ($uploadedImage !== null) {
                    remove_uploaded_asset((string) ($saleEditingProperty['image_path'] ?? ''));
                }

                dashboard_redirect(['mode' => 'sale', 'status' => 'sale-updated'], 'listing-panel');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO properties
                    (title, city, price, area, type, beds, furnish, parking, age, description, image_path, owner_id, owner_name)
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $saleForm['title'],
                $saleForm['city'],
                (float) $saleForm['price'],
                (float) $saleForm['area'],
                $saleForm['type'],
                (int) $saleForm['beds'],
                $saleForm['furnish'],
                $saleForm['parking'],
                $saleForm['age'] === '' ? null : (int) $saleForm['age'],
                $saleForm['desc'],
                (string) $uploadedImage,
                $currentUserId,
                $currentUserName,
            ]);

            dashboard_redirect(['mode' => 'sale', 'status' => 'sale-saved'], 'listing-panel');
        }
    }

    if ($action === 'rent_delete') {
        $listingId = (int) ($_POST['listing_id'] ?? 0);
        $listingToDelete = fetch_owned_rent_listing($pdo, $listingId, $currentUserId);
        if ($listingToDelete === null) {
            $rentErrors[] = 'Rent listing not found or you do not have permission to delete it.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM rent_listings WHERE id = ? AND owner_id = ?');
            $stmt->execute([$listingId, $currentUserId]);
            remove_uploaded_asset((string) ($listingToDelete['image_path'] ?? ''));
            dashboard_redirect(['mode' => 'rent', 'status' => 'rent-deleted'], 'listing-panel');
        }
    }

    if ($action === 'rent_create' || $action === 'rent_update') {
        $listingId = (int) ($_POST['listing_id'] ?? 0);

        if ($action === 'rent_update') {
            $rentEditingListing = fetch_owned_rent_listing($pdo, $listingId, $currentUserId);
            if ($rentEditingListing === null) {
                $rentErrors[] = 'Rent listing not found or you do not have permission to update it.';
            }
        } else {
            $rentEditingListing = null;
        }

        $rentForm = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'locality' => trim((string) ($_POST['locality'] ?? '')),
            'rent' => trim((string) ($_POST['rent'] ?? '')),
            'deposit' => trim((string) ($_POST['deposit'] ?? '')),
            'area' => trim((string) ($_POST['area'] ?? '')),
            'type' => trim((string) ($_POST['type'] ?? '')),
            'beds' => trim((string) ($_POST['beds'] ?? '')),
            'baths' => trim((string) ($_POST['baths'] ?? '')),
            'furnish' => trim((string) ($_POST['furnish'] ?? '')),
            'available_from' => trim((string) ($_POST['available_from'] ?? '')),
            'tenant_type' => trim((string) ($_POST['tenant_type'] ?? '')),
            'parking' => trim((string) ($_POST['parking'] ?? '')),
            'pet_friendly' => trim((string) ($_POST['pet_friendly'] ?? '')),
            'desc' => trim((string) ($_POST['desc'] ?? '')),
        ];

        if ($rentForm['title'] === '') {
            $rentErrors[] = 'Rental title is required.';
        }
        if ($rentForm['city'] === '') {
            $rentErrors[] = 'City is required.';
        }
        if ($rentForm['locality'] === '') {
            $rentErrors[] = 'Locality is required.';
        }
        if ($rentForm['rent'] === '' || !is_numeric($rentForm['rent']) || (float) $rentForm['rent'] <= 0) {
            $rentErrors[] = 'Enter a valid monthly rent greater than zero.';
        }
        if ($rentForm['deposit'] === '' || !is_numeric($rentForm['deposit']) || (float) $rentForm['deposit'] < 0) {
            $rentErrors[] = 'Enter a valid deposit amount.';
        }
        if ($rentForm['area'] === '' || !is_numeric($rentForm['area']) || (float) $rentForm['area'] <= 0) {
            $rentErrors[] = 'Enter a valid area greater than zero.';
        }
        if ($rentForm['type'] === '' || !in_array($rentForm['type'], $rentTypes, true)) {
            $rentErrors[] = 'Select a valid rental type.';
        }
        if ($rentForm['beds'] === '' || !ctype_digit($rentForm['beds']) || (int) $rentForm['beds'] <= 0) {
            $rentErrors[] = 'Select a valid bedroom count.';
        }
        if ($rentForm['baths'] === '' || !ctype_digit($rentForm['baths']) || (int) $rentForm['baths'] <= 0) {
            $rentErrors[] = 'Select a valid bathroom count.';
        }
        if ($rentForm['furnish'] === '' || !in_array($rentForm['furnish'], $rentFurnishOptions, true)) {
            $rentErrors[] = 'Select a valid furnishing option.';
        }
        if ($rentForm['available_from'] === '' || !in_array($rentForm['available_from'], $rentAvailableOptions, true)) {
            $rentErrors[] = 'Select a valid availability option.';
        }
        if ($rentForm['tenant_type'] === '' || !in_array($rentForm['tenant_type'], $rentTenantOptions, true)) {
            $rentErrors[] = 'Select a valid tenant type.';
        }
        if ($rentForm['parking'] === '' || !in_array($rentForm['parking'], $rentParkingOptions, true)) {
            $rentErrors[] = 'Select a valid parking option.';
        }
        if ($rentForm['pet_friendly'] === '' || !in_array($rentForm['pet_friendly'], $rentPetOptions, true)) {
            $rentErrors[] = 'Select a valid pet-friendly option.';
        }

        $uploadedImage = null;
        $fileError = (int) ($_FILES['rent_image']['error'] ?? UPLOAD_ERR_NO_FILE);

        if (!$rentErrors) {
            if ($action === 'rent_create' && $fileError === UPLOAD_ERR_NO_FILE) {
                $rentErrors[] = 'Rental image is required for a new listing.';
            } elseif ($fileError !== UPLOAD_ERR_NO_FILE) {
                $uploadedImage = upload_listing_image($_FILES['rent_image'], $rentErrors, 'rental');
            }
        }

        if (!$rentErrors) {
            if ($action === 'rent_update' && $rentEditingListing !== null) {
                $imagePath = $uploadedImage ?? (string) ($rentEditingListing['image_path'] ?? '');

                $stmt = $pdo->prepare(
                    'UPDATE rent_listings
                        SET title = ?, city = ?, locality = ?, rent = ?, deposit = ?, area = ?, type = ?, beds = ?, baths = ?, furnish = ?, available_from = ?, tenant_type = ?, parking = ?, pet_friendly = ?, description = ?, image_path = ?, owner_name = ?
                      WHERE id = ? AND owner_id = ?'
                );
                $stmt->execute([
                    $rentForm['title'],
                    $rentForm['city'],
                    $rentForm['locality'],
                    (float) $rentForm['rent'],
                    (float) $rentForm['deposit'],
                    (float) $rentForm['area'],
                    $rentForm['type'],
                    (int) $rentForm['beds'],
                    (int) $rentForm['baths'],
                    $rentForm['furnish'],
                    $rentForm['available_from'],
                    $rentForm['tenant_type'],
                    $rentForm['parking'],
                    $rentForm['pet_friendly'],
                    $rentForm['desc'],
                    $imagePath,
                    $currentUserName,
                    $listingId,
                    $currentUserId,
                ]);

                if ($uploadedImage !== null) {
                    remove_uploaded_asset((string) ($rentEditingListing['image_path'] ?? ''));
                }

                dashboard_redirect(['mode' => 'rent', 'status' => 'rent-updated'], 'listing-panel');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO rent_listings
                    (title, city, locality, rent, deposit, area, type, beds, baths, furnish, available_from, tenant_type, parking, pet_friendly, description, image_path, owner_id, owner_name)
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $rentForm['title'],
                $rentForm['city'],
                $rentForm['locality'],
                (float) $rentForm['rent'],
                (float) $rentForm['deposit'],
                (float) $rentForm['area'],
                $rentForm['type'],
                (int) $rentForm['beds'],
                (int) $rentForm['baths'],
                $rentForm['furnish'],
                $rentForm['available_from'],
                $rentForm['tenant_type'],
                $rentForm['parking'],
                $rentForm['pet_friendly'],
                $rentForm['desc'],
                (string) $uploadedImage,
                $currentUserId,
                $currentUserName,
            ]);

            dashboard_redirect(['mode' => 'rent', 'status' => 'rent-saved'], 'listing-panel');
        }
    }
}

$saleStmt = $pdo->prepare('SELECT * FROM properties WHERE owner_id = ? ORDER BY created_at DESC, id DESC');
$saleStmt->execute([$currentUserId]);
$saleListings = $saleStmt->fetchAll();
$saleCount = count($saleListings);

$rentStmt = $pdo->prepare('SELECT * FROM rent_listings WHERE owner_id = ? ORDER BY created_at DESC, id DESC');
$rentStmt->execute([$currentUserId]);
$rentListings = $rentStmt->fetchAll();
$rentCount = count($rentListings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Post Listings | TrackMyProperty</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
.merged-shell{max-width:1200px;margin:0 auto;padding:36px 24px 80px}
.user-chip{padding:8px 14px;border-radius:999px;background:rgba(190,24,93,.08);color:#be185d;font-size:14px;font-weight:600}
.nav-links a.is-active{color:#be185d;font-weight:700}
.anchor-section{scroll-margin-top:110px}
.intro-card,.panel{background:rgba(255,255,255,.84);border-radius:28px;box-shadow:0 20px 40px rgba(0,0,0,.06)}
.intro-card{padding:24px;margin-bottom:28px}
.intro-actions{display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-top:18px}
.mode-switch{display:inline-flex;gap:8px;padding:8px;border-radius:22px;background:rgba(252,231,243,.8);border:1px solid rgba(190,24,93,.08)}
.mode-pill,.jump-link,.ghost-link{display:inline-flex;align-items:center;justify-content:center;padding:12px 18px;border-radius:16px;text-decoration:none;font-weight:700}
.mode-pill{color:#9f1239;background:transparent}
.mode-pill.is-active{background:#be185d;color:#fff;box-shadow:0 10px 24px rgba(190,24,93,.2)}
.jump-link,.ghost-link{background:white;color:#be185d;border:1px solid rgba(190,24,93,.14)}
.count-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:22px}
.count-card{padding:18px;border-radius:22px;background:linear-gradient(135deg,rgba(252,231,243,.95),rgba(255,237,213,.95))}
.count-card strong{display:block;font-size:30px;color:#be185d}
.count-card span{color:#6b7280}
.section-head-merged{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:18px}
.section-head-merged h2{margin:0;color:#be185d}
.publish-layout{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(320px,.92fr);gap:24px;align-items:start}
.panel{padding:26px}
.panel h3{color:#be185d;margin-bottom:8px}
.panel-copy{color:#6b7280;margin-bottom:18px}
.flash,.error-list{margin-bottom:16px;padding:14px 16px;border-radius:16px;font-size:14px}
.flash{background:#ecfdf5;color:#047857}
.error-list{background:#fff1f2;color:#9f1239}
.error-list ul{margin-left:18px}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.field-full{grid-column:1/-1}
.form-grid input,.form-grid select,.form-grid textarea{width:100%;padding:14px 16px;border-radius:16px;border:1px solid rgba(236,72,153,.16);background:white;color:#374151}
.form-grid textarea{min-height:130px;resize:vertical}
.field-note{margin-top:8px;color:#6b7280;font-size:13px}
.estimate-card{padding:16px;border-radius:20px;background:rgba(252,231,243,.58);border:1px dashed rgba(190,24,93,.18)}
.estimate-head{display:flex;align-items:center;justify-content:space-between;gap:16px}
.estimate-head strong{color:#9f1239}
.estimate-note{display:block;margin-top:4px;color:#6b7280;font-size:13px}
.estimate-output{margin-top:12px;padding:12px 14px;border-radius:16px;background:white;border:1px solid rgba(190,24,93,.12);color:#374151;font-size:14px}
.estimate-actions{margin-top:10px;display:flex;gap:12px;flex-wrap:wrap}
.estimate-actions button[disabled]{opacity:.6;cursor:not-allowed}
.image-preview{display:flex;align-items:center;gap:14px;padding:14px;border-radius:18px;background:rgba(252,231,243,.58)}
.image-preview img{width:88px;height:88px;border-radius:16px;object-fit:cover}
.form-actions{display:flex;gap:12px;flex-wrap:wrap}
.tip{padding:16px;border-radius:20px;background:rgba(255,255,255,.9);border:1px solid rgba(190,24,93,.08);margin-top:12px}
.tip strong{display:block;color:#be185d;margin-bottom:4px}
.listing-block{margin-top:22px}
.card-actions form{flex:1}
.card-actions button{width:100%}
.empty{padding:30px 20px;border-radius:24px;text-align:center;background:rgba(255,255,255,.8);color:#6b7280}
@media (max-width:980px){.publish-layout{grid-template-columns:1fr}}
@media (max-width:768px){.merged-shell{padding:28px 20px 70px}.count-grid,.form-grid{grid-template-columns:1fr}.section-head-merged{flex-direction:column;align-items:flex-start}.intro-actions{width:100%;align-items:stretch}.mode-switch{width:100%;display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.mode-pill,.jump-link,.ghost-link{width:100%}.image-preview{flex-direction:column;align-items:flex-start}.image-preview img{width:100%;max-width:220px;height:auto;aspect-ratio:1/1}.form-actions{flex-direction:column}.form-actions>*{width:100%}.card-actions>*{width:100%}.card-actions form{width:100%}}
@media (max-width:480px){.mode-switch{grid-template-columns:1fr}}
</style>
</head>
<body>
<nav>
  <div class="logo">TrackMy<span>Property</span></div>
  <div class="nav-links">
    <a href="buy.php">Buy</a>
    <a href="rent.php">Rent</a>
    <a href="<?php echo h(dashboard_url(['mode' => 'sale'], 'listing-panel')); ?>"<?php echo $activeMode === 'sale' ? ' class="is-active"' : ''; ?>>Sell</a>
    <a href="agent.php">Agents</a>
    <a href="enquiries.php">Enquiries</a>
    <a class="user-chip" href="profile.php"><?php echo h($currentUserName); ?></a>
    <a href="logout.php">Logout</a>
  </div>
</nav>

<header>
  <h1>Post Listings</h1>
  <p>Switch between sale and rent-out modes from one dashboard.</p>
</header>

<main class="merged-shell">
  <section class="intro-card">
    <h2>Listing Dashboard</h2>
    <p>Choose a mode below and manage both sale and rental listings without leaving this page.</p>
    <div class="intro-actions">
      <div class="mode-switch" aria-label="Listing mode switch">
        <a class="mode-pill <?php echo $activeMode === 'sale' ? 'is-active' : ''; ?>" href="<?php echo h(dashboard_url(['mode' => 'sale'], 'listing-panel')); ?>">Sell</a>
        <a class="mode-pill <?php echo $activeMode === 'rent' ? 'is-active' : ''; ?>" href="<?php echo h(dashboard_url(['mode' => 'rent'], 'listing-panel')); ?>">Rent Out</a>
      </div>
      <a class="jump-link" href="#listing-panel">Jump To Form</a>
    </div>
    <div class="count-grid">
      <div class="count-card">
        <strong><?php echo $saleCount; ?></strong>
        <span>Sale listings</span>
      </div>
      <div class="count-card">
        <strong><?php echo $rentCount; ?></strong>
        <span>Rent listings</span>
      </div>
      <div class="count-card">
        <strong><?php echo h($currentUserName); ?></strong>
        <span>Current account</span>
      </div>
    </div>
  </section>

  <section id="listing-panel" class="anchor-section">
    <?php if ($activeMode === 'rent'): ?>
      <div class="section-head-merged">
        <div>
          <h2>Rent Listings</h2>
          <p>Create or manage properties you want to rent out.</p>
        </div>
      </div>

      <div class="publish-layout">
        <section class="panel">
          <h3><?php echo $rentEditingListing !== null ? 'Edit Rent Listing' : 'Add Rent Listing'; ?></h3>
          <p class="panel-copy">Use this mode for rent, deposit, availability, and tenant terms.</p>

          <?php if ($rentFlashMessage !== ''): ?>
            <div class="flash"><?php echo h($rentFlashMessage); ?></div>
          <?php endif; ?>

          <?php if ($rentErrors): ?>
            <div class="error-list">
              <ul>
                <?php foreach ($rentErrors as $error): ?>
                  <li><?php echo h($error); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form class="form-grid" method="POST" action="<?php echo h($rentEditingListing !== null ? dashboard_url(['mode' => 'rent', 'rent_edit' => (int) ($rentEditingListing['id'] ?? 0)], 'listing-panel') : dashboard_url(['mode' => 'rent'], 'listing-panel')); ?>" enctype="multipart/form-data">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="<?php echo $rentEditingListing !== null ? 'rent_update' : 'rent_create'; ?>">
            <?php if ($rentEditingListing !== null): ?>
              <input type="hidden" name="listing_id" value="<?php echo (int) ($rentEditingListing['id'] ?? 0); ?>">
            <?php endif; ?>

            <input name="title" placeholder="Rental Title" value="<?php echo h($rentForm['title']); ?>" required>
            <input name="city" placeholder="City" value="<?php echo h($rentForm['city']); ?>" required>
            <input name="locality" placeholder="Locality" value="<?php echo h($rentForm['locality']); ?>" required>
            <input name="rent" type="number" step="0.01" min="1" placeholder="Monthly Rent (₹)" value="<?php echo h($rentForm['rent']); ?>" required>
            <input name="deposit" type="number" step="0.01" min="0" placeholder="Deposit (₹)" value="<?php echo h($rentForm['deposit']); ?>" required>
            <input name="area" type="number" step="0.01" min="1" placeholder="Area (sqft)" value="<?php echo h($rentForm['area']); ?>" required>

            <select name="type" required>
              <option value="">Rental Type</option>
              <?php foreach ($rentTypes as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $rentForm['type'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <select name="beds" required>
              <option value="">Bedrooms</option>
              <?php foreach ($rentBedOptions as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $rentForm['beds'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <select name="baths" required>
              <option value="">Bathrooms</option>
              <?php foreach ($rentBathOptions as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $rentForm['baths'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <select name="furnish" required>
              <option value="">Furnishing</option>
              <?php foreach ($rentFurnishOptions as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $rentForm['furnish'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <select name="available_from" required>
              <option value="">Available From</option>
              <?php foreach ($rentAvailableOptions as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $rentForm['available_from'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <select name="tenant_type" required>
              <option value="">Tenant Type</option>
              <?php foreach ($rentTenantOptions as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $rentForm['tenant_type'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <select name="parking" required>
              <option value="">Parking</option>
              <?php foreach ($rentParkingOptions as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $rentForm['parking'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <select name="pet_friendly" required>
              <option value="">Pet Friendly</option>
              <?php foreach ($rentPetOptions as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $rentForm['pet_friendly'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <div class="field-full">
              <input type="file" name="rent_image" accept="image/*" <?php echo $rentEditingListing !== null ? '' : 'required'; ?>>
              <p class="field-note"><?php echo $rentEditingListing !== null ? 'Upload a new image only if you want to replace the current one.' : 'A rental image is required for new listings.'; ?></p>
            </div>

            <?php if ($rentEditingListing !== null && !empty($rentEditingListing['image_path'])): ?>
              <div class="field-full image-preview">
                <img src="<?php echo h(image_first_url((string) $rentEditingListing['image_path'], RENT_FALLBACK_IMAGE)); ?>" alt="Current rent image">
                <div>
                  <strong>Current image</strong>
                  <p class="field-note">This image will stay unless you upload a new one.</p>
                </div>
              </div>
            <?php endif; ?>

            <textarea class="field-full" name="desc" placeholder="Rental Description"><?php echo h($rentForm['desc']); ?></textarea>

            <div class="field-full form-actions">
              <button type="submit"><?php echo $rentEditingListing !== null ? 'Update Rent Listing' : 'Publish Rent Listing'; ?></button>
              <?php if ($rentEditingListing !== null): ?>
                <a class="ghost-link" href="<?php echo h(dashboard_url(['mode' => 'rent'], 'listing-panel')); ?>">Cancel Edit</a>
              <?php endif; ?>
            </div>
          </form>
        </section>

        <aside class="panel">
          <h3>Rent Snapshot</h3>
          <p class="panel-copy">Keep the rental listing practical: rent, deposit, availability, and tenant fit.</p>
          <div class="tip">
            <strong>Same dashboard</strong>
            Switch back to sale mode at any time without leaving this page.
          </div>
          <div class="tip">
            <strong>Current rent count</strong>
            You have <?php echo $rentCount; ?> <?php echo $rentCount === 1 ? 'rent listing' : 'rent listings'; ?>.
          </div>
          <div class="tip">
            <strong>Ownership locked</strong>
            Only your own rent listings can be edited or deleted here.
          </div>
        </aside>
      </div>

      <div class="listing-block">
        <?php if (!$rentListings): ?>
          <div class="empty">No rent listings published yet. Add your first rental from the form above.</div>
        <?php else: ?>
          <div class="grid">
            <?php foreach ($rentListings as $listing): ?>
              <?php
                $title = (string) ($listing['title'] ?? '');
                $location = trim((string) ($listing['locality'] ?? '') . ', ' . (string) ($listing['city'] ?? ''));
                $rentLabel = format_money_display($listing['rent'] ?? 0);
                $area = (string) ($listing['area'] ?? '');
                $beds = (int) ($listing['beds'] ?? 0);
                $baths = (int) ($listing['baths'] ?? 0);
                $type = (string) ($listing['type'] ?? '');
                $furnish = (string) ($listing['furnish'] ?? '');
                $availableFrom = (string) ($listing['available_from'] ?? '');
                $tenantType = (string) ($listing['tenant_type'] ?? '');
                $desc = trim((string) ($listing['description'] ?? ''));
                $img = image_first_url((string) ($listing['image_path'] ?? ''), RENT_FALLBACK_IMAGE);
              ?>
              <article class="card">
                <img src="<?php echo h($img); ?>" class="card-img" alt="<?php echo h($title); ?>">
                <div class="card-body">
                  <div class="card-price"><?php echo h($rentLabel); ?> / month</div>
                  <div class="card-title"><?php echo h($title); ?></div>
                  <div class="card-loc"><?php echo h($location); ?></div>
                  <div class="tag-row">
                    <span class="tag"><?php echo h($type); ?></span>
                    <span class="tag"><?php echo $beds; ?> Beds</span>
                    <span class="tag"><?php echo $baths; ?> Baths</span>
                    <span class="tag"><?php echo h($furnish); ?></span>
                    <span class="tag"><?php echo h($availableFrom); ?></span>
                    <span class="tag"><?php echo h($tenantType); ?></span>
                    <span class="tag"><?php echo h(number_format((float) $area)); ?> sqft</span>
                  </div>
                  <p class="card-copy"><?php echo h($desc !== '' ? $desc : 'No description added yet.'); ?></p>
                  <div class="card-actions">
                    <a class="ghost-link" href="<?php echo h(dashboard_url(['mode' => 'rent', 'rent_edit' => (int) ($listing['id'] ?? 0)], 'listing-panel')); ?>">Edit</a>
                    <form method="POST" action="<?php echo h(dashboard_url(['mode' => 'rent'], 'listing-panel')); ?>" onsubmit="return confirm('Delete this rent listing?');">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="rent_delete">
                      <input type="hidden" name="listing_id" value="<?php echo (int) ($listing['id'] ?? 0); ?>">
                      <button type="submit">Delete</button>
                    </form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="section-head-merged">
        <div>
          <h2>Sale Listings</h2>
          <p>Create or manage properties you want to sell.</p>
        </div>
      </div>

      <div class="publish-layout">
        <section class="panel">
          <h3><?php echo $saleEditingProperty !== null ? 'Edit Sale Listing' : 'Add Sale Listing'; ?></h3>
          <p class="panel-copy">Use this mode for buyer-facing sale listings.</p>

          <?php if ($saleFlashMessage !== ''): ?>
            <div class="flash"><?php echo h($saleFlashMessage); ?></div>
          <?php endif; ?>

          <?php if ($saleErrors): ?>
            <div class="error-list">
              <ul>
                <?php foreach ($saleErrors as $error): ?>
                  <li><?php echo h($error); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form class="form-grid" id="saleForm" method="POST" action="<?php echo h($saleEditingProperty !== null ? dashboard_url(['mode' => 'sale', 'sale_edit' => (int) ($saleEditingProperty['id'] ?? 0)], 'listing-panel') : dashboard_url(['mode' => 'sale'], 'listing-panel')); ?>" enctype="multipart/form-data">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="<?php echo $saleEditingProperty !== null ? 'sale_update' : 'sale_create'; ?>">
            <?php if ($saleEditingProperty !== null): ?>
              <input type="hidden" name="property_id" value="<?php echo (int) ($saleEditingProperty['id'] ?? 0); ?>">
            <?php endif; ?>

            <input name="title" placeholder="Property Title" value="<?php echo h($saleForm['title']); ?>" required>
            <input name="city" placeholder="City" value="<?php echo h($saleForm['city']); ?>" required>
            <input name="price" type="number" step="0.01" min="1" placeholder="Price (₹)" value="<?php echo h($saleForm['price']); ?>" required>
            <input name="area" type="number" step="0.01" min="1" placeholder="Area (sqft)" value="<?php echo h($saleForm['area']); ?>" required>

            <select name="type" required>
              <option value="">Property Type</option>
              <?php foreach ($salePropertyTypes as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $saleForm['type'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <select name="beds" required>
              <option value="">Bedrooms</option>
              <?php foreach ($saleBedOptions as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $saleForm['beds'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <select name="furnish" required>
              <option value="">Furnishing</option>
              <?php foreach ($saleFurnishOptions as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $saleForm['furnish'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <select name="parking" required>
              <option value="">Parking</option>
              <?php foreach ($saleParkingOptions as $option): ?>
                <option value="<?php echo h($option); ?>" <?php echo $saleForm['parking'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
              <?php endforeach; ?>
            </select>

            <input name="age" type="number" min="0" step="1" placeholder="Property Age (Years)" value="<?php echo h($saleForm['age']); ?>">

            <div class="field-full estimate-card">
              <div class="estimate-head">
                <div>
                  <strong>Flask Price Estimator</strong>
                  <span class="estimate-note">Uses the Python microservice to suggest a price per sqft.</span>
                </div>
                <button class="ghost-link" type="button" id="saleEstimateBtn">Estimate Price</button>
              </div>
              <div class="estimate-output" id="saleEstimateOutput" aria-live="polite">Fill city, area, property type, and bedrooms to get an estimate.</div>
              <div class="estimate-actions">
                <button class="ghost-link" type="button" id="saleUseEstimateBtn" disabled>Use Estimate</button>
              </div>
            </div>

            <div class="field-full">
              <input type="file" name="sale_image" accept="image/*" <?php echo $saleEditingProperty !== null ? '' : 'required'; ?>>
              <p class="field-note"><?php echo $saleEditingProperty !== null ? 'Upload a new image only if you want to replace the current one.' : 'A property image is required for new listings.'; ?></p>
            </div>

            <?php if ($saleEditingProperty !== null && !empty($saleEditingProperty['image_path'])): ?>
              <div class="field-full image-preview">
                <img src="<?php echo h(image_first_url((string) $saleEditingProperty['image_path'], SALE_FALLBACK_IMAGE)); ?>" alt="Current sale image">
                <div>
                  <strong>Current image</strong>
                  <p class="field-note">This image will stay unless you upload a new one.</p>
                </div>
              </div>
            <?php endif; ?>

            <textarea class="field-full" name="desc" placeholder="Property Description"><?php echo h($saleForm['desc']); ?></textarea>

            <div class="field-full form-actions">
              <button type="submit"><?php echo $saleEditingProperty !== null ? 'Update Sale Listing' : 'Publish Sale Listing'; ?></button>
              <?php if ($saleEditingProperty !== null): ?>
                <a class="ghost-link" href="<?php echo h(dashboard_url(['mode' => 'sale'], 'listing-panel')); ?>">Cancel Edit</a>
              <?php endif; ?>
            </div>
          </form>
        </section>

        <aside class="panel">
          <h3>Sale Snapshot</h3>
          <p class="panel-copy">Keep the sale listing focused on the buyer-facing essentials.</p>
          <div class="tip">
            <strong>Same dashboard</strong>
            Switch to rent mode at any time without leaving this page.
          </div>
          <div class="tip">
            <strong>Current sale count</strong>
            You have <?php echo $saleCount; ?> <?php echo $saleCount === 1 ? 'sale listing' : 'sale listings'; ?>.
          </div>
          <div class="tip">
            <strong>Ownership locked</strong>
            Only your own sale listings can be edited or deleted here.
          </div>
        </aside>
      </div>

      <div class="listing-block">
        <?php if (!$saleListings): ?>
          <div class="empty">No sale listings published yet. Add your first listing from the form above.</div>
        <?php else: ?>
          <div class="grid">
            <?php foreach ($saleListings as $property): ?>
              <?php
                $title = (string) ($property['title'] ?? '');
                $city = (string) ($property['city'] ?? '');
                $price = format_money_display($property['price'] ?? 0);
                $area = (string) ($property['area'] ?? '');
                $beds = (string) ($property['beds'] ?? '');
                $type = (string) ($property['type'] ?? '');
                $furnish = (string) ($property['furnish'] ?? '');
                $parking = (string) ($property['parking'] ?? '');
                $age = $property['age'] === null ? 'New / Not specified' : (string) $property['age'] . ' yrs';
                $desc = trim((string) ($property['description'] ?? ''));
                $img = image_first_url((string) ($property['image_path'] ?? ''), SALE_FALLBACK_IMAGE);
              ?>
              <article class="card">
                <img src="<?php echo h($img); ?>" class="card-img" alt="<?php echo h($title); ?>">
                <div class="card-body">
                  <div class="card-price"><?php echo h($price); ?></div>
                  <div class="card-title"><?php echo h($title); ?></div>
                  <div class="card-loc"><?php echo h($city); ?> • <?php echo h($area); ?> sqft</div>
                  <div class="tag-row">
                    <span class="tag"><?php echo h($type); ?></span>
                    <span class="tag"><?php echo h($beds); ?> Beds</span>
                    <span class="tag"><?php echo h($furnish); ?></span>
                    <span class="tag">Parking: <?php echo h($parking); ?></span>
                    <span class="tag">Age: <?php echo h($age); ?></span>
                  </div>
                  <p class="card-copy"><?php echo h($desc !== '' ? $desc : 'No description added yet.'); ?></p>
                  <div class="card-actions">
                    <a class="ghost-link" href="<?php echo h(dashboard_url(['mode' => 'sale', 'sale_edit' => (int) ($property['id'] ?? 0)], 'listing-panel')); ?>">Edit</a>
                    <form method="POST" action="<?php echo h(dashboard_url(['mode' => 'sale'], 'listing-panel')); ?>" onsubmit="return confirm('Delete this sale listing?');">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="sale_delete">
                      <input type="hidden" name="property_id" value="<?php echo (int) ($property['id'] ?? 0); ?>">
                      <button type="submit">Delete</button>
                    </form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<footer>
  <div class="copyright">© 2026 TrackMyProperty. Sale listings dashboard.</div>
</footer>
<script>
(() => {
  const FLASK_BASE = 'http://127.0.0.1:5000';
  const saleForm = document.getElementById('saleForm');
  if (!saleForm) {
    return;
  }

  const estimateBtn = document.getElementById('saleEstimateBtn');
  const useBtn = document.getElementById('saleUseEstimateBtn');
  const output = document.getElementById('saleEstimateOutput');
  const priceInput = saleForm.querySelector('[name="price"]');
  const cityInput = saleForm.querySelector('[name="city"]');
  const areaInput = saleForm.querySelector('[name="area"]');
  const typeInput = saleForm.querySelector('[name="type"]');
  const bedsInput = saleForm.querySelector('[name="beds"]');

  if (!estimateBtn || !useBtn || !output || !priceInput) {
    return;
  }

  const setOutput = (message, estimateValue) => {
    output.textContent = message;
    output.dataset.estimate = estimateValue ? String(estimateValue) : '';
  };

  estimateBtn.addEventListener('click', async () => {
    const city = cityInput ? cityInput.value.trim() : '';
    const area = areaInput ? areaInput.value : '';
    const propertyType = typeInput ? typeInput.value : '';
    const bedrooms = bedsInput ? bedsInput.value : '';

    if (!city || !area || !propertyType || !bedrooms) {
      setOutput('Please fill city, area, property type, and bedrooms first.');
      useBtn.disabled = true;
      return;
    }

    setOutput('Contacting Flask service for estimate...');
    useBtn.disabled = true;

    try {
      const response = await fetch(`${FLASK_BASE}/api/price-estimate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          city,
          area_sqft: area,
          property_type: propertyType,
          bedrooms,
        }),
      });

      if (!response.ok) {
        throw new Error('Bad response');
      }

      const data = await response.json();
      const estimate = Number(data.estimate || 0);
      const perSqft = Number(data.per_sqft || 0);
      const confidence = data.confidence || 'medium';

      if (!estimate) {
        throw new Error('Missing estimate');
      }

      const perSqftText = perSqft
        ? ` (₹${perSqft.toLocaleString()}/sqft, ${confidence} confidence)`
        : ` (${confidence} confidence)`;
      setOutput(`Estimated price: ₹${estimate.toLocaleString()}${perSqftText}`, estimate);
      useBtn.disabled = false;
    } catch (error) {
      setOutput('Flask service is not reachable. Start the Flask API to get estimates.');
      useBtn.disabled = true;
    }
  });

  useBtn.addEventListener('click', () => {
    const estimate = Number(output.dataset.estimate || 0);
    if (estimate > 0) {
      priceInput.value = String(estimate);
    }
  });
})();
</script>
</body>
</html>
