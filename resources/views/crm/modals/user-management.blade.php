<!-- User Management Modal -->
<div class="modal fade" id="userManagementModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <button class="btn btn-primary btn-sm" onclick="showCreateUserForm()">
                        <i class="bi bi-plus-circle"></i> Create User
                    </button>
                </div>
                <div id="create-user-form-container" class="d-none mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Create New User</h6>
                        </div>
                        <div class="card-body">
                            <form id="create-user-form">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Username <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="user-username" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="user-email" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password (Default: 123456)</label>
                                        <input type="password" class="form-control" id="user-password">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Role <span class="text-danger">*</span></label>
                                        <select class="form-select" id="user-role" required onchange="handleRoleChange()">
                                            <option value="">Select Role...</option>
                                            <!-- Roles will be loaded dynamically via JavaScript -->
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3" id="user-manager-container" style="display: none;">
                                        <label class="form-label">Manager <span class="text-danger">*</span></label>
                                        <select class="form-select" id="user-manager">
                                            <option value="">Select Manager...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3" id="user-independent-container" style="display: none;">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="user-independent">
                                            <label class="form-check-label" for="user-independent">
                                                Independent
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="bi bi-save"></i> Save
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="cancelCreateUser()">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div id="users-list-container">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="edit-user-form">
                    <input type="hidden" id="edit-user-id">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit-user-username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit-user-email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password (Leave empty to keep current)</label>
                        <input type="password" class="form-control" id="edit-user-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" id="edit-user-role" required>
                            <option value="">Select Role...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manager</label>
                        <select class="form-select" id="edit-user-manager">
                            <option value="">Select Manager...</option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="edit-user-active">
                        <label class="form-check-label" for="edit-user-active">
                            Active
                        </label>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-save"></i> Update
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

