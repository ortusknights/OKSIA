<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>{{DESTINATION_NAME}} - Quote</title>

<style>

@page{
    size:A4;
    margin:20mm 15mm;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    background:#f5f7fa;
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    color:#2d3748;
    line-height:1.5;
}

/* PAGE STRUCTURE */

.quote-page{
    max-width:900px;
    margin:25px auto;
    background:#fff;
    border:1px solid #e1e4e6;
    border-radius:12px;
    overflow:hidden;
    page-break-after:always;
}

.quote-page:last-child{
    page-break-after:auto;
}

@media print{

    body{
        background:#fff;
    }

    .quote-page{
        margin:0;
        border:none;
        border-radius:0;
        box-shadow:none;
        page-break-after:always;
    }

}

/* HEADER */

.header{
    background:#fff;
    padding:20px 25px;
    border-bottom:1px solid #e2e8f0;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.logo{
    display:flex;
    flex-direction:column;
    gap:6px;
    align-items:flex-start;
}

.brand-logo{
    display:block;
    max-width:170px;
    max-height:54px;
    width:auto;
    height:auto;
    object-fit:contain;
}

.brand-logo-text{
    display:block;
    font-size:22px;
    font-weight:700;
    color:#111827;
    line-height:1.1;
    word-break:break-word;
}

.quote-meta{
    text-align:right;
    font-size:11px;
    color:#64748b;
    line-height:1.6;
}

/* CONTENT */

.content{
    padding:25px;
}

.destination{
    font-size:24px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:8px;
}

.subtitle{
    color:#64748b;
    margin-bottom:25px;
}

.section-title{
    font-size:14px;
    text-transform:uppercase;
    letter-spacing:.5px;
    color:#0f172a;
    font-weight:700;
    margin-bottom:12px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:15px;
    margin-bottom:25px;
}
.card{
    background:#f8fafc;
    padding:14px;
    border-radius:10px;
}

.card-label{
    font-size:10px;
    text-transform:uppercase;
    color:#64748b;
}

.card-value{
    margin-top:4px;
    font-size:15px;
    font-weight:600;
}

.quote-table{
    width:100%;
    border-collapse:collapse;
    margin-bottom:20px;
}

.quote-table th{
    background:#f1f5f9;
    padding:12px;
    text-align:left;
    font-size:11px;
    color:#475569;
    border-bottom:2px solid #cbd5e1;
}

.quote-table td{
    padding:12px;
    border-bottom:1px solid #e2e8f0;
}

.quote-table td:last-child,
.quote-table th:last-child{
    text-align:right;
}

.info-blue{
    background:#eff6ff;
    border-left:4px solid #2563eb;
    padding:14px;
    border-radius:0 8px 8px 0;
    color:#1e40af;
    margin-bottom:15px;
}

.info-green{
    background:#f0fdf4;
    border-left:4px solid #16a34a;
    padding:14px;
    border-radius:0 8px 8px 0;
    color:#166534;
    margin-bottom:20px;
}

.hotel-card{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:10px;
    padding:14px;
    margin-bottom:12px;
}

.hotel-location{
    color:#64748b;
    font-size:12px;
    margin-bottom:4px;
}

.hotel-name{
    font-weight:600;
}

.hotel-summary-list{
    margin-top:10px;
    display:flex;
    flex-direction:column;
    gap:8px;
}

.hotel-summary-item{
    font-size:12px;
    line-height:1.45;
    color:#111827;
    word-break:break-word;
}

.hotel-summary-item strong{
    color:#1e40af;
}
.policy-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
    margin-bottom:20px;
}

.panel{
    background:#f8fafc;
    border-radius:10px;
    padding:15px;
}

.panel h4{
    margin-bottom:10px;
}

.green{
    border-top:3px solid #22c55e;
}

.red{
    border-top:3px solid #ef4444;
}

.policy-card{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:10px;
    padding:15px;
    margin-bottom:15px;
}

.policy-title{
    font-size:11px;
    text-transform:uppercase;
    color:#475569;
    font-weight:700;
    margin-bottom:6px;
}

.note{
    background:#fff7ed;
    border-left:4px solid #f97316;
    padding:15px;
    border-radius:0 8px 8px 0;
}

.oksia-simple-list{
    margin:0;
    padding-left:0;
    list-style-position:inside;
}

.oksia-simple-list li{
    margin:0 0 6px;
    overflow-wrap:anywhere;
    word-break:break-word;
}

.booking-ref-card{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:10px;
    padding:14px;
    margin-top:15px;
}

.booking-ref-card__label{
    font-size:11px;
    text-transform:uppercase;
    color:#64748b;
    margin-bottom:4px;
}

.booking-ref-card__value{
    font-size:15px;
    font-weight:600;
    color:#111827;
    word-break:break-word;
}

.timeline{
    border-left:2px solid #e2e8f0;
    margin-left:12px;
    padding-left:24px;
}

.day-card{
    position:relative;
    margin-bottom:25px;
    page-break-inside:avoid;
}

.day-card:before{
    content:"";
    position:absolute;
    left:-31px;
    top:4px;
    width:10px;
    height:10px;
    background:#2563eb;
    border-radius:50%;
}

.day-title{
    font-weight:700;
    margin-bottom:6px;
}

.day-title span{
    color:#2563eb;
}

.day-text{
    color:#4a5568;
}
.footer{
    background:#f8fafc;
    border-top:1px solid #e2e8f0;
    padding:15px 25px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-size:13px;
    color:#64748b;
}

.footer-left{
    font-size:14px;
    line-height:1.55;
    color:#334155;
}

</style>
</head>
<body>

<!-- PAGE 1 -->

<div class="quote-page">

<div class="header">

<div class="logo">
{{LOGO_MARK}}
</div>

<div class="quote-meta">
Quote: <strong>{{QUOTE_NUMBER}}</strong><br>
Version: <strong>{{QUOTE_VERSION}}</strong>
</div>

</div>

<div class="content">

<h2 class="destination">
{{DESTINATION_NAME}}
</h2>

<p class="subtitle">
Personalized trip quotation prepared for
<strong>{{CLIENT_NAME}}</strong>
({{TOTAL_PAX}} Pax)
</p>

<div class="grid">

<div class="card">
<div class="card-label">Check In</div>
<div class="card-value">{{CHECKIN_DATE}}</div>
</div>

<div class="card">
<div class="card-label">Check Out</div>
<div class="card-value">{{CHECKOUT_DATE}}</div>
</div>

</div>

<h3 class="section-title">
Price Quotation
</h3>

<table class="quote-table">

<tr>
<th>Item Description</th>
<th>Rate ({{CURRENCY}})</th>
</tr>

<tr>
<td>
Per Adult Rate ({{ADULT_PAX}} Pax)
</td>
<td>
{{ADULT_RATE}}
</td>
</tr>

<tr>
<td>
Extra With Bed ({{WITH_BED_PAX}} Pax)
</td>
<td>
{{WITH_BED_RATE}}
</td>
</tr>

<tr>
<td>
Child No Bed ({{CHILD_PAX}} Child)
</td>
<td>
{{CHILD_RATE}}
</td>
</tr>

</table>

<div class="info-blue">

<strong>Category:</strong>
{{HOTEL_CATEGORY}}

&nbsp; | &nbsp;

<strong>Rooms:</strong>
{{ROOM_COUNT}}

&nbsp; | &nbsp;

<strong>Occupancy:</strong>
{{OCCUPANCY}}

&nbsp; | &nbsp;

<strong>Meal Plan:</strong>
{{MEAL_PLAN}}

{{HOTEL_LIST}}

</div>

<div class="info-green">

<strong>Vehicle:</strong>
{{VEHICLE_NAME}}

({{VEHICLE_TYPE}})

&nbsp; | &nbsp;

<strong>Route:</strong>
{{PICKUP_LOCATION}}
to
{{DROP_LOCATION}}

&nbsp; | &nbsp;

<strong>Sightseeing Vehicle:</strong>
{{SIGHTSEEING_VEHICLE}}

</div>

{{BOOKING_REFERENCE_BLOCK}}

</div>

<div class="footer">

<div class="footer-left">
{{FOOTER_LINE}}
</div>

<div class="footer-left">
Page 1
</div>

</div>

</div>

<!-- PAGE 2 START -->

<div class="quote-page">

<div class="header">

<div class="logo">
{{LOGO_MARK}}
</div>

<div class="quote-meta">
Quote: <strong>{{QUOTE_NUMBER}}</strong><br>
Version: <strong>{{QUOTE_VERSION}}</strong>
</div>

</div>

<div class="content">
<div class="policy-grid">

<div class="panel green">

<h4>
Inclusions
</h4>

{{INCLUSIONS}}

</div>

<div class="panel red">

<h4>
Exclusions
</h4>

{{EXCLUSIONS}}

</div>

</div>

<h3 class="section-title">
Terms & Policies
</h3>

<div class="policy-card">

<div class="policy-title">
Child Policy
</div>

{{CHILD_POLICY}}

</div>

<div class="policy-card">

<div class="policy-title">
Booking Policy
</div>

{{BOOKING_POLICY}}

</div>

<div class="policy-card">

<div class="policy-title">
Cancellation Policy
</div>

{{CANCELLATION_POLICY}}

</div>

<div class="note">

<strong>
Important Notes
</strong>

<br><br>

{{IMPORTANT_NOTES}}

</div>

</div>

<div class="footer">

<div class="footer-left">
{{FOOTER_LINE}}
</div>

<div class="footer-left">
Page 2
</div>

</div>

</div>

<!-- PAGE 3 START -->

<div class="quote-page">

<div class="header">

<div class="logo">
{{LOGO_MARK}}
</div>

<div class="quote-meta">
Quote: <strong>{{QUOTE_NUMBER}}</strong><br>
Version: <strong>{{QUOTE_VERSION}}</strong>
</div>

</div>

<div class="content">

<h3 class="section-title">
Day Wise Tentative Schedule
</h3>

<div class="timeline">

{{ITINERARY_DAYS}}

</div>

</div>

<div class="footer">

<div class="footer-left">
{{FOOTER_LINE}}
</div>

<div class="footer-left">
Page 3
</div>

</div>

</div>
</body>
</html>
