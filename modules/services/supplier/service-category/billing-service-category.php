<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = "localhost";
$db_user = "clientzone_user";
$db_pass = "S@utech2024!";
$db_name = "clientzone";

include_once '../../../config.php'; // Ensure this path is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all categories
$categories = $conn->query("
    SELECT c.*, t.service_type_name 
    FROM billing_service_categories c
    LEFT JOIN billing_service_types t ON c.service_type_id = t.id
    WHERE c.is_deleted = 0 
    ORDER BY c.created_at DESC
");
$service_types = $conn->query("SELECT id, service_type_name FROM billing_service_types  ORDER BY service_type_name ASC");
$search_category = $conn->query("SELECT id, category_name FROM billing_service_categories ORDER BY category_name ASC")

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Service Categories</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>

    <div class="px-5 my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <?php session_start(); ?>
                <?php include('../../../components/permissioncheck.php') ?>
                <h3 class="mb-0">Manage Service Categories</h3>
            </div>
            <?php if (hasPermission('Manage Service Categories', 'create')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    Add Service Category
                </button>
            <?php endif; ?>
        </div>
        <!-- start search option  -->
        <div class="card shadow-sm p-4 mb-4 " style=" margin: auto;">
            <form id="filterForm" class="row g-3 align-items-end">

                <div class="col-md-3">
                    <label class="form-label">Search Service Category</label>
                    <!-- <select name="service_category_id" class="form-select" id="categorySearch">
                        <option value="">All service catgegory</option>
                        <?php foreach ($search_category as $category): ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select> -->
                    <input type="text" id="categorySearch" name="categorySearch" placeholder="Search Service Category..." class="form-control">
                </div>
                <div class="col-12 text-end">
                    <button type="submit" id="searchCategory" class="btn btn-primary">Apply Filter</button>
                    <button type="button" onclick="resetFilters()" class="btn btn-secondary">Reset</button>
                </div>
            </form>
        </div>
        <!-- end search option  -->
        <table class="table table-hover table-bordered text-center">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Notes</th>
                    <th>VM Category?</th>
                    <th>Service Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="categoryTableBody">

                <?php
                $i = 1;
                while ($cat = $categories->fetch_assoc()):
                ?>
                    <tr>
                        <td><?= $i ?></td>
                        <td><?= htmlspecialchars($cat['category_name']) ?></td>
                        <td><?= htmlspecialchars($cat['note']) ?></td>
                        <td>
                            <?= $cat['has_vm_fields'] == 1 ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?>
                        </td>
                        <td><?= $cat['service_type_name'] != null ? htmlspecialchars($cat['service_type_name']) : 'N/A' ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group" aria-label="Actions">
                                <?php if (hasPermission('Manage Service Categories', 'update')): ?>
                                    <a href="javascript:void(0);" onclick="openEditModal(<?= $cat['id'] ?>)" class="btn btn-sm"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (hasPermission('Manage Service Categories', 'delete')): ?>
                                    <a href="javascript:void(0);" onclick="deleteCategory(<?= $cat['id'] ?>)"
                                        class="btn btn-sm text-danger" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php $i++;
                endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="addCategoryForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Service Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body row g-3">
                    <!-- Add Service Type Dropdown in Add Modal -->
                    <div class="col-12">
                        <label class="form-label">Service Type</label>
                        <select name="service_type_id" class="form-control" required>
                            <option value="">Select Service Type</option>
                            <?php while ($type = $service_types->fetch_assoc()): ?>
                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['service_type_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="category_name" class="form-control" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control"></textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_vm_category" id="is_vm_category">
                            <label class="form-check-label" for="is_vm_category">
                                This is a VM Category (CPU, Memory, HDD, OS, IP)
                            </label>
                        </div>
                    </div>


                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="editCategoryForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Service Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body row g-3">
                    <input type="hidden" name="id" id="edit-id">
                    <!-- Add Service Type Dropdown in Edit Modal -->
                    <div class="col-12">
                        <label class="form-label">Service Type</label>
                        <select name="service_type_id" id="edit-service-type-id" class="form-control" required>
                            <option value="">Select Service Type</option>
                            <?php
                            $service_types->data_seek(0); // Reset pointer for reuse
                            while ($type = $service_types->fetch_assoc()): ?>
                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['service_type_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="category_name" id="edit-category-name" class="form-control" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="edit-notes" class="form-control"></textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_vm_category"
                                id="edit-is-vm-category">
                            <label class="form-check-label" for="edit-is-vm-category">
                                This is a VM Category
                            </label>
                        </div>
                    </div>


                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Handle Add Category
        document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add');

            axios.post('service-category-backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Service Category Added Successfully ✅');
                        location.reload();
                    } else {
                        alert('Failed to add category ❌');
                    }
                })
                .catch(error => {
                    alert('Server Error ❌');
                    console.error(error);
                });
        });

        // Handle Edit Category
        document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit');

            axios.post('service-category-backend.php', formData)
                .then(response => {
                    if (response.data.trim() === 'success') {
                        alert('Service Category Updated Successfully ✅');
                        location.reload();
                    } else {
                        alert('Failed to update category ❌');
                    }
                })
                .catch(error => {
                    alert('Server Error ❌');
                    console.error(error);
                });
        });

        // Open Edit Modal
        function openEditModal(id) {
            const formData = new FormData();
            formData.append('action', 'fetch');
            formData.append('id', id);

            axios.post('service-category-backend.php', formData)
                .then(response => {
                    const category = response.data;

                    document.getElementById('edit-id').value = category.id;
                    document.getElementById('edit-category-name').value = category.category_name;
                    document.getElementById('edit-notes').value = category.note;
                    document.getElementById('edit-is-vm-category').checked = category.has_vm_fields == 1;
                    document.getElementById('edit-service-type-id').value = category.service_type_id;

                    var editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
                    editModal.show();
                })
                .catch(error => {
                    alert('Failed to fetch category ❌');
                    console.error(error);
                });
        }

        // Delete Category
        function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this category?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                axios.post('service-category-backend.php', formData)
                    .then(response => {
                        if (response.data.trim() === 'success') {
                            alert('Service Category Deleted Successfully ✅');
                            location.reload();
                        } else {
                            alert('Failed to delete category ❌');
                        }
                    })
                    .catch(error => {
                        alert('Server Error ❌');
                        console.error(error);
                    });
            }
        }

        // ✅ Handle Apply Filter button click using its ID
        document.getElementById('searchCategory').addEventListener('click', function(e) {
            e.preventDefault();
            const form = document.getElementById('filterForm');
            const formData = new FormData(form);
            // console.log(formData)
            axios.post('billing-service-category-filter.php', formData)
                .then(response => {
                    const categories = response.data;
                    const tbody = document.getElementById('categoryTableBody');
                    tbody.innerHTML = '';

                    let i = 1;

                    categories.forEach(cat => {
                        const hasVM = cat.has_vm_fields == 1 ?
                            '<span class="badge bg-success">Yes</span>' :
                            '<span class="badge bg-secondary">No</span>';

                        const serviceType = cat.service_type_name ?
                            cat.service_type_name :
                            'N/A';

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                    <td>${i++}</td>
                    <td>${cat.category_name}</td>
                    <td>${cat.note}</td>
                    <td>${hasVM}</td>
                    <td>${serviceType}</td>
                    <td>
                        <div class="btn-group" role="group" aria-label="Actions">
                            ${hasPermissionUpdate ? `
                                <a href="javascript:void(0);" onclick="openEditModal(${cat.id})" class="btn btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>` : ''}
                            ${hasPermissionDelete ? `
                                <a href="javascript:void(0);" onclick="deleteCategory(${cat.id})" class="btn btn-sm text-danger" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </a>` : ''}
                        </div>
                    </td>
                `;
                        tbody.appendChild(tr);
                    });
                })
                .catch(error => {
                    alert('Failed to filter service categories ❌');
                    console.error(error);
                });
        });




        const hasPermissionUpdate = <?= hasPermission('Manage Service Categories', 'update') ? 'true' : 'false' ?>;
        const hasPermissionDelete = <?= hasPermission('Manage Service Categories', 'delete') ? 'true' : 'false' ?>;

        // Reset Filters
        function resetFilters() {
            document.getElementById('filterForm').reset();
            document.getElementById('filterForm').dispatchEvent(new Event('submit'));
        }
    </script>

</body>

</html>