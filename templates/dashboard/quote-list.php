<table class="quotes-table">
    <thead>
        <tr>
            <th>Quote #</th>
            <th>Client</th>
            <th>Destination</th>
            <th>Status</th>
            <th>Version</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="quotes-list-body">
        <tr>
            <td colspan="7" class="loading">Loading quotes...</td>
        </tr>
    </tbody>
</table>

<style>
.quotes-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.quotes-table th,
.quotes-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}
.quotes-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
}
.quotes-table tr:hover {
    background: #f8f9fa;
}
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.status-draft { background: #e2e3e5; color: #383d41; }
.status-sent { background: #d1ecf1; color: #0c5460; }
.status-confirmed { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.action-btn {
    display: inline-block;
    padding: 4px 8px;
    margin: 0 2px;
    font-size: 12px;
    border-radius: 3px;
    text-decoration: none;
}
.action-view { background: #3498db; color: white; }
.action-edit { background: #f39c12; color: white; }
.action-pdf { background: #e74c3c; color: white; }
.action-share { background: #2ecc71; color: white; }
</style>

<script>
jQuery(document).ready(function($) {
    function loadQuotes() {
        $.ajax({
            url: oksia_dashboard.ajax_url,
            type: 'POST',
            data: {
                action: 'oksia_get_quotes',
                nonce: oksia_dashboard.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    var html = '';
                    $.each(response.data, function(i, quote) {
                        var clientData = quote.client_data;
                        var statusClass = 'status-' + quote.status;
                        var statusLabel = quote.status.charAt(0).toUpperCase() + quote.status.slice(1);

                        html += '<tr>';
                        html += '<td><code>' + quote.quote_number + '</code></td>';
                        html += '<td>' + (clientData.client_name || 'N/A') + '</td>';
                        html += '<td>' + (clientData.destination || 'N/A') + '</td>';
                        html += '<td><span class="status-badge ' + statusClass + '">' + statusLabel + '</span></td>';
                        html += '<td>v' + quote.version + '</td>';
                        html += '<td>' + new Date(quote.created_at).toLocaleDateString() + '</td>';
                        html += '<td>';
                        html += '<a href="/quote/' + quote.share_token + '" class="action-btn action-view" target="_blank">View</a>';
                        html += '<a href="#" class="action-btn action-edit" data-id="' + quote.id + '">Edit</a>';
                        html += '<a href="?oksia_download_pdf=1&quote_id=' + quote.id + '&nonce=' + oksia_dashboard.nonce + '" class="action-btn action-pdf">PDF</a>';
                        html += '<a href="#" class="action-btn action-share" data-link="' + quote.share_url + '">Share</a>';
                        html += '</td>';
                        html += '</tr>';
                    });
                    $('#quotes-list-body').html(html);
                } else {
                    $('#quotes-list-body').html('<tr><td colspan="7">No quotes found. Create your first quote!</td></tr>');
                }
            },
            error: function() {
                $('#quotes-list-body').html('<tr><td colspan="7">Error loading quotes. Please refresh.</td></tr>');
            }
        });
    }

    loadQuotes();

    $(document).on('click', '.action-share', function(e) {
        e.preventDefault();
        var link = $(this).data('link');
        prompt('Copy this link to share with client:', link);
    });
});
</script>
