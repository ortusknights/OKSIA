<div id="edit-agency-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; width:500px; max-width:90%; border-radius:8px; padding:20px;">
        <h2>Edit Agency</h2>
        <form id="edit-agency-form">
            <input type="hidden" id="edit_agency_id">
            <div class="form-field">
                <label>Agency Name</label>
                <input type="text" id="edit_agency_name" class="regular-text">
            </div>
            <div class="form-field">
                <label>Status</label>
                <select id="edit_agency_status">
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="form-field">
                <label>Agency Code</label>
                <input type="text" id="edit_agency_code" readonly disabled>
            </div>
            <div class="form-buttons" style="margin-top:20px;">
                <button type="submit" class="button button-primary">Save Changes</button>
                <button type="button" class="button close-modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.form-field {
    margin-bottom: 15px;
}
.form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}
.form-field input, .form-field select {
    width: 100%;
    padding: 8px;
}
</style>
