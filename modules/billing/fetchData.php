<?php


session_start();
include_once '../config.php'; // Ensure this path is correct
function fetchCategories($conn, $serviceTypeId)
{
    $stmt = $conn->prepare("SELECT id, category_name FROM billing_service_categories WHERE service_type_id = ? AND is_deleted = 0 ORDER BY category_name ASC");
    if (!$stmt) {
        error_log("Failed to prepare statement for fetchCategories: " . $conn->error);
        return ['error' => 'error_prepare'];
    }

    $stmt->bind_param("i", $serviceTypeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];

    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }

    return $categories;
}

function fetchUnitPrice($conn, $serviceCategoryId,$companyId)
{
    $stmt = $conn->prepare("SELECT unit_price, vat_rate FROM billing_category_prices WHERE service_category_id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare statement for fetchUnitPrice: " . $conn->error);
        return ['error' => 'error_prepare'];
    }

    $stmt->bind_param("i", $serviceCategoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    $price = $result->fetch_assoc();
    $stmt->close();
    // fetch company also
    $stmt = $conn->prepare("SELECT * FROM billing_invoice_companies WHERE id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare statement for fetchUnitPrice: " . $conn->error);
        return ['error' => 'error_prepare'];
    }
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $company = $result->fetch_assoc();
    $stmt->close();
    if ($price) {
        return [
            'unit_price' => $price['unit_price'],
            'vat' => $price['vat_rate'],
            'company_vat' => $company['vat_rate']
        ];
    } else {
        return [
            'unit_price' => 0,
            'vat' => 0,
            'company_vat' => $company['vat_rate']
        ];
    }
}

if (isset($_POST['action'])) {
    header('Content-Type: application/json'); // Set response type to JSON
    switch ($_POST['action']) {
        case 'fetch_categories':
            $serviceTypeId = (int) ($_POST['service_type_id'] ?? 0);
            if ($serviceTypeId > 0) {
                $categories = fetchCategories($conn, $serviceTypeId);
                echo json_encode($categories);
            } else {
                echo json_encode([]);
            }
            exit;
        case 'fetch_unit_price':
            $serviceCategoryId = (int) ($_POST['service_category_id'] ?? 0);
            if ($serviceCategoryId > 0) {
                $price = fetchUnitPrice($conn, $serviceCategoryId,$_POST['company_id']);
                echo json_encode($price);
            } else {
                echo json_encode([
                    'unit_price' => 0,
                    'vat' => 0
                ]);
            }
            exit;
        case 'fetch_quote_details':
            $quoteId = (int) ($_POST['quote_id'] ?? 0);
            if ($quoteId > 0) {
                // Fetch main quote details
                $quote = $conn->query("
                    SELECT q.*, 
                           c.client_name, 
                           c.currency,
                            c.currency_symbol,
                           ic.company_name AS invoice_company_name
                    FROM quotes q
                    LEFT JOIN clients c ON q.client_id = c.id
                    LEFT JOIN billing_invoice_companies ic ON q.quoted_company_id = ic.id
                    WHERE q.id = $quoteId
                ")->fetch_assoc();

                // Fetch quote items
                $items = [];
                $result = $conn->query("
                    SELECT qi.*, 
                           sc.category_name, 
                           st.service_type_name
                    FROM quote_items qi
                    LEFT JOIN billing_service_categories sc ON qi.service_category_id = sc.id
                    LEFT JOIN billing_service_types st ON sc.service_type_id = st.id
                    WHERE qi.quote_id = $quoteId
                ");
                while ($row = $result->fetch_assoc()) {
                    $items[] = $row;
                }

                echo json_encode(['quote' => $quote, 'items' => $items]);
            } else {
                echo json_encode(['error' => 'Invalid quote ID']);
            }
            exit;
            case 'fetch_currency_from_client':
                $clientId = (int) ($_POST['client_id'] ?? 0);
                if ($clientId > 0) {
                    $currency = $conn->query("
                        SELECT *
                        FROM clients
                        WHERE id = $clientId
                    ")->fetch_assoc();
                    echo json_encode($currency);
                } else {
                    echo json_encode(['error' => 'Invalid client ID']);
                }
                exit;
        default:
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'invalid_action']);
            exit;
    }
}
?>