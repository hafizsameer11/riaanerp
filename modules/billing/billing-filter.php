<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../config.php'; // Ensure this path is correct
// Build dynamic WHERE conditions
$where = "b.is_deleted = 0";
$params = [];
$searchDescription = $_POST['searchDescription'] ?? '';

if (!empty($_POST['client_id'])) {
    $where .= " AND b.client_id = " . (int)$_POST['client_id'];
}
if (!empty($_POST['supplier_id'])) {
    $where .= " AND b.supplier_id = " . (int)$_POST['supplier_id'];
}
if (!empty($_POST['service_type_id'])) {
    $where .= " AND b.service_type_id = " . (int)$_POST['service_type_id'];
}
if (!empty($_POST['invoicing_company_id'])) {
    $where .= " AND b.invoicing_company_id = " . (int)$_POST['invoicing_company_id'];
}
if (!empty($_POST['frequency'])) {
    $where .= " AND b.frequency = '" . $conn->real_escape_string($_POST['frequency']) . "'";
}
if (!empty($searchDescription)) {
    $safeTerm = $conn->real_escape_string($searchDescription);
    $where .= " AND (b.description LIKE '%$safeTerm%' OR c.client_name LIKE '%$safeTerm%')";
}
// Handle date range filter - same logic as billing report
if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
    $filterStart = $conn->real_escape_string($_POST['start_date']);
    $filterEnd = $conn->real_escape_string($_POST['end_date']);
    // Agreement is active during the period if: start_date <= end AND end_date >= start (or end_date is NULL)
    $where .= " AND (b.start_date <= '$filterEnd' AND (b.end_date IS NULL OR b.end_date >= '$filterStart'))";
}
// Fetch filtered billing records
$sql = "
SELECT b.*, 
       c.client_name, 
       s.supplier_name AS supplier_name, 
       st.service_type_name,
       sc.category_name,
       ic.company_name
FROM billing_items b
LEFT JOIN clients c ON b.client_id = c.id
LEFT JOIN billing_suppliers s ON b.supplier_id = s.id
LEFT JOIN billing_service_types st ON b.service_type_id = st.id
LEFT JOIN billing_service_categories sc ON b.service_category_id = sc.id
LEFT JOIN billing_invoice_companies ic ON b.invoicing_company_id = ic.id
WHERE $where
ORDER BY c.client_name
";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        $subtotal = $row['qty'] * $row['unit_price'];
        $vatAmount = ($row['vat_rate'] / 100) * $subtotal;
        $total = $subtotal + $vatAmount;

        echo '<tr>
            <td class="text-center">' . $i++ . '</td>
            <td>' . htmlspecialchars($row['client_name']) . '</td>
            <td>' . htmlspecialchars($row['description']) . '</td>
            <td class="text-center">' . $row['qty'] . '</td>
            <td class="text-end">' . number_format($row['unit_price'], 2) . '</td>
            <td class="text-end">' . number_format($vatAmount, 2) . '</td>
            <td class="text-end">' . number_format($subtotal, 2) . '</td>
            <td class="text-end"><strong>' . number_format($total, 2) . '</strong></td>
            <td class="text-center">' . ucfirst($row['frequency']) . '</td>
            <td class="text-center">
                <div class="btn-group" role="group">
                 <a href="javascript:void(0)" 
          onclick="openViewModal(' . (int)$row['id'] . ')" 
          class="btn btn-sm" 
          title="View">
          <i class="fas fa-eye"></i>
        </a>
                 <a href="javascript:void(0)" onclick=\'openEditModal('
            . json_encode($row["id"] ?? null) . ','
            . json_encode($row["client_id"] ?? null) . ','
            . json_encode($row["supplier_id"] ?? null) . ','
            . json_encode($row["service_type_id"] ?? null) . ','
            . json_encode($row["service_category_id"] ?? null) . ','
            . json_encode($row["invoicing_company_id"] ?? null) . ','
            . json_encode($row["description"] ?? "") . ','
            . json_encode($row["qty"] ?? 0) . ','
            . json_encode($row["unit_price"] ?? 0) . ','
            . json_encode($row["vat_rate"] ?? 0) . ','
            . json_encode($row["frequency"] ?? "") . ','
            . json_encode($row["start_date"] ?? "") . ','
            . json_encode($row["end_date"] ?? "") . ','
            . json_encode($row["vat_applied"] ?? 0)
            . ')\' class="btn btn-sm" title="Edit"><i class="fas fa-edit"></i></a>

                    <a href="javascript:void(0)" onclick="openDeleteModal(' . $row['id'] . ')" class="btn btn-sm text-danger" title="Delete"><i class="fas fa-trash-alt"></i></a>
                </div>
            </td>
        </tr>';
    }
} else {
    echo '<tr><td colspan="15" class="text-center text-muted">No billing items found matching filters.</td></tr>';
}
?>
 <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        function openViewModal(id) {
            const modalBody = document.getElementById('viewBillingModalBody');
            modalBody.innerHTML = '<div class="text-center py-4 text-muted">Loading billing details...</div>';

            // Fetch data from backend using AJAX
            axios.get('view_billing.php?id=' + id)
                .then(response => {
                    modalBody.innerHTML = response.data; // response is rendered HTML
                })
                .catch(error => {
                    modalBody.innerHTML = '<div class="alert alert-danger">Failed to load billing details.</div>';
                    console.error(error);
                });

            // Show modal
            const viewModal = new bootstrap.Modal(document.getElementById('viewBillingModal'));
            viewModal.show();
        }
    </script>