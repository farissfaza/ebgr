<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-config-modal">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="settingsModalLabel">
                    <i class="bi bi-sliders me-2 text-primary"></i>Pengaturan UI
                </h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-4">
                
                <div class="mb-2 text-center">
                    <h6 class="fw-bold mb-4 text-secondary">Tema Tampilan</h6>
                    
                    <div class="d-flex flex-column gap-3">
                        <input type="radio" class="btn-check" name="themeSwitch" id="themeLight" autocomplete="off" checked>
                        <label class="btn btn-outline-primary theme-btn py-3 rounded-4 shadow-sm" for="themeLight">
                            <i class="bi bi-sun-fill me-2 fs-5 align-middle"></i> 
                            <span class="fw-semibold">ioT Development Light</span>
                        </label>

                        <input type="radio" class="btn-check" name="themeSwitch" id="themeDark" autocomplete="off">
                        <label class="btn btn-outline-dark theme-btn py-3 rounded-4 shadow-sm" for="themeDark">
                            <i class="bi bi-moon-stars-fill me-2 fs-5 align-middle"></i> 
                            <span class="fw-semibold">ioT Development Dark</span>
                        </label>
                    </div>
                </div>
                
            </div>
            <div class="modal-footer border-top-0 pt-0 pb-4 justify-content-center">
                <p class="text-muted small mb-0">Pilih gaya visual dashboard yang nyaman untuk Anda.</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Glassmorphism settings modal */
#settingsModal {
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    background-color: rgba(0, 0, 0, 0.4);
}

.glass-config-modal {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

/* Dark mode overrides for the modal */
html.dark-mode .glass-config-modal {
    background: rgba(30, 33, 36, 0.85) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: #f8f9fa;
}
html.dark-mode .glass-config-modal .text-secondary {
    color: #adb5bd !important;
}

.theme-btn {
    transition: all 0.3s ease;
    border-width: 2px;
}

.theme-btn:hover {
    transform: translateY(-2px);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeLight = document.getElementById('themeLight');
    const themeDark = document.getElementById('themeDark');
    const htmlElement = document.documentElement;
    
    // Sinkronisasi status radio button berdasarkan local storage
    let currentTheme = localStorage.getItem('iot_theme') || 'light';
    
    if (currentTheme === 'dark') {
        themeDark.checked = true;
        htmlElement.classList.add('dark-mode');
    } else {
        themeLight.checked = true;
        htmlElement.classList.remove('dark-mode');
    }

    // Event listeners
    if(themeLight) {
        themeLight.addEventListener('change', function() {
            if (this.checked) {
                htmlElement.classList.remove('dark-mode');
                localStorage.setItem('iot_theme', 'light');
            }
        });
    }

    if(themeDark) {
        themeDark.addEventListener('change', function() {
            if (this.checked) {
                htmlElement.classList.add('dark-mode');
                localStorage.setItem('iot_theme', 'dark');
            }
        });
    }
});
</script>
