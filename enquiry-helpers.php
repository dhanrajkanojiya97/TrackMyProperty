<?php
declare(strict_types=1);

function enquiry_form_defaults(array $session = []): array
{
    return [
        'name' => trim((string) ($session['user_name'] ?? '')),
        'email' => trim((string) ($session['user_email'] ?? '')),
        'phone' => '',
        'message' => '',
    ];
}

function enquiry_form_from_post(array $post, array $fallback = []): array
{
    return [
        'name' => trim((string) ($post['name'] ?? ($fallback['name'] ?? ''))),
        'email' => strtolower(trim((string) ($post['email'] ?? ($fallback['email'] ?? '')))),
        'phone' => trim((string) ($post['phone'] ?? ($fallback['phone'] ?? ''))),
        'message' => trim((string) ($post['message'] ?? ($fallback['message'] ?? ''))),
    ];
}

function validate_enquiry_form(array $form): array
{
    $errors = [];

    if (($form['name'] ?? '') === '') {
        $errors[] = 'Your name is required.';
    }

    $email = (string) ($form['email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address or leave it empty.';
    }

    $phone = (string) ($form['phone'] ?? '');
    if ($phone === '' || preg_match('/^\+?[0-9][0-9\s-]{8,17}$/', $phone) !== 1) {
        $errors[] = 'Enter a valid phone number.';
    }

    if (strlen((string) ($form['message'] ?? '')) > 1000) {
        $errors[] = 'Message must be 1000 characters or fewer.';
    }

    return $errors;
}

function create_property_enquiry(PDO $pdo, int $ownerId, int $propertyId, array $form): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO enquiries
            (owner_id, property_id, rent_listing_id, listing_type, sender_name, sender_email, sender_phone, message)
         VALUES
            (?, ?, NULL, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $ownerId,
        $propertyId,
        'sale',
        $form['name'],
        $form['email'] !== '' ? $form['email'] : null,
        $form['phone'],
        $form['message'] !== '' ? $form['message'] : null,
    ]);
}

function create_rent_enquiry(PDO $pdo, int $ownerId, int $rentListingId, array $form): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO enquiries
            (owner_id, property_id, rent_listing_id, listing_type, sender_name, sender_email, sender_phone, message)
         VALUES
            (?, NULL, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $ownerId,
        $rentListingId,
        'rent',
        $form['name'],
        $form['email'] !== '' ? $form['email'] : null,
        $form['phone'],
        $form['message'] !== '' ? $form['message'] : null,
    ]);
}
