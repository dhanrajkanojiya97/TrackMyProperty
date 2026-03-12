<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';

sign_out_user();

header('Location: login.php');
exit;
