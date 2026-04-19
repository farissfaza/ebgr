<nav class="navbar navbar-expand navbar-light glass-navbar px-4 py-3 d-flex justify-content-between">
    <a href="#" class="sidebar-toggle d-flex align-items-center me-3" style="text-decoration: none; color: #2c3e50;">
        <i class="bi bi-list fs-3 toggle-icon"></i>
    </a>
    
    <div class="navbar-collapse collapse justify-content-end">
        <ul class="navbar-nav align-items-center">
            <li class="nav-item me-3">
                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal" title="Pengaturan Tema">
                    <i class="bi bi-gear-fill fs-5 text-secondary toggle-icon"></i>
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
/* Liquid Glass Navbar */
.glass-navbar {
    background: rgba(255, 255, 255, 0.45);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.5);
    z-index: 999;
    box-shadow: 0 4px 15px rgba(31, 38, 135, 0.05);
}

.toggle-icon {
    transition: transform 0.3s ease, color 0.3s ease;
}

.toggle-icon:hover {
    color: #0d6efd;
    transform: scale(1.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggler = document.querySelector('.sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    if (toggler && sidebar) {
        toggler.addEventListener('click', function(e) {
            e.preventDefault();
            if (sidebar.style.display === 'none' || sidebar.style.marginLeft === '-260px') {
                sidebar.style.display = 'block';
                sidebar.style.marginLeft = '0';
            } else {
                // simple hiding for now to mimic toggle
                sidebar.style.marginLeft = '-260px';
                setTimeout(() => { sidebar.style.display = 'none'; }, 300);
            }
        });
    }
});
</script>
