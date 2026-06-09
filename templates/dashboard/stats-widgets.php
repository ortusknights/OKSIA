<div class="stats-grid">
    <div class="stat-widget">
        <div class="stat-icon">📋</div>
        <div class="stat-info">
            <span class="stat-number" id="total-quotes">0</span>
            <span class="stat-label">Total Quotes</span>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon">✏️</div>
        <div class="stat-info">
            <span class="stat-number" id="draft-quotes">0</span>
            <span class="stat-label">Draft</span>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon">📤</div>
        <div class="stat-info">
            <span class="stat-number" id="sent-quotes">0</span>
            <span class="stat-label">Sent</span>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon">✅</div>
        <div class="stat-info">
            <span class="stat-number" id="confirmed-quotes">0</span>
            <span class="stat-label">Confirmed</span>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon">❌</div>
        <div class="stat-info">
            <span class="stat-number" id="cancelled-quotes">0</span>
            <span class="stat-label">Cancelled</span>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
            <span class="stat-number" id="total-value">₹0</span>
            <span class="stat-label">Total Value</span>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-widget {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.stat-icon {
    font-size: 32px;
}
.stat-info {
    display: flex;
    flex-direction: column;
}
.stat-number {
    font-size: 28px;
    font-weight: bold;
}
.stat-label {
    font-size: 12px;
    opacity: 0.8;
}
</style>

<script>
function updateStats(stats) {
    jQuery('#total-quotes').text(stats.total || 0);
    jQuery('#draft-quotes').text(stats.draft || 0);
    jQuery('#sent-quotes').text(stats.sent || 0);
    jQuery('#confirmed-quotes').text(stats.confirmed || 0);
    jQuery('#cancelled-quotes').text(stats.cancelled || 0);
}
</script>
