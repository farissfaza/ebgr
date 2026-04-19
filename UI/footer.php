<footer class="footer mt-auto py-3 bg-white border-top">
    <div class="container-fluid">
        <div class="row text-muted">
            <div class="col-6 text-start">
                <p class="mb-0">
                    <a class="text-muted text-decoration-none" href="#"><strong>EBGR - Footer</strong></a> &copy; <?php echo date('Y'); ?>
                </p>
            </div>
            <div class="col-6 text-end">
                <ul class="list-inline mb-0">
                    <li class="list-inline-item">
                        <a class="text-muted text-decoration-none" href="#" data-bs-toggle="modal" data-bs-target="#changelogModal">
                            <i class="bi bi-journal-text me-1"></i> Log Pembaruan
                        </a>
                    </li>
                    <li class="list-inline-item ms-3">
                        <a class="text-muted text-decoration-none" href="#">Support</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</footer>

<!-- Changelog Modal -->
<div class="modal fade" id="changelogModal" tabindex="-1" aria-labelledby="changelogModalLabel" aria-hidden="true" style="backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4" style="background: rgba(255, 255, 255, 0.95);">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-primary" id="changelogModalLabel">
                    <i class="bi bi-stars me-2 text-warning"></i> Log Pembaruan Sistem
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-info border-0 rounded-3 mb-4" style="background-color: rgba(13, 202, 240, 0.1);">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle-fill text-info fs-4 me-3"></i>
                        <div>
                            <h6 class="alert-heading fw-bold mb-1">Pembaruan Terbaru</h6>
                            <p class="mb-0 small text-muted">Aplikasi ini baru saja mendapatkan beberapa peningkatan penting untuk mengoptimalkan alur Check-in.</p>
                        </div>
                    </div>
                </div>

                <div class="timeline position-relative ps-4 border-start border-2 border-primary border-opacity-25 pb-3">
                    <!-- Update Entry Version 2.0 -->
                    <div class="position-relative mb-4">
                        <span class="position-absolute top-0 start-0 translate-middle p-2 bg-success border border-light rounded-circle" style="left: -1rem !important;"></span>
                        <div class="ms-3">
                            <h6 class="fw-bold mb-1">Pembaruan Versi 2.0 (Massive Core Update)</h6>
                            <p class="text-muted small mb-3"><i class="bi bi-rocket-takeoff text-success me-1"></i> Diluncurkan 9 Maret 2026</p>
                            
                            <ul class="list-group list-group-flush border-0">
                                <li class="list-group-item bg-transparent px-0 py-2 border-bottom border-light">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-qr-code-scan text-primary me-2"></i>Cross-Device KTP Scanner (Mobile & Desktop Bridge)</h6>
                                    <p class="mb-0 small text-muted">Staf kini dapat memotret KTP secara nirkabel menggunakan Kamera Handphone mereka sendiri melalui <strong>Live QR Code Pairing</strong>. Foto dan detail KTP langsung mengalir ke server secara real-time dan masuk ke form desktop tanpa perlu kabel data!</p>
                                </li>
                                <li class="list-group-item bg-transparent px-0 py-2 border-bottom border-light">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-cpu text-info me-2"></i>Sistem Heuristik OCR 2.0 & Akurasi NIK</h6>
                                    <p class="mb-0 small text-muted">Mesin OCR (Google Vision) kini dilengkapi penyaring (filter) khusus untuk mengabaikan serial <em>Watermark KTP</em>, meluruskan format NIK yang melompat akibat pantulan Flash/Glare, serta jangkar pelindung agar posisi alamat tidak tertukar jika ada kata yang hilang.</p>
                                </li>
                                <li class="list-group-item bg-transparent px-0 py-2 border-bottom border-light">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-clock-history text-warning me-2"></i>Dashboard Selesai / Auto-Checkout Otomatis</h6>
                                    <p class="mb-0 small text-muted">Tabel Daftar Tamu kini menjadi lebih canggih. Jika masa menginap tamu melewati <code>tanggal_check_out</code> (H+1 atau lebih), maka Dashboard akan secara <strong>Otomatis</strong> menjadikan status mereka sebagai <em>Selesai</em> tanpa perlu campur tangan manual!</p>
                                </li>
                                <li class="list-group-item bg-transparent px-0 py-2">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-bug text-danger me-2"></i>Perbaikan Bug Payload File KTP</h6>
                                    <p class="mb-0 small text-muted">Perbaikan fatal Javascript yang sebelumnya membuat file gambar OCR dari upload file manual dan injeksi mobile gagal masuk. Kini upload jalur Desktop maupun jalur HP tersimpan aman ke Database dan Halaman Detail Tamu.</p>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Pervious Update Entry -->
                    <div class="position-relative mb-4">
                        <span class="position-absolute top-0 start-0 translate-middle p-2 bg-primary border border-light rounded-circle" style="left: -1rem !important;"></span>
                        <div class="ms-3">
                            <h6 class="fw-bold mb-1">Pembaruan Versi 1.5</h6>
                            <p class="text-muted small mb-3"><i class="bi bi-calendar-event me-1"></i> Diluncurkan 3 Maret 2026</p>
                            
                            <ul class="list-group list-group-flush border-0">
                                <li class="list-group-item bg-transparent px-0 py-2 border-bottom border-light">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-shield-lock text-success me-2"></i>Validasi Jadwal Real-time (Anti Double Booking)</h6>
                                    <p class="mb-0 small text-muted">Sistem kini langsung menolak dan mengunci form (warna peringatan merah) saat pengguna memilih Kamar/Kelas yang sedang dipakai, atau jika NIK dan Nama yang sama diregistrasi ganda di tanggal yang bentrok.</p>
                                </li>
                                <li class="list-group-item bg-transparent px-0 py-2 border-bottom border-light">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-save text-primary me-2"></i>Penyimpanan Sementara Data Ketik</h6>
                                    <p class="mb-0 small text-muted">Mencegah hilangnya ketikan yang sudah diisi! Jika validasi gagal saat <i>submit</i>, seluruh isian form akan tetap dipertahankan dan tidak terhapus.</p>
                                </li>
                                <li class="list-group-item bg-transparent px-0 py-2 border-bottom border-light">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-palette text-warning me-2"></i>Pembaruan UI Settings & Tema Tampilan</h6>
                                    <p class="mb-0 small text-muted">Panel pengaturan sekarang tampil sebagai <strong>Glassmorphism Modal</strong> (Pop-up tengah dengan efek kaca kabur) dan label pilihan tema telah diperbarui menjadi <em>"ioT Development Light" & "ioT Development Dark"</em>.</p>
                                </li>
                                <li class="list-group-item bg-transparent px-0 py-2 border-bottom border-light">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-card-image text-danger me-2"></i>Wajib Upload KTP (Bypass Warning)</h6>
                                    <p class="mb-0 small text-muted">Menegakkan regulasi ketat perusahaan di mana proses "Ketik Manual" tetap <strong>memaksa</strong> staf untuk mengunggah foto KTP sebelum diperbolehkan menyimpan tamu.</p>
                                </li>
                                <li class="list-group-item bg-transparent px-0 py-2">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-database-check text-info me-2"></i>Pembersihan Skema Database</h6>
                                    <p class="mb-0 small text-muted">Optimalisasi riwayat tabel (dibersihkan massal) untuk menyiapkan server produksi, menyisakan khusus 1 data tamu aktif definitif terbaru.</p>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">Saya Mengerti</button>
            </div>
        </div>
    </div>
</div>
