<?php
session_start();
// Database Connection
include_once '../config.php'; // Ensure this path is correct
require_once '../../vendor/autoload.php';
// require_once '../../helper/email_helper.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../../TCPDF/tcpdf.php';

// Check if an action is provided
if (isset($_POST['action'])) {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'send_quote') {

        $quote_id = (int) $_POST['quote_id'];
        $sender_name = trim($_POST['sender_name']);
        $recipient_name = trim($_POST['recipient_name']);
        $recipient_email = trim($_POST['recipient_email']);
        $message = trim($_POST['message']);
        if (empty($message)) {
            $message = "Please find the attached quote.";
        }

        // Fetch quote
        $stmt = $conn->prepare("SELECT q.*, c.client_name,c.currency,c.currency_symbol,  b.company_name FROM quotes q
        JOIN clients c ON q.client_id = c.id
        JOIN billing_invoice_companies b ON q.quoted_company_id = b.id
        WHERE q.id = ?");
        $stmt->bind_param("i", $quote_id);
        $stmt->execute();
        $quote = $stmt->get_result()->fetch_assoc();

        if (!$quote) {
            $_SESSION['error'] = "Quote not found.";
            header("Location: quotes.php");
            exit;
        }

        // Fetch quote items
        $itemsQuery = $conn->query("SELECT 
        st.service_type_name, 
        sc.category_name, 
        qi.qty, 
        qi.unit_price, 
        qi.vat, 
        qi.description, 
        qi.total_incl_vat 
        FROM quote_items qi
        JOIN billing_service_types st ON qi.service_type_id = st.id
        JOIN billing_service_categories sc ON qi.service_category_id = sc.id
        WHERE qi.quote_id = $quote_id");

        // 1. Generate PDF using TCPDF
        $pdf = new TCPDF();
        $pdf->SetCreator($sender_name);
        $pdf->SetAuthor($sender_name);
        $pdf->SetTitle("Quote {$quote['quote_number']}");
        $pdf->SetSubject("Quote PDF");

        $pdf->AddPage();

        $html = "
    <style>
        body { font-family: DejaVu Sans; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f4f4f4; }
        .header { margin-bottom: 10px; }
        .totals { font-weight: bold; background-color: #f9f9f9; }
    </style>

    <h1>Quote</h1>
    <table class='header'>
        <tr><td><strong>Quote #:</strong> {$quote['quote_number']}</td><td><strong>Status:</strong> {$quote['status']}</td></tr>
        <tr><td><strong>Client:</strong> {$quote['client_name']}</td><td><strong>Invoice Company:</strong> {$quote['company_name']}</td></tr>
        <tr><td colspan='2'><strong>Note:</strong> {$quote['description']}</td></tr>
        <tr><td><strong>Sales Person:</strong> {$quote['sales_person']}</td><td><strong>Date:</strong> " . date('Y-m-d') . "</td></tr>
    </table>

    <h3>Quote Items</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Service Type</th>
                <th>Category</th>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Price Ex VAT</th> <!-- New column -->
                <th>VAT %</th>
                <th>Total Incl VAT</th>
            </tr>
        </thead>
        <tbody>";

        $total_ex_vat = 0;
        $total_vat = 0;
        $total_incl_vat = 0;

        $i = 1;
        while ($item = $itemsQuery->fetch_assoc()) {
            $price_ex_vat = $item['qty'] * $item['unit_price'];
            $vat_amount = $price_ex_vat * ($item['vat'] / 100);
            $total_incl_vat_item = $price_ex_vat + $vat_amount;

            $total_ex_vat += $price_ex_vat;
            $total_vat += $vat_amount;
            $total_incl_vat += $total_incl_vat_item;

            $html .= "<tr>
        <td>{$i}</td>
        <td>{$item['service_type_name']}</td>
        <td>{$item['category_name']}</td>
        <td>{$item['description']}</td>
        <td>{$item['qty']}</td>
        <td>" . number_format($item['unit_price'], 2) . "</td>
        <td>" . number_format($price_ex_vat, 2) . "</td> <!-- New column -->
        <td>{$item['vat']}%</td>
        <td>" . number_format($total_incl_vat_item, 2) . "</td>
    </tr>";
            $i++;
        }

        // Add totals row with heading aligned to the right, with each total in a separate td
        $html .= "
        </tbody>
        </table>
        ";
        $html .= "
<h5>Totals</h5>
<table style='width: 100%; margin-bottom: 10px;'>
    <tr>
        <td style='width: 20%;'><strong>Sub Total:</strong> " . number_format($total_ex_vat, 2) . "</td>
        <td style='width: 20%;'><strong>Discount (%):</strong> " . number_format($quote['discount'], 2) . "%</td>
        <td style='width: 20%;'><strong>Discount Amount:</strong> " . number_format(($total_ex_vat * $quote['discount'] / 100), 2) . "</td>
        <td style='width: 20%;'><strong>Total VAT:</strong> " . number_format($total_vat, 2) . "</td>
        <td style='width: 20%;'><strong>Total After Discount:</strong> " . ($quote['currency_symbol'] ?? (isset($quote['currency'][0]) ? $quote['currency'][0] : '')) . " " . number_format($total_ex_vat - ($total_ex_vat * $quote['discount'] / 100), 2) . "</td>
        <td style='width: 20%;'><strong>Grand Total:</strong> " . ($quote['currency_symbol'] ?? (isset($quote['currency'][0]) ? $quote['currency'][0] : '')) . " " . number_format($total_incl_vat, 2) . "</td>
    </tr>
</table>";

        $pdf->writeHTML($html, true, false, true, false, '');

        $pdfFilePath = sys_get_temp_dir() . "/quote_{$quote['quote_number']}.pdf";
        $pdf->Output($pdfFilePath, 'F');

        // 2. Send Email with PHPMailer
        try {
            $mail = new PHPMailer(true);

            // SMTP
            $mail->isSMTP();
            $mail->Host = 'relay.sautech.co.za';
            $mail->SMTPAuth = true;
            $mail->Username = 'erpsautech';
            $mail->Password = 'Erp$au+ech#782';
            $mail->Port = 2525;
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;


            // Recipients
            $mail->setFrom('support@sautech.net', $sender_name);
            $mail->addAddress($recipient_email, $recipient_name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Quote {$quote['quote_number']} from {$sender_name}";
            $mail->Body = nl2br(htmlspecialchars($message));

            $mail->addAttachment($pdfFilePath, "Quote_{$quote['quote_number']}.pdf");

            $mail->send();

            // Log email
            $status = 'sent';
            $logStmt = $conn->prepare("INSERT INTO quote_email_log 
            (quote_id, sender_name, recipient_name, recipient_email, sent_at, message, status) 
            VALUES (?, ?, ?, ?, NOW(), ?, ?)");
            $logStmt->bind_param("isssss", $quote_id, $sender_name, $recipient_name, $recipient_email, $message, $status);
            $logStmt->execute();

            $_SESSION['success'] = "Quote sent successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Email send failed: " . $e->getMessage();
        }

        // Clean up
        if (file_exists($pdfFilePath)) {
            unlink($pdfFilePath);
        }

        header("Location: quotes.php");
        exit;
    }

    if ($_POST['action'] === 'fetch_company_vat') {
        $company_id = (int) $_POST['company_id'];
        $row = $conn->query("SELECT vat_rate FROM billing_invoice_companies WHERE id=$company_id")->fetch_assoc();
        echo json_encode(['vat_rate' => $row ? $row['vat_rate'] : 0]);
        exit;
    }
    $action = $_POST['action'];
    // Add Quote
    if ($action === 'add') {
        // echo "<pre>";
        // print_r($_POST);
        // echo "</pre>";
        // exit;
        $reference = $_POST['reference'];
        $sales_person = $_POST['sales_person'];
        $quote_date = $_POST['quote_date'];
        $due_date = $_POST['due_date'];
        $service_type_id = $_POST['service_type_id'];
        $service_category_id = $_POST['service_category_id'];
        $quote_number = $_POST['quote_number'];
        $client_id = (int) $_POST['client_id'];
        $quoted_company_id = (int) $_POST['quoted_company_id'];
        $description = $conn->real_escape_string($_POST['note']);
        $qty = (int) $_POST['qty'];
        $unit_price = (float) $_POST['unit_price'];
        $vat = (float) $_POST['vat'];
        $status = $conn->real_escape_string($_POST['status']);

        $price_ex_vat = $qty * $unit_price;
        $total_incl_vat = $price_ex_vat + ($price_ex_vat * $vat / 100);
        $created_by = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO quotes (created_by,quote_number, client_id, quoted_company_id, description, status, reference, sales_person, quote_date, due_date) VALUES (?,?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiissssss", $created_by, $quote_number, $client_id, $quoted_company_id, $description, $status, $reference, $sales_person, $quote_date, $due_date);
        $stmt->execute();
        $quote_id = $stmt->insert_id;

        // Save quote items and calculate total

        $discount = (float) $_POST['discount'];
        $grand_total = 0;
        $total_exclusive = 0;
        $total_vat = 0;

        foreach ($_POST['service_type_id'] as $i => $service_type_id) {
            $service_category_id = $_POST['service_category_id'][$i];
            $description = $conn->real_escape_string($_POST['description'][$i]);
            $qty = $_POST['qty'][$i];
            $unit_price = $_POST['unit_price'][$i];
            $vat = $_POST['vat'][$i];
            $price_ex_vat = $qty * $unit_price;
            $total_incl_vat = $price_ex_vat + ($price_ex_vat * $vat / 100);

            $stmt = $conn->prepare("INSERT INTO quote_items (quote_id, service_type_id, service_category_id, description, qty, unit_price, price_ex_vat, vat, total_incl_vat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisidddd", $quote_id, $service_type_id, $service_category_id, $description, $qty, $unit_price, $price_ex_vat, $vat, $total_incl_vat);
            $stmt->execute();

            $total_exclusive += $price_ex_vat;
            $total_vat += $price_ex_vat * $vat / 100;
            $grand_total += $total_incl_vat;
        }
        $discountPercentage = (float) $_POST['discount']; // Discount as a percentage
        $discountAmount = ($total_exclusive * $discountPercentage) / 100; // Calculate discount amount
        $afterDiscount = $total_exclusive - $discountAmount; // Subtract discount from total exclusive

        // Update quotes table with the totals
        $stmt = $conn->prepare("UPDATE quotes SET total = ?, total_exclusive = ?, total_vat = ?, discount = ? WHERE id = ?");
        $stmt->bind_param("dddii", $grand_total, $afterDiscount, $total_vat, $discountPercentage, $quote_id);
        $stmt->execute();


        if ($stmt->execute()) {
            $_SESSION['success'] = "Quote added successfully.";
        } else {
            $_SESSION['error'] = "Error adding quote: " . $stmt->error;
        }
        $stmt->close();
    }

    // Edit Quote
    elseif ($action === 'edit') {
        $id = (int) $_POST['id'];
        $reference = $_POST['reference'];
        $sales_person = $_POST['sales_person'];
        $quote_date = $_POST['quote_date'];
        $due_date = $_POST['due_date'];
        $client_id = (int) $_POST['client_id'];
        $quoted_company_id = (int) $_POST['quoted_company_id'];
        $description = $conn->real_escape_string($_POST['note']);
        $status = $conn->real_escape_string($_POST['status']);

        // Update the main quote
        $stmt = $conn->prepare("UPDATE quotes SET reference = ?, sales_person = ?, quote_date = ?, due_date = ?, client_id = ?, quoted_company_id = ?, description = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssssisssi", $reference, $sales_person, $quote_date, $due_date, $client_id, $quoted_company_id, $description, $status, $id);
        $stmt->execute();

        // Delete existing items
        $conn->query("DELETE FROM quote_items WHERE quote_id = $id");

        // Insert updated items and calculate grand total

        $discount = (float) $_POST['discount'];
        $grand_total = 0;
        $total_exclusive = 0;
        $total_vat = 0;

        foreach ($_POST['service_type_id'] as $i => $service_type_id) {
            $service_category_id = $_POST['service_category_id'][$i];
            $description = $conn->real_escape_string($_POST['description'][$i]);
            $qty = (int) $_POST['qty'][$i];
            $unit_price = (float) $_POST['unit_price'][$i];
            $vat = (float) $_POST['vat'][$i];
            $price_ex_vat = $qty * $unit_price;
            $total_incl_vat = $price_ex_vat + ($price_ex_vat * $vat / 100);

            $stmt = $conn->prepare("INSERT INTO quote_items (quote_id, service_type_id, service_category_id, description, qty, unit_price, vat, price_ex_vat, total_incl_vat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisidddd", $id, $service_type_id, $service_category_id, $description, $qty, $unit_price, $vat, $price_ex_vat, $total_incl_vat);
            $stmt->execute();

            $total_exclusive += $price_ex_vat;
            $total_vat += $price_ex_vat * $vat / 100;
            $grand_total += $total_incl_vat;
        }
        $discountPercentage = (float) $_POST['discount']; // Discount as a percentage
        $discountAmount = ($total_exclusive * $discountPercentage) / 100; // Calculate discount amount
        $afterDiscount = $grand_total - $discountAmount; // Subtract discount from total exclusive

        // Update quotes table with the totals
        $stmt = $conn->prepare("UPDATE quotes SET total = ?, total_exclusive = ?, total_vat = ?, discount = ? WHERE id = ?");
        $stmt->bind_param("dddii", $grand_total, $afterDiscount, $total_vat, $discountPercentage, $id);
        $stmt->execute();

        $_SESSION['success'] = "Quote updated successfully.";
        header("Location: quotes.php");
        exit;
    }

    // Delete Quote
    elseif ($action === 'delete') {
        $id = (int) $_POST['id'];

        // Delete quote items first
        $conn->query("DELETE FROM quote_items WHERE quote_id = $id");

        // Delete the quote
        $stmt = $conn->prepare("DELETE FROM quotes WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Quote deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting quote: " . $stmt->error;
        }
        $stmt->close();

        header("Location: quotes.php");
        exit;
    }
}


// Redirect back to quotes.php
header("Location: quotes.php");
exit();
?>