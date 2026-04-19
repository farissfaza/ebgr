<!-- Global Theme Setup -->
<script>
    // Inisialisasi tema paling awal agar tidak flicker
    let currentTheme = localStorage.getItem('iot_theme') || 'light';
    if (currentTheme === 'dark') {
        document.documentElement.classList.add('dark-mode');
    }
</script>

<style>
    /* Transisi untuk tema */
    body {
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    /* DARK MODE HIGHLIGHT & TEXT CONTRAST FIXES */
    html.dark-mode body {
        background-color: #121212 !important;
        color: #e9ecef !important;
    }
    
    html.dark-mode h1, html.dark-mode h2, html.dark-mode h3, 
    html.dark-mode h4, html.dark-mode h5, html.dark-mode h6,
    html.dark-mode strong, html.dark-mode .fw-bold {
        color: #ffffff !important;
    }
    
    html.dark-mode h1.text-dark {
        text-shadow: 2px 2px 4px rgba(0,0,0,0.8) !important;
    }
    
    html.dark-mode .bg-white {
        background-color: #1e2124 !important;
    }
    
    html.dark-mode .text-dark {
        color: #ffffff !important;
    }

    html.dark-mode .text-muted, 
    html.dark-mode .text-secondary {
        color: #adb5bd !important;
    }

    html.dark-mode .text-primary {
        color: #5ee79d !important; /* brighter green/teal for visibility */
    }
    
    html.dark-mode .form-label, 
    html.dark-mode label {
        color: #e9ecef !important;
    }
    
    html.dark-mode .content.glass-dashboard-bg {
        background: rgba(24, 26, 28, 0.95) !important;
    }
    
    html.dark-mode .glass-dashboard-bg::before {
        background: #4a2c91 !important;
        opacity: 0.25;
    }
    
    html.dark-mode .glass-dashboard-bg::after {
        background: #b74d57 !important;
        opacity: 0.25;
    }
    
    html.dark-mode .glass-card, 
    html.dark-mode .glass-form-card {
        background: rgba(35, 40, 45, 0.75) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.4) !important;
        color: #e9ecef !important;
    }
    
    html.dark-mode .glass-header {
        background: rgba(255, 255, 255, 0.05) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    
    html.dark-mode .glass-sidebar,
    html.dark-mode .glass-navbar {
        background: rgba(22, 25, 28, 0.85) !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
    }
    
    html.dark-mode .sidebar-link {
        color: #ced4da !important;
    }
    
    html.dark-mode .sidebar-link:hover, 
    html.dark-mode .sidebar-item.active .sidebar-link {
        background: rgba(255, 255, 255, 0.12) !important;
        color: #38ef7d !important;
    }
    
    html.dark-mode .sidebar-brand {
        color: #ffffff !important;
    }
    
    html.dark-mode .sidebar-link:hover i {
        color: #38ef7d !important;
    }
    
    html.dark-mode .form-section {
        background: #25292d !important;
        border-color: #353a40 !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
    }

    html.dark-mode .form-control, 
    html.dark-mode .form-select,
    html.dark-mode textarea {
        background-color: #171a1d !important;
        border-color: #3d4349 !important;
        color: #ffffff !important;
    }
    
    html.dark-mode .form-control:focus, 
    html.dark-mode .form-select:focus {
        border-color: #38ef7d !important;
        box-shadow: 0 0 0 0.25rem rgba(56, 239, 125, 0.2) !important;
    }
    
    html.dark-mode .glass-table {
        color: #e9ecef !important;
        --bs-table-hover-bg: rgba(255,255,255,0.08) !important;
    }
    
    html.dark-mode .glass-table thead th {
        color: #f8f9fa !important;
        border-bottom-color: rgba(255,255,255,0.15) !important;
    }
    
    html.dark-mode .glass-table tbody tr:hover td {
        background-color: rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }
    
    html.dark-mode .card {
        background-color: #22262a !important;
        border-color: #363b41 !important;
    }
    
    html.dark-mode .detail-list dt {
        color: #adb5bd !important;
    }
    
    html.dark-mode .detail-list dd {
        color: #f8f9fa !important;
    }
    
    html.dark-mode .card-header {
        border-bottom-color: #363b41 !important;
        background-color: rgba(0,0,0,0.15) !important;
        color: #ffffff !important;
    }
    
    /* Method Card Dark Mode */
    html.dark-mode .method-card {
        background: rgba(45, 50, 56, 0.8) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
    }
    html.dark-mode .method-card p {
        color: #adb5bd !important;
    }
    html.dark-mode .method-card.selected {
        background: linear-gradient(135deg, rgba(17, 153, 142, 0.25) 0%, rgba(56, 239, 125, 0.25) 100%) !important;
        border-color: #38ef7d !important;
    }
    html.dark-mode .method-card.selected .method-icon {
        color: #38ef7d !important;
    }
    
    html.dark-mode .step-circle {
        background: #363b41 !important;
        color: #adb5bd !important;
    }
    html.dark-mode .step.active .step-circle {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%) !important;
        color: #121212 !important;
        box-shadow: 0 0 0 5px #121212, 0 5px 15px rgba(56, 239, 125, 0.5) !important;
    }
    html.dark-mode .step.active small {
        color: #5ee79d !important;
    }
    html.dark-mode .step.completed .step-circle {
        background: #11998e !important;
        box-shadow: 0 0 0 5px #121212 !important;
        color: #ffffff !important;
    }
    html.dark-mode .progress-steps::before {
        background: #2c3136 !important;
    }
    html.dark-mode .border-bottom, 
    html.dark-mode .border-top {
        border-color: #2c3136 !important;
    }

    html.dark-mode .footer {
        background-color: #1a1d21 !important;
        border-top-color: #2c3136 !important;
    }

    html.dark-mode .toggle-icon {
        color: #b0b5ba !important;
    }
    html.dark-mode .toggle-icon:hover {
        color: #38ef7d !important;
    }
    
    /* --- NEW INDEX METRICS & FILTER FIXES FOR DARK MODE --- */
    html.dark-mode .glass-filter-container {
        background: linear-gradient(to right, rgba(35, 40, 45, 0.8), rgba(25, 30, 35, 0.6)) !important;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1), 0 4px 15px rgba(0,0,0,0.4) !important;
    }
    
    html.dark-mode .glass-filter-container .bg-white,
    html.dark-mode .glass-filter-container .bg-light {
        background-color: #1e2124 !important;
        border-color: #363b41 !important;
    }
    
    html.dark-mode .glass-filter-container .form-control {
        background-color: #171a1d !important;
        color: #e9ecef !important;
    }
    
    html.dark-mode .glass-filter-container :-webkit-calendar-picker-indicator {
        filter: invert(1);
    }
    
    /* Fix for Search Icon color inside dark mode */
    html.dark-mode .glass-filter-container .bi-search,
    html.dark-mode .glass-filter-container .bi-calendar-event {
        color: #5ee79d !important;
    }
    
    /* Fix Sortable Table Column links in dark mode */
    html.dark-mode .sortable {
        color: #f8f9fa !important;
    }
    html.dark-mode .sortable:hover {
        color: #5ee79d !important;
    }
    html.dark-mode .sortable::after {
        color: rgba(255,255,255,0.3) !important;
    }
    html.dark-mode .sort-asc::after,
    html.dark-mode .sort-desc::after {
        color: #5ee79d !important;
    }

    /* Offcanvas Settings Dark Mode Fixes */
    html.dark-mode .offcanvas {
        background-color: #1e2124 !important;
        color: #e9ecef !important;
    }
    
    html.dark-mode .offcanvas-header {
        border-bottom-color: #363b41 !important;
    }
    
    html.dark-mode .offcanvas-title {
        color: #ffffff !important;
    }
    
    html.dark-mode .offcanvas .text-secondary,
    html.dark-mode .offcanvas .text-muted {
        color: #adb5bd !important;
    }
    
    html.dark-mode .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

</style>
