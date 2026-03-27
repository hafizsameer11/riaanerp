<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../../config.php'; // Ensure this path is correct
// Fetch Billing Records
$billingRecords = [];
$result = $conn->query("
    SELECT b.*, 
           c.client_name, 
           s.supplier_name AS supplier_name, 
            st.service_type_name,
           sc.category_name
    FROM billing_items b
    LEFT JOIN clients c ON b.client_id = c.id
    LEFT JOIN billing_suppliers s ON b.supplier_id = s.id
   LEFT JOIN billing_service_types st ON b.service_type_id = st.id
    LEFT JOIN billing_service_categories sc ON b.service_category_id = sc.id
        WHERE b.is_deleted = 0
    ORDER BY b.created_at DESC
");


if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $billingRecords[] = $row;
    }
}
// Fetch Billing Items
$itemsResult = $conn->query("
    SELECT * FROM billing_items 
    WHERE is_deleted = 0
    ORDER BY created_at DESC
");

// Fetch Filters
$clients = $conn->query("SELECT id, client_name FROM clients ORDER BY client_name ASC");
$suppliers = $conn->query("SELECT id, supplier_name FROM billing_suppliers ORDER BY supplier_name ASC");
$serviceTypes = $conn->query("SELECT id, service_type_name FROM billing_service_types ORDER BY service_type_name ASC");
$serviceCategories = $conn->query("SELECT id, category_name, has_vm_fields FROM billing_service_categories ORDER BY category_name ASC");

function calculateStatus($endDate)
{
    if (!$endDate)
        return 'Active';
    $now = strtotime(date('Y-m-d'));
    $end = strtotime($endDate);
    $diff = ($end - $now) / (60 * 60 * 24);

    if ($diff < 0)
        return 'Expired';
    if ($diff <= 90)
        return 'Expiring Soon';
    return 'Active';
}

function statusBadge($status)
{
    return match ($status) {
        'Active' => 'success',
        'Expiring Soon' => 'warning',
        'Expired' => 'danger',
        default => 'secondary'
    };
}
session_start();
include('../components/permissioncheck.php');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Billing Items List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <div class=" my-5 " style="width:93%; margin:auto; ">

        <div class="mt-5 mb-5">
            <!-- Left-aligned Title -->
            <h3 class="mb-2 d-flex align-items-center">
                <i class="bi bi-people-fill me-2 text-secondary" style="font-size: 1.5rem;"></i>
                <span class="fw-semibold text-dark">Billing Module</span>
            </h3>

            <div class="row align-items-center">
                <?php if(hasPermission('billing','billing')):  ?>
                <div class="col-md-3 px-2"><a href="billing.php" class="btn btn-primary p-3 h5 py-2 w-100">Billing</a></div>
                <?php endif; ?>
                <?php if (hasPermission('billing', 'wip')): ?>
                    <div class="col-md-3 px-2"><a href="wip.php" class="btn btn-warning p-3 h5 py-2 w-100">WIP</a></div>
                <?php endif; ?>
                <?php if (hasPermission('billing', 'quotes')): ?>
                    <div class="col-md-3 px-2"><a href="quotes.php" class="btn btn-secondary p-3 h5 py-2 w-100">Quotes</a></div>
                <?php endif; ?>
                <?php if (hasPermission('billing', 'expenses')): ?>
                    <div class="col-md-3 px-2"><a href="expenses.php" class="btn btn-danger p-3 h5 py-2 w-100">Expenses</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- clients that expire 90 days -->



    <!-- Bootstrap + JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        function openEditModal(
            id, client_id, supplier_id, service_type_id, service_category_id,
            description, qty, unit_price, vat_rate, invoice_frequency,
            start_date, end_date, charge_vat,
            cpu = '', memory = '', hdd_sata = '', hdd_ssd = '', os = '', ip_address = ''
        ) {
            document.getElementById('edit-billing-id').value = id;
            document.getElementById('edit-client-id').value = client_id;
            document.getElementById('edit-supplier-id').value = supplier_id;
            document.getElementById('edit-service-type-id').value = service_type_id;
            document.getElementById('edit-service-category-id').value = service_category_id;
            document.getElementById('edit-description').value = description;
            document.getElementById('edit-quantity').value = qty;
            document.getElementById('edit-unit-price').value = unit_price;
            document.getElementById('edit-vat-rate').value = vat_rate;
            document.getElementById('edit-invoice-frequency').value = invoice_frequency;
            document.getElementById('edit-start-date').value = start_date;
            document.getElementById('edit-end-date').value = end_date;
            document.getElementById('edit-charge-vat').checked = charge_vat == 1 ? true : false;

            // Show VM fields if applicable
            const selectedOption = document.querySelector(`#edit-service-category-id option[value='${service_category_id}']`);
            const isVMCategory = selectedOption ? selectedOption.getAttribute('data-vm') : '0';

            if (isVMCategory == '1') {
                document.getElementById('vmFieldsEdit').style.display = 'block';
                document.getElementById('edit-cpu').value = cpu;
                document.getElementById('edit-memory').value = memory;
                document.getElementById('edit-hdd-sata').value = hdd_sata;
                document.getElementById('edit-hdd-ssd').value = hdd_ssd;
                document.getElementById('edit-os').value = os;
                document.getElementById('edit-ip-address').value = ip_address;
            } else {
                document.getElementById('vmFieldsEdit').style.display = 'none';
            }

            var editModal = new bootstrap.Modal(document.getElementById('editBillingModal'));
            editModal.show();
        }
    </script>
    <script>
        function reloadPage() {
            setTimeout(() => location.reload(), 800);
        }
        // frequency 
        function handleFrequencyFields(frequency) {
            const startLabel = document.getElementById('start-date-label');
            const endDateWrapper = document.getElementById('end-date-wrapper');

            if (frequency === 'once_off') {
                startLabel.innerText = 'Invoice Date (Month & Day)';
                endDateWrapper.style.display = 'none';
            } else if (frequency === 'monthly' || frequency === 'finance') {
                startLabel.innerText = 'Start Date';
                endDateWrapper.style.display = 'block';
            } else if (frequency === 'annually') {
                startLabel.innerText = 'Start Date';
                endDateWrapper.style.display = 'none';
            }
        }

        // Call once if already selected (e.g., during edit)
        document.addEventListener('DOMContentLoaded', function () {
            const freq = document.getElementById('invoice-frequency')?.value;
            if (freq) handleFrequencyFields(freq);
        });

        // ➕ Handle Add Billing
        document.getElementById('addBillingForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add');

            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Billing Item Added Successfully ✅');
                        location.reload(); // or reloadPage();
                    } else {
                        alert('Error adding billing item ❌\nServer Response: ' + response.data);
                    }
                })
                .catch(error => {
                    alert('Server Error ❌');
                    console.error(error);
                });
        });
        // ✏️ Handle Edit Billing Form Submit
        document.getElementById('editBillingForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit'); // Set action for backend

            axios.post('backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Billing Item Updated Successfully ✅');
                        location.reload(); // reload page after successful update
                    } else {
                        alert('Error updating billing item ❌\nServer: ' + response.data);
                    }
                })
                .catch(error => {
                    alert('Server Error ❌');
                    console.error(error);
                });
        });

        // Dynamically show/hide Edit VM fields
        function handleEditVMFields(select) {
            const selectedOption = select.options[select.selectedIndex];
            const isVM = selectedOption.getAttribute('data-vm') == "1";

            if (isVM) {
                document.getElementById('vmFieldsEdit').style.display = 'block';
            } else {
                document.getElementById('vmFieldsEdit').style.display = 'none';
                // Optionally clear VM fields
                document.getElementById('edit-cpu').value = '';
                document.getElementById('edit-memory').value = '';
                document.getElementById('edit-hdd-sata').value = '';
                document.getElementById('edit-hdd-ssd').value = '';
                document.getElementById('edit-os').value = '';
                document.getElementById('edit-ip-address').value = '';
            }
        }

        function openDeleteModal(id) {
            if (confirm('Are you sure you want to delete this billing item?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                axios.post('backend.php', formData)
                    .then(response => {
                        if (response.data.trim() === 'success') {
                            alert('Billing Item Deleted Successfully ✅');
                            location.reload();
                        } else {
                            alert('Failed to delete billing item ❌\nServer: ' + response.data);
                        }
                    })
                    .catch(error => {
                        alert('Server Error ❌');
                        console.error(error);
                    });
            }
        }
        // Handle Filter Form Submit
        document.getElementById('filterForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            axios.post('billing-filter.php', formData)
                .then(response => {
                    const tbody = document.querySelector('table tbody');
                    tbody.innerHTML = response.data;
                })
                .catch(error => {
                    alert('Failed to filter results ❌');
                    console.error(error);
                });
        });

        // Reset Filters
        function resetFilters() {
            document.getElementById('filterForm').reset();
            document.getElementById('filterForm').dispatchEvent(new Event('submit'));
        }
        // Show/Hide VM Fields on Service Category Change
        function handleVMFields(select) {
            var selectedOption = select.options[select.selectedIndex];
            var isVMCategory = selectedOption.getAttribute('data-vm') === '1';

            document.getElementById('vmFieldsAdd').style.display = isVMCategory ? 'flex' : 'none';

            const serviceCategoryId = select.value;

            if (serviceCategoryId) {
                const formData = new FormData();
                formData.append('action', 'fetch_unit_price'); // 👈 Important: pass action
                formData.append('service_category_id', serviceCategoryId);

                axios.post('backend.php', formData) // 👈 Post to backend.php now
                    .then(response => {
                        const data = response.data;
                        if (data.unit_price !== undefined) {
                            document.querySelector('input[name="unit_price"]').value = data.unit_price;
                        } else {
                            document.querySelector('input[name="unit_price"]').value = '';
                        }
                    })
                    .catch(error => {
                        console.error('Failed to fetch unit price:', error);
                    });
            } else {
                document.querySelector('input[name="unit_price"]').value = '';
            }
        }

        // Show/Hide VM Fields on Service Category Change in Edit Modal
        function handleEditVMFields(select) {
            var selectedOption = select.options[select.selectedIndex];
            var isVMCategory = selectedOption.getAttribute('data-vm') === '1';

            // ➡️ Show/Hide VM Fields
            document.getElementById('vmFieldsEdit').style.display = isVMCategory ? 'flex' : 'none';

            // ➡️ Fetch and Autofill Unit Price in Edit Modal
            const serviceCategoryId = select.value;

            if (serviceCategoryId) {
                const formData = new FormData();
                formData.append('action', 'fetch_unit_price'); // 👈 Important: action for backend
                formData.append('service_category_id', serviceCategoryId);

                axios.post('backend.php', formData) // 👈 Post to your backend.php
                    .then(response => {
                        const data = response.data;
                        if (data.unit_price !== undefined) {
                            document.getElementById('edit-unit-price').value = data.unit_price;
                        } else {
                            document.getElementById('edit-unit-price').value = '';
                        }
                    })
                    .catch(error => {
                        console.error('❌ Failed to fetch unit price in edit modal:', error);
                    });
            } else {
                document.getElementById('edit-unit-price').value = '';
            }
        }
        // Live sub total
        function updateBillingSummary() {
            const qty = parseFloat(document.querySelector('input[name="quantity"]').value) || 0;
            const unitPrice = parseFloat(document.querySelector('input[name="unit_price"]').value) || 0;
            const vatRate = parseFloat(document.querySelector('input[name="vat_rate"]').value) || 0;

            const subtotal = qty * unitPrice;
            const vatAmount = (vatRate / 100) * subtotal;
            const total = subtotal + vatAmount;

            document.getElementById('subtotalDisplay').innerText = subtotal.toFixed(2);
            document.getElementById('vatDisplay').innerText = vatAmount.toFixed(2);
            document.getElementById('totalDisplay').innerText = total.toFixed(2);
        }

        // ➡️ Attach events to live update
        document.querySelector('input[name="quantity"]').addEventListener('input', updateBillingSummary);
        document.querySelector('input[name="unit_price"]').addEventListener('input', updateBillingSummary);
        document.querySelector('input[name="vat_rate"]').addEventListener('input', updateBillingSummary);

        // edit sub total
        function updateEditBillingSummary() {
            const qty = parseFloat(document.getElementById('edit-quantity').value) || 0;
            const unitPrice = parseFloat(document.getElementById('edit-unit-price').value) || 0;
            const vatRate = parseFloat(document.getElementById('edit-vat-rate').value) || 0;

            const subtotal = qty * unitPrice;
            const vatAmount = (vatRate / 100) * subtotal;
            const total = subtotal + vatAmount;

            document.getElementById('edit-subtotalDisplay').innerText = subtotal.toFixed(2);
            document.getElementById('edit-vatDisplay').innerText = vatAmount.toFixed(2);
            document.getElementById('edit-totalDisplay').innerText = total.toFixed(2);
        }

        // ➡️ Attach event listeners for Edit Modal fields
        document.getElementById('edit-quantity').addEventListener('input', updateEditBillingSummary);
        document.getElementById('edit-unit-price').addEventListener('input', updateEditBillingSummary);
        document.getElementById('edit-vat-rate').addEventListener('input', updateEditBillingSummary);
    </script>


</body>

</html>