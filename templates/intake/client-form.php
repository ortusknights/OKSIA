<div class="oksia-intake-container">
    <h2>Request a Travel Quote</h2>
    <form id="oksia-client-intake-form" method="post">
        <?php wp_nonce_field('oksia_frontend_nonce', 'intake_nonce'); ?>
        <input type="hidden" name="form_type" value="client_intake">

        <div class="form-section">
            <h3>Personal Information</h3>

            <div class="form-row">
                <div class="form-group">
                    <label>Salutation <span class="required">*</span></label>
                    <select name="salutation" required>
                        <option value="">Select</option>
                        <option value="Mr">Mr</option>
                        <option value="Ms">Ms</option>
                        <option value="Mrs">Mrs</option>
                        <option value="Dr">Dr</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="client_name" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" required>
                </div>

                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="phone" required>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>Trip Details</h3>

            <div class="form-row">
                <div class="form-group">
                    <label>Trip Type <span class="required">*</span></label>
                    <select name="trip_type" required>
                        <option value="">Select</option>
                        <option value="Domestic">Domestic</option>
                        <option value="International">International</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Destination <span class="required">*</span></label>
                    <input type="text" name="destination" placeholder="e.g., Paris, Bali, Rajasthan" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Start Date <span class="required">*</span></label>
                    <input type="date" name="start_date" required>
                </div>

                <div class="form-group">
                    <label>End Date <span class="required">*</span></label>
                    <input type="date" name="end_date" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Adults (12+ yrs) <span class="required">*</span></label>
                    <input type="number" name="adults" min="1" max="20" value="2" required>
                </div>

                <div class="form-group">
                    <label>Children (2-11 yrs)</label>
                    <input type="number" name="children" min="0" max="10" value="0">
                </div>

                <div class="form-group">
                    <label>Infants (0-2 yrs)</label>
                    <input type="number" name="infants" min="0" max="5" value="0">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Budget Range (Optional)</label>
                    <input type="text" name="budget" placeholder="e.g., $2000-3000, Moderate, Luxury">
                </div>
            </div>

            <div class="form-group">
                <label>Special Requests</label>
                <textarea name="special_requests" rows="4" placeholder="Dietary restrictions, accessibility needs, preferred hotels, etc."></textarea>
            </div>
        </div>

        <div class="form-submit">
            <button type="submit" class="submit-btn">Submit Request</button>
        </div>

        <div id="form-message" class="form-message" style="display:none;"></div>
    </form>
</div>

<style>
.oksia-intake-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 30px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.1);
}
.oksia-intake-container h2 {
    margin-top: 0;
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
}
.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e0e0e0;
}
.form-section h3 {
    color: #34495e;
    margin-bottom: 20px;
}
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
}
.form-group .required {
    color: #e74c3c;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3498db;
}
.submit-btn {
    background: #3498db;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.3s;
}
.submit-btn:hover {
    background: #2980b9;
}
.form-message {
    margin-top: 20px;
    padding: 10px;
    border-radius: 6px;
}
.form-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.form-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>
