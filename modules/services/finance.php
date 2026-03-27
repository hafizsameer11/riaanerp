<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
$cost = $_GET['cost'] ?? '';
$currency = $_GET['currency'] ?? 'ZAR';
$markup = $_GET['markup'] ?? '';
$interest = $_GET['interest'] ?? '';
$term = $_GET['term'] ?? '';

$result = [];

if (is_numeric($cost) && is_numeric($markup) && is_numeric($interest) && is_numeric($term) && $term > 0) {
    $cost = floatval($cost);
    $markup = floatval($markup);
    $interest = floatval($interest);
    $term = intval($term);

    $markupAmount = $cost * ($markup / 100);
    $baseTotal = $cost + $markupAmount;

    $monthlyInterestRate = $interest / 12 / 100;
    $totalWithInterest = $baseTotal * pow(1 + $monthlyInterestRate, $term);
    $monthlyPayment = $totalWithInterest / $term;

    $result = [
        'currency' => $currency,
        'cost' => $cost,
        'markup_percent' => $markup,
        'interest_annual' => $interest,
        'term_months' => $term,
        'base_total' => round($baseTotal, 2),
        'total_with_interest' => round($totalWithInterest, 2),
        'monthly_payment' => round($monthlyPayment, 2)
    ];
}
include('../components/permissioncheck.php');
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Finance Calculator</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background: #f9f9f9; padding: 20px;">

    <div class="my-4" style="width: 93%; margin: auto;">
        <h2 class="mb-4">Admin Service</h2>

        <!-- First Row of Buttons -->
        <div class="row g-4">

            <?php if (hasPermission('admin service', 'Manage Suppliers')): ?>
                <div class="col-md-3">
                    <a href="supplier/supplier/supplier.php" class="btn btn-primary w-100 p-3">Manage Suppliers</a>
                </div>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Service Types')): ?>
                <div class="col-md-3">
                    <a href="supplier/service-type/service-type.php" class="btn btn-success w-100 p-3">Manage Service
                        Types</a>
                </div>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Service Categories')): ?>
                <div class="col-md-3">
                    <a href="supplier/service-category/billing-service-category.php"
                        class="btn btn-warning w-100 p-3">Manage Service Categories</a>
                </div>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Unit Prices')): ?>
                <div class="col-md-3">
                    <a href="supplier/unit-price/index.php" class="btn btn-danger w-100 p-3">Unit Prices</a>
                </div>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Hosting Assets')): ?>
                <div class="col-md-3">
                    <a href="manage_hosting_assets.php" class="btn btn-info w-100 p-3">Manage Hosting Assets</a>
                </div>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Manage Invoice Companies')): ?>
                <div class="col-md-3">
                    <a href="supplier/invoice-company/index.php" class="btn btn-secondary w-100 p-3">Manage Invoice
                        Companies</a>
                </div>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Finance Calculator')): ?>
                <div class="col-md-3">
                    <a href="Calculator.php" class="btn btn-warning w-100 p-3">Finance Calculator</a>
                </div>
            <?php endif; ?>
            <?php if (hasPermission('admin service', 'Reseller')): ?>
                <div class="col-md-3">
                    <a href="supplier/reseller/reseller.php" class="btn btn-info w-100 p-3">Reseller</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>