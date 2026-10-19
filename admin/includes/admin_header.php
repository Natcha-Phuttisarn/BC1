<?php
if (!isset($adminPageTitle)) $adminPageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($adminPageTitle) ?> | Bizcomp Club Admin</title>
<link rel="icon" type="image/png" href="<?= BC_BASE_PATH ?>/assets/images/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="<?= BC_BASE_PATH ?>/admin/assets/css/admin.css">
<script src="<?= BC_BASE_PATH ?>/assets/js/image-optimize.js" defer></script>
</head>
<body>
<div class="admin-layout">
