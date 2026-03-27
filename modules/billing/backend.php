<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
// Database Connection
include_once '../config.php'; // Ensure this path is correct
if (isset($_POST['action']) && $_POST['action'] === 'billing_price_increase') {
    $billing_item_ids = $_POST['billing_item_ids'] ?? [];
    $percentage = (float) ($_POST['percentage'] ?? 0);

    if (empty($billing_item_ids) || $percentage <= 0) {
        echo "error_invalid_input";
        exit;
    }

    $billing_item_ids = array_map('intval', $billing_item_ids); // Sanitize IDs
    $billing_item_ids_str = implode(',', $billing_item_ids);

    $sql = "
        UPDATE billing_items 
        SET unit_price = unit_price + (unit_price * ($percentage / 100))
        WHERE id IN ($billing_item_ids_str)
    ";

    if ($conn->query($sql)) {
        echo "success";
    } else {
        echo "error_execute: " . $conn->error;
    }
    exit;
}
// Add
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $client_id = (int) ($_POST['client_id'] ?? 0);
    $supplier_id = (int) ($_POST['supplier_id'] ?? 0);
    $service_type_id = (int) ($_POST['service_type_id'] ?? 0);
    $service_category_id = !empty($_POST['service_category_id']) ? (int) $_POST['service_category_id'] : 'NULL';
    $invoicing_company_id = (int) ($_POST['invoicing_company_id'] ?? 0);
    $description = $_POST['description'] ?? '';
    $quantity = (int) ($_POST['quantity'] ?? 1);
    $unit_price = (float) ($_POST['unit_price'] ?? 0);
    $vat_rate = (float) ($_POST['vat_rate'] ?? 0);
    $charge_vat = isset($_POST['charge_vat']) ? (int) $_POST['charge_vat'] : 0;
    $invoice_frequency = $_POST['invoice_frequency'] ?? 'monthly';
    $start_date = !empty($_POST['start_date']) && strtotime($_POST['start_date']) ? date('Y-m-d', strtotime($_POST['start_date'])) : null;

    // Automatically set end_date for 'once_off' or 'annually' if not provided
    if (in_array($_POST['invoice_frequency'], ['once_off', 'annually']) && $start_date !== null && empty($_POST['end_date'])) {
        $end_date = date('Y-m-d', strtotime('+1 month', strtotime($start_date)));
    } else {
        $end_date = !empty($_POST['end_date']) && strtotime($_POST['end_date']) ? date('Y-m-d', strtotime($_POST['end_date'])) : null;
    }

    if ($client_id <= 0) {
        echo "error_invalid_client_id";
        exit;
    }

    // Fetch billing_type, currency, and currency_symbol from clients table
    $billing_type = null;
    $currency = null;
    $currency_symbol = null;
    $clientName = null;

    $clientStmt = $conn->prepare("SELECT billing_type, currency, currency_symbol, client_name FROM clients WHERE id = ?");
    $clientStmt->bind_param("i", $client_id);
    $clientStmt->execute();
    $clientResult = $clientStmt->get_result();

    if ($clientData = $clientResult->fetch_assoc()) {
        $billing_type = $clientData['billing_type'];
        $currency = $clientData['currency'];
        $currency_symbol = $clientData['currency_symbol'];
        $clientName = $clientData['client_name'];

        // If currency_symbol is null or empty, use the first letter of the currency
        if (empty($currency_symbol)) {
            $currency_symbol = strtoupper(substr($currency, 0, 1));
        }
    } else {
        echo "error_client_not_found";
        exit;
    }
    // invoicing company
    if ($invoicing_company_id > 0) {
    // Prepare the SQL statement
    $companyStmt = $conn->prepare("SELECT company_name FROM billing_invoice_companies WHERE id = ?");
    $companyStmt->bind_param("i", $invoicing_company_id);
    $companyStmt->execute();

    // Get the result
    $companyResult = $companyStmt->get_result();

    // Fetch the row
    if ($companyRow = $companyResult->fetch_assoc()) {
       $invoicing_company_name = $companyRow['company_name'];
    } else {
        echo "Company not found.";
    }

 
}
    // VM Fields
    $cpu = $_POST['cpu'] ?? null;
    $memory = $_POST['memory'] ?? null;
    $hdd_sata = $_POST['hdd_sata'] ?? null;
    $hdd_ssd = $_POST['hdd_ssd'] ?? null;
    $os = $_POST['os'] ?? null;
    $ip_address = $_POST['ip_address'] ?? null;

    // Escape and sanitize inputs
    $clientName = mysqli_real_escape_string($conn, $clientName);
    $description = mysqli_real_escape_string($conn, $description);
    $invoice_frequency = mysqli_real_escape_string($conn, $invoice_frequency);
    $start_date = $start_date !== null ? mysqli_real_escape_string($conn, $start_date) : null;
    $end_date = $end_date !== null ? mysqli_real_escape_string($conn, $end_date) : null;
    $cpu = $cpu !== null ? mysqli_real_escape_string($conn, $cpu) : null;
    $memory = $memory !== null ? mysqli_real_escape_string($conn, $memory) : null;
    $hdd_sata = $hdd_sata !== null ? mysqli_real_escape_string($conn, $hdd_sata) : null;
    $hdd_ssd = $hdd_ssd !== null ? mysqli_real_escape_string($conn, $hdd_ssd) : null;
    $os = $os !== null ? mysqli_real_escape_string($conn, $os) : null;
    $ip_address = $ip_address !== null ? mysqli_real_escape_string($conn, $ip_address) : null;
    $billing_type = mysqli_real_escape_string($conn, $billing_type);
    $currency = mysqli_real_escape_string($conn, $currency);
    $currency_symbol = mysqli_real_escape_string($conn, $currency_symbol);
    $created_by = $_SESSION['user_id'];
    // Insert into billing_items
    $service_category_sql = ($service_category_id === 'NULL') ? 'NULL' : $service_category_id;
    $sql = "
    INSERT INTO billing_items (
    created_by,
        client_name, client_id, supplier_id, service_type_id, service_category_id,
        description, qty, unit_price, vat_rate, vat_applied,
        frequency, start_date, end_date, cpu, memory,
        hdd_sata, hdd_ssd, os, ip_address, invoice_type, currency, currency_symbol,
        invoicing_company_id, invoicing_company_name
    ) VALUES (
        '$created_by',
        '$clientName', $client_id, $supplier_id, $service_type_id, $service_category_sql,
        '$description', $quantity, $unit_price, $vat_rate, $charge_vat,
        '$invoice_frequency', '$start_date', '$end_date', '$cpu', '$memory',
        '$hdd_sata', '$hdd_ssd', '$os', '$ip_address', '$billing_type', '$currency', '$currency_symbol',
        $invoicing_company_id, '$invoicing_company_name'
    )";

    if (mysqli_query($conn, $sql)) {
        echo "success";
    } else {
        echo "error_execute: " . mysqli_error($conn);
    }
    exit;
}

// unit price
// 📥 FETCH Unit Price when Service Category Selected
if (isset($_POST['action']) && $_POST['action'] === 'fetch_unit_price') {
    $service_category_id = (int) ($_POST['service_category_id'] ?? 0);

    if ($service_category_id > 0) {
        $stmt = $conn->prepare("SELECT unit_price FROM billing_category_prices WHERE service_category_id = ? LIMIT 1");
        if (!$stmt) {
            echo json_encode(['error' => 'error_prepare']);
            exit;
        }

        $stmt->bind_param("i", $service_category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $price = $result->fetch_assoc();

        if ($price) {
            echo json_encode([
                'unit_price' => $price['unit_price']
            ]);
        } else {
            echo json_encode([
                'unit_price' => 0
            ]);
        }
    } else {
        echo json_encode([
            'unit_price' => 0
        ]);
    }
    exit;
}


// ✏️ Edit Billing Item
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int) ($_POST['billing_id'] ?? 0);
    $client_id = (int) ($_POST['client_id'] ?? 0);
    $supplier_id = (int) ($_POST['supplier_id'] ?? 0);
    $service_type_id = (int) ($_POST['service_type_id'] ?? 0);
    $service_category_id = !empty($_POST['service_category_id']) ? (int) $_POST['service_category_id'] : 'NULL';
    $invoicing_company_id = (int) ($_POST['invoicing_company_id'] ?? 0);
    $description = $_POST['description'] ?? '';
    $quantity = (int) ($_POST['quantity'] ?? 1);
    $unit_price = (float) ($_POST['unit_price'] ?? 0);
    $vat_rate = (float) ($_POST['vat_rate'] ?? 0);
    $charge_vat = isset($_POST['charge_vat']) ? (int) $_POST['charge_vat'] : 0;
    $invoice_frequency = $_POST['invoice_frequency'] ?? 'monthly';
    $start_date = !empty($_POST['start_date']) && strtotime($_POST['start_date']) ? date('Y-m-d', strtotime($_POST['start_date'])) : null;
    if (in_array($_POST['invoice_frequency'], ['once_off', 'annually']) && $start_date !== null && empty($_POST['end_date'])) {
        $end_date = date('Y-m-d', strtotime('+1 month', strtotime($start_date)));
    } else {
        $end_date = !empty($_POST['end_date']) && strtotime($_POST['end_date']) ? date('Y-m-d', strtotime($_POST['end_date'])) : null;
    }

    if ($client_id <= 0) {
        echo "error_invalid_client_id";
        exit;
    }

    // Fetch billing_type, currency, and currency_symbol from clients table
    $billing_type = null;
    $currency = null;
    $currency_symbol = null;
    $clientName = null;
    $clientStmt = $conn->prepare("SELECT billing_type, currency, currency_symbol, client_name FROM clients WHERE id = ?");
    $clientStmt->bind_param("i", $client_id);
    $clientStmt->execute();
    $clientResult = $clientStmt->get_result();
    if ($clientData = $clientResult->fetch_assoc()) {
        $billing_type = $clientData['billing_type'];
        $currency = $clientData['currency'];
        $currency_symbol = $clientData['currency_symbol'];
        $clientName = $clientData['client_name'];

        // If currency_symbol is null or empty, use the first letter of the currency
        if (empty($currency_symbol)) {
            $currency_symbol = strtoupper(substr($currency, 0, 1));
        }
    } else {
        echo "error_client_not_found";
        exit;
    }

     if ($invoicing_company_id > 0) {
    // Prepare the SQL statement
    $companyStmt = $conn->prepare("SELECT company_name FROM billing_invoice_companies WHERE id = ?");
    $companyStmt->bind_param("i", $invoicing_company_id);
    $companyStmt->execute();

    // Get the result
    $companyResult = $companyStmt->get_result();

    // Fetch the row
    if ($companyRow = $companyResult->fetch_assoc()) {
       $invoicing_company_name = $companyRow['company_name'];
    } 

}
    // VM Fields
    $cpu = $_POST['cpu'] ?? null;
    $memory = $_POST['memory'] ?? null;
    $hdd_sata = $_POST['hdd_sata'] ?? null;
    $hdd_ssd = $_POST['hdd_ssd'] ?? null;
    $os = $_POST['os'] ?? null;
    $ip_address = $_POST['ip_address'] ?? null;

    // Escape and sanitize inputs
    $clientName = mysqli_real_escape_string($conn, $clientName);
    $description = mysqli_real_escape_string($conn, $description);
    $invoice_frequency = mysqli_real_escape_string($conn, $invoice_frequency);
    $start_date = $start_date !== null ? mysqli_real_escape_string($conn, $start_date) : null;
    $end_date = $end_date !== null ? mysqli_real_escape_string($conn, $end_date) : null;
    $cpu = $cpu !== null ? mysqli_real_escape_string($conn, $cpu) : null;
    $memory = $memory !== null ? mysqli_real_escape_string($conn, $memory) : null;
    $hdd_sata = $hdd_sata !== null ? mysqli_real_escape_string($conn, $hdd_sata) : null;
    $hdd_ssd = $hdd_ssd !== null ? mysqli_real_escape_string($conn, $hdd_ssd) : null;
    $os = $os !== null ? mysqli_real_escape_string($conn, $os) : null;
    $ip_address = $ip_address !== null ? mysqli_real_escape_string($conn, $ip_address) : null;
    $billing_type = mysqli_real_escape_string($conn, $billing_type);
    $currency = mysqli_real_escape_string($conn, $currency);
    $currency_symbol = mysqli_real_escape_string($conn, $currency_symbol);

    // Update Query
    $service_category_sql = ($service_category_id === 'NULL') ? 'NULL' : $service_category_id;
    $sql = "
        UPDATE billing_items 
        SET 
            client_name = '$clientName',
            client_id = $client_id, 
            supplier_id = $supplier_id, 
            service_type_id = $service_type_id, 
            service_category_id = $service_category_sql, 
            invoicing_company_id = $invoicing_company_id,
            invoicing_company_name = '$invoicing_company_name',
            description = '$description', 
            qty = $quantity, 
            unit_price = $unit_price, 
            vat_rate = $vat_rate, 
            vat_applied = $charge_vat, 
            frequency = '$invoice_frequency', 
            start_date = '$start_date', 
            end_date = '$end_date', 
            cpu = '$cpu', 
            memory = '$memory', 
            hdd_sata = '$hdd_sata', 
            hdd_ssd = '$hdd_ssd', 
            os = '$os', 
            ip_address = '$ip_address', 
            invoice_type = '$billing_type', 
            currency = '$currency',
            currency_symbol = '$currency_symbol'
        WHERE id = $id
    ";
    if (mysqli_query($conn, $sql)) {
        echo "success";
    } else {
        echo "error_execute: " . mysqli_error($conn);
    }
    exit;
}


// ❌ Delete Billing Item (Soft Delete)
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE billing_items SET is_deleted = 1 WHERE id = ?");
        if (!$stmt) {
            echo "error_prepare";
            exit;
        }

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "error_execute";
        }
    } else {
        echo "error_invalid_id";
    }
    exit;
}

// 📂 FETCH Service Categories
if (isset($_POST['action']) && $_POST['action'] === 'fetch_categories') {
    $service_type_id = (int) ($_POST['service_type_id'] ?? 0);

    if ($service_type_id > 0) {
        $stmt = $conn->prepare("SELECT id, category_name, has_vm_fields FROM billing_service_categories WHERE service_type_id = ? AND is_deleted = 0 ORDER BY category_name ASC");
        if (!$stmt) {
            echo json_encode(['error' => 'error_prepare']);
            exit;
        }

        $stmt->bind_param("i", $service_type_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $categories = [];

        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }

        echo json_encode($categories);
    } else {
        // Return all categories if no service_type_id is provided
        $result = $conn->query("SELECT id, category_name, has_vm_fields FROM billing_service_categories WHERE is_deleted = 0 ORDER BY category_name ASC");
        $categories = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
        }
        
        echo json_encode($categories);
    }
    exit;
}

if ($_POST['action'] == 'fetch') {
    $id = intval($_POST['id']);
    $query = $conn->query("SELECT * FROM billing_suppliers WHERE id = $id LIMIT 1");

    if ($query && $row = $query->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode($row);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Supplier not found']);
    }
    exit;
}