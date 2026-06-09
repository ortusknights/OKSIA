<div id="edit-quote-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; width:600px; max-width:90%; border-radius:8px; padding:20px; max-height:80%; overflow-y:auto;">
        <h2>Edit Quote</h2>
        <form id="edit-quote-form">
            <input type="hidden" id="edit_quote_id">
            <div class="form-field">
                <label>Quote Number</label>
                <input type="text" id="edit_quote_number" readonly disabled>
            </div>
            <div class="form-field">
                <label>Status</label>
                <select id="edit_quote_status">
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="form-field">
                <label>Client Name</label>
                <input type="text" id="edit_client_name">
            </div>
            <div class="form-field">
                <label>Client Email</label>
                <input type="email" id="edit_client_email">
            </div>
            <div class="form-field">
                <label>Client Phone</label>
                <input type="text" id="edit_client_phone">
            </div>
            <div class="form-field">
                <label>Destination</label>
                <input type="text" id="edit_destination">
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
