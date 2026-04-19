<?php
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token)) {
    die("<h3>Token tidak valid atau tidak ditemukan. Harap scan ulang QR Code dari layar komputer/tablet Anda.</h3>");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pindai KTP - EBGR Kiosk</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- heic2any for iPhone photos -->
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 15px;
        }
        .scanner-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 400px;
            padding: 30px 20px;
            text-align: center;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 36px;
            box-shadow: 0 10px 20px rgba(13, 110, 253, 0.3);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4); }
            70% { box-shadow: 0 0 0 20px rgba(13, 110, 253, 0); }
            100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
        }
        .btn-capture {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            border-radius: 50px;
            padding: 15px 30px;
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            width: 100%;
            box-shadow: 0 8px 15px rgba(56, 239, 125, 0.3);
            transition: all 0.3s ease;
        }
        .btn-capture:active {
            transform: scale(0.95);
        }
        #ktp-preview-container {
            display: none;
            margin-top: 20px;
        }
        #ktp-preview {
            width: 100%;
            border-radius: 12px;
            border: 2px solid #dee2e6;
            margin-bottom: 20px;
        }
        /* Hidden file input */
        #camera-input {
            display: none;
        }
    </style>
</head>
<body>

<div class="scanner-card">
    <div class="icon-circle" id="main-icon">
        <i class="bi bi-camera"></i>
    </div>
    
    <h3 class="fw-bold mb-2">Ambil Foto KTP</h3>
    <p class="text-muted mb-4">Pastikan KTP berada di tempat terang dan tulisan terbaca jelas (fokus).</p>

    <div id="capture-section">
        <!-- Standard Form Submission to bypass Safari strict Local Network CORS -->
        <form id="upload-form" action="api_mobile_upload.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            
            <!-- Input for direct camera capture -->
            <input type="file" id="camera-input" name="ktp_image" accept="image/*" capture="environment" style="display: none;">
            
            <!-- Input for gallery/file selection (no capture attribute) -->
            <input type="file" id="gallery-input" name="ktp_image_gallery" accept="image/*,.heic,.heif" style="display: none;">
        </form>
        
        <button type="button" class="btn btn-capture mb-3" onclick="document.getElementById('camera-input').click()">
            <i class="bi bi-camera-fill me-2"></i> Buka Kamera
        </button>
        
        <button type="button" class="btn btn-outline-primary mb-3 w-100 rounded-pill py-2" onclick="document.getElementById('gallery-input').click()" style="font-weight: 600;">
            <i class="bi bi-images me-2"></i> Pilih dari Galeri / File
        </button>
        
        <div class="small text-muted"><i class="bi bi-shield-check text-success"></i> Data langsung dikirim ke layar Komputer</div>
    </div>

    <div id="processing-section" class="d-none">
        <div class="spinner-border text-primary ms-auto" role="status" aria-hidden="true" style="width: 3rem; height: 3rem;"></div>
        <h5 class="mt-3 fw-bold text-primary">Memproses & Mengirim...</h5>
        <p class="text-muted small">Sedang membaca teks KTP dan mengirim ke formulir di layar utama. Mohon tunggu.</p>
    </div>

    <!-- We will rely on PHP header redirect back to mobile_ktp.php?status=success -->
    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('capture-section').classList.add('d-none');
            document.getElementById('main-icon').style.display = 'none';
            document.querySelector('.text-muted.mb-4').style.display = 'none';
            document.querySelector('h3').style.display = 'none';
        });
    </script>
    <div id="success-section" class="mt-4">
        <div class="text-success mb-3" style="font-size: 4rem;">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <h4 class="fw-bold text-success">Berhasil!</h4>
        <p class="text-muted">Data KTP sudah terkirim ke komputer. Anda bisa menutup halaman ini dan kembali ke komputer utama.</p>
    </div>
    <?php elseif (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: '<?= htmlspecialchars($_GET['message'] ?? 'Terjadi kesalahan saat memproses gambar.') ?>',
                confirmButtonColor: '#0d6efd'
            });
        });
    </script>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cameraInput = document.getElementById('camera-input');
    const galleryInput = document.getElementById('gallery-input');
    const captureSection = document.getElementById('capture-section');
    const processingSection = document.getElementById('processing-section');
    const mainIcon = document.getElementById('main-icon');
    const token = document.querySelector('input[name="token"]').value;

    function handleImageSelection(inputElement) {
        if (inputElement.files && inputElement.files.length > 0) {
            let file = inputElement.files[0];
            
            // Switch UI to processing
            captureSection.classList.add('d-none');
            processingSection.classList.remove('d-none');
            mainIcon.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            mainIcon.style.background = 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)';
            mainIcon.style.boxShadow = '0 10px 20px rgba(253, 160, 133, 0.4)';

            // Handle HEIC for iOS
            if (file.name.toLowerCase().endsWith('.heic') || file.name.toLowerCase().endsWith('.heif')) {
                heic2any({
                    blob: file,
                    toType: "image/jpeg",
                    quality: 0.8
                }).then(function(conversionResult) {
                    let convertedBlob = Array.isArray(conversionResult) ? conversionResult[0] : conversionResult;
                    const newFile = new File([convertedBlob], file.name.split('.')[0] + ".jpg", {
                        type: "image/jpeg",
                        lastModified: new Date().getTime()
                    });
                    
                    uploadViaFetch(newFile);
                }).catch(function(err) {
                    console.error("HEIC Conversion failed:", err);
                    showError("Gagal merubah format foto iPhone. Silakan coba lagi.");
                });
            } else {
                uploadViaFetch(file);
            }
        }
    }

    function uploadViaFetch(fileToUpload) {
        const formData = new FormData();
        formData.append('token', token);
        formData.append('ktp_image', fileToUpload);

        fetch('api/api_mobile_upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.url && response.url.includes('status=error')) {
                const urlObj = new URL(response.url);
                const msg = urlObj.searchParams.get('message') || 'Server menolak gambar (Redirected to Error).';
                throw new Error(msg);
            }
            if (response.url && response.url.includes('status=success')) {
                window.location.href = response.url;
                return;
            }
            if (!response.ok) {
                throw new Error('Koneksi HTTP ke server terputus (' + response.status + ').');
            }
            window.location.href = 'mobile_ktp.php?status=success';
        })
        .catch(err => {
            console.error("Fetch Error:", err);
            showError(err.message);
        });
    }

    cameraInput.addEventListener('change', function() { handleImageSelection(this); });
    galleryInput.addEventListener('change', function() { handleImageSelection(this); });

    function showError(msg) {
        processingSection.classList.add('d-none');
        captureSection.classList.remove('d-none');
        mainIcon.innerHTML = '<i class="bi bi-camera"></i>';
        mainIcon.style.background = 'linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%)';
        
        // Reset inputs so user can select same file again if they want
        cameraInput.value = '';
        galleryInput.value = '';
        
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: msg,
            confirmButtonColor: '#0d6efd'
        });
    }
});
</script>

</body>
</html>
