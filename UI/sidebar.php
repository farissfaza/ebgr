<nav id="sidebar" class="sidebar js-sidebar glass-sidebar">
    <div class="sidebar-content js-simplebar">
        <a class="sidebar-brand text-center d-block py-4" href="index.php" style="text-decoration: none;">
            <span class="align-middle fw-bolder" style="font-size: 1.5rem; color: #2c3e50; text-shadow: 1px 1px 2px rgba(255,255,255,0.8);">EBGR - Sidebar</span>
        </a>
        <ul class="sidebar-nav list-unstyled mb-0">
            <li class="sidebar-header px-4 py-2 text-muted fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">Menu Utama</li>
            <li class="sidebar-item mt-2">
                <a class="sidebar-link" href="index.php">
                    <i class="align-middle bi bi-house-door"></i> <span class="align-middle">Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item mt-2">
                <a class="sidebar-link" href="form_checkin.php">
                    <i class="align-middle bi bi-person-plus"></i> <span class="align-middle">Check-In Tamu</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
/* Liquid Glass Sidebar */
.glass-sidebar {
    background: rgba(255, 255, 255, 0.45);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-right: 1px solid rgba(255, 255, 255, 0.5);
    min-width: 260px;
    max-width: 260px;
    height: 100vh;
    position: sticky;
    top: 0;
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 4px 0 15px rgba(31, 38, 135, 0.05);
}

.sidebar-link {
    display: flex;
    align-items: center;
    padding: 0.85rem 1.5rem;
    color: #495057;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    border-radius: 12px;
    margin: 0 1rem;
    position: relative;
    overflow: hidden;
}

.sidebar-link:hover, .sidebar-item.active .sidebar-link {
    background: rgba(255, 255, 255, 0.7);
    color: #0d6efd;
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(31, 38, 135, 0.1);
}

.sidebar-link i {
    margin-right: 12px;
    font-size: 1.3rem;
    transition: all 0.3s;
}

.sidebar-link:hover i {
    transform: scale(1.1);
    color: #0d6efd;
}

/* White global background as per user request */
body {
    background-color: #ffffff !important;
}

/* Wrapper setup */
#wrapper {
    display: flex;
    width: 100%;
    align-items: stretch;
    min-height: 100vh;
}

.main-content {
    flex-grow: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}
</style>
