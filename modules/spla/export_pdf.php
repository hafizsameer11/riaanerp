<?php
require_once('../../TCPDF/tcpdf.php'); // Include your database connection
$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../config.php'; // Ensure this path is correct


$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('SPLA Licensing');
$pdf->SetTitle('SPLA Licensing Records');
$pdf->SetHeaderData('', 0, 'SPLA Licensing Records', 'Generated on ' . date('Y-m-d'));
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 10);
$pdf->AddPage();

// Fetch records
$result = $conn->query("
    SELECT id, client, ms_products, quantity, notes
    FROM spla_licenses
    WHERE is_deleted = 0
    ORDER BY client ASC
");

// Add table header
$html = '<h3>SPLA Licensing Records</h3>';
$html .= '<table border="1" cellpadding="5">
    <thead>
        <tr>
            <th>#</th>
            <th>Client</th>
            <th>Microsoft Product</th>
            <th>Quantity</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>';

// Add table rows
$i = 1;
while ($row = $result->fetch_assoc()) {
    $html .= '<tr>
        <td>' . $i++ . '</td>
        <td>' . htmlspecialchars($row['client']) . '</td>
        <td>' . htmlspecialchars($row['ms_products']) . '</td>
        <td>' . htmlspecialchars($row['quantity']) . '</td>
        <td>' . htmlspecialchars($row['notes']) . '</td>
    </tr>';
}

$html .= '</tbody></table>';

// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output('spla_licensing_records.pdf', 'D');
?>