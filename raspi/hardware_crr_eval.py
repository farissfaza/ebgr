import csv
import os
import cv2
import time
import requests
import threading
from flask import Flask, Response

try:
    import Levenshtein
except ImportError:
    Levenshtein = None

def fallback_levenshtein(s1, s2):
    if len(s1) < len(s2):
        return fallback_levenshtein(s2, s1)
    if len(s2) == 0:
        return len(s1)
    previous_row = range(len(s2) + 1)
    for i, c1 in enumerate(s1):
        current_row = [i + 1]
        for j, c2 in enumerate(s2):
            insertions = previous_row[j + 1] + 1
            deletions = current_row[j] + 1
            substitutions = previous_row[j] + (c1 != c2)
            current_row.append(min(insertions, deletions, substitutions))
        previous_row = current_row
    return previous_row[-1]

# =====================================================================
# CONFIGURATION
# =====================================================================
OCR_API_URL = "http://10.153.36.172:8000/services/ocr_vision.php" 
GROUND_TRUTH_CSV = "dummy_ktp_data (1).csv" # Sudah disesuaikan di folder raspi/

app = Flask(__name__)
camera = None
current_frame = None
camera_lock = threading.Lock()

# =====================================================================
# FLASK VIDEO FEED 
# =====================================================================
def camera_read_thread():
    global current_frame
    while True:
        if camera is not None and camera.isOpened():
            ret, frame = camera.read()
            if ret:
                with camera_lock:
                    current_frame = frame.copy()
            else:
                time.sleep(0.1)
        else:
            time.sleep(0.1)

def generate_frames():
    while True:
        with camera_lock:
            frame = current_frame
            
        if frame is None:
            time.sleep(0.1)
            continue
            
        ret, buffer = cv2.imencode('.jpg', frame)
        if not ret: continue
        yield (b'--frame\r\n'
               b'Content-Type: image/jpeg\r\n\r\n' + buffer.tobytes() + b'\r\n')
        time.sleep(0.05)

@app.route('/')
def video_feed():
    return Response(generate_frames(), mimetype='multipart/x-mixed-replace; boundary=frame')

# =====================================================================
# HITUNG CRR
# =====================================================================
def calculate_crr(ground_truth, ocr_result):
    if not ground_truth: return None
    if not ocr_result: return 0.0

    gt = str(ground_truth).upper().strip()
    ocr = str(ocr_result).upper().strip()
    
    if Levenshtein:
        errors = Levenshtein.distance(gt, ocr)
    else:
        errors = fallback_levenshtein(gt, ocr)
        
    n = len(gt)
    if n == 0: return 0.0
    return max((1 - errors / n) * 100, 0.0)

# =====================================================================
# INISIALISASI KAMERA
# =====================================================================
def init_camera():
    global camera
    print("[INFO] Menginisialisasi kamera...")
    camera = cv2.VideoCapture(0)
    if not camera.isOpened():
        raise RuntimeError("Gagal membuka kamera Raspi!")
    
    camera.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
    camera.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)
    
    threading.Thread(target=camera_read_thread, daemon=True).start()
    threading.Thread(target=lambda: app.run(host='0.0.0.0', port=5050, debug=False, use_reloader=False), daemon=True).start()
    
    print("\n" + "="*50)
    print(" >>> LIVE FEED KAMERA AKTIF! <<<")
    print(" Buka browser di laptop Anda dan akses:")
    print(" http://<IP_RASPBERRY_PI>:5050/")
    print("="*50 + "\n")
    time.sleep(2)

# =====================================================================
# MENU & PIPELINE EVALUASI INTERAKTIF
# =====================================================================
def run_hardware_evaluation():
    if not os.path.exists(GROUND_TRUTH_CSV):
        print(f"[ERROR] File {GROUND_TRUTH_CSV} tidak ditemukan!")
        return

    # Tampilkan Menu Kondisi Uji
    print("=========================================")
    print(" PILIH KONDISI PENGUJIAN OCR (30 DATA)")
    print("=========================================")
    print(" 1. Normal Lighting, Straight Angle")
    print(" 2. Dim Lighting, Straight Angle")
    print(" 3. Normal Lighting, Slight Tilt (~15 deg)")
    print("=========================================")
    
    choice = input("Masukkan pilihan (1/2/3): ").strip()
    
    if choice == '1':
        OUTPUT_CSV = "hasil_crr_1_normal_straight.csv"
        print(">> Mode Terpilih: NORMAL LIGHTING, STRAIGHT ANGLE")
    elif choice == '2':
        OUTPUT_CSV = "hasil_crr_2_dim_straight.csv"
        print(">> Mode Terpilih: DIM LIGHTING, STRAIGHT ANGLE")
    elif choice == '3':
        OUTPUT_CSV = "hasil_crr_3_normal_tilt.csv"
        print(">> Mode Terpilih: NORMAL LIGHTING, SLIGHT TILT (~15°)")
    else:
        print("[ERROR] Pilihan tidak valid. Keluar.")
        return

    init_camera()
    results = []
    
    # Fields to extract & calculate CRR
    fields = [
        "NIK", "Nama", "Tempat_Lahir", "Tanggal_Lahir", 
        "Jenis_Kelamin", "Alamat", "RT_RW", "Kel_Desa", 
        "Kecamatan", "Agama", "Pekerjaan", "Gol Darah"
    ]

    with open(GROUND_TRUTH_CSV, newline="", encoding="utf-8") as f:
        # Gunakan delimiter ';' karena data dipisahkan dengan titik koma
        reader = csv.DictReader(f, delimiter=';')
        rows = list(reader)

    # Hanya jalankan 30 data pertama
    rows_to_process = rows[:30]

    for row in rows_to_process:
        no = row.get("No", "?")
        nama_gt = row.get("Nama", "")
        
        print(f"\n[KTP {no}/30] Letakkan KTP Atas Nama: {nama_gt} di depan kamera.")
        input(">>> Tekan [ENTER] jika sudah pas...")

        with camera_lock:
            frame_to_capture = current_frame.copy() if current_frame is not None else None
        
        if frame_to_capture is None:
            print("[ERROR] Gagal menangkap gambar!")
            continue

        temp_img_path = f"/tmp/ktp_eval_{no}.jpg"
        cv2.imwrite(temp_img_path, frame_to_capture, [cv2.IMWRITE_JPEG_QUALITY, 95])
        print("  -> Foto diambil. Mengirim ke API...")

        response_data = {}
        try:
            with open(temp_img_path, 'rb') as img_file:
                res = requests.post(OCR_API_URL, files={'identitas': (f"ktp_{no}.jpg", img_file, 'image/jpeg')}, timeout=30)
                response_data = res.json()
        except Exception as e:
            print(f"  -> [ERROR] API Gagal: {e}")
        
        if os.path.exists(temp_img_path): os.remove(temp_img_path)

        ocr_data = {}
        if response_data.get('success'):
            data = response_data.get('data', {})
            addr = data.get('alamat_details', {})
            
            ocr_data = {
                "NIK": data.get('nik', ''),
                "Nama": data.get('nama', ''),
                "Tempat_Lahir": data.get('tempat_lahir', ''),
                "Tanggal_Lahir": data.get('tanggal_lahir', ''),
                "Jenis_Kelamin": data.get('jenis_kelamin', ''),
                "Alamat": addr.get('alamat', ''),
                "RT_RW": addr.get('rt_rw', ''),
                "Kel_Desa": addr.get('kel_desa', ''),
                "Kecamatan": addr.get('kecamatan', ''),
                "Agama": data.get('agama', ''),
                "Pekerjaan": data.get('pekerjaan', ''),
                "Gol Darah": data.get('golongan_darah', '')
            }
            print(f"  -> [SUKSES OCR] Terekstrak {response_data.get('fields_found', 0)} field.")
        else:
            print(f"  -> [GAGAL OCR] {response_data.get('message')}")
            for f in fields: ocr_data[f] = ""

        # Calculate CRR for each field
        result_row = {"no": no}
        print("  -> [CRR]")
        for f in fields:
            gt_val = row.get(f, "")
            ocr_val = ocr_data.get(f, "")
            crr_val = calculate_crr(gt_val, ocr_val)
            
            result_row[f"{f}_GT"] = gt_val
            result_row[f"{f}_OCR"] = ocr_val
            result_row[f"CRR_{f}"] = round(crr_val, 2) if crr_val is not None else ""
            
            # Print brief CRR
            print(f"     - {f}: {result_row[f'CRR_{f}']}% (GT: '{gt_val}' | OCR: '{ocr_val}')")

        results.append(result_row)

    # Save to CSV
    if results:
        with open(OUTPUT_CSV, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=results[0].keys())
            writer.writeheader()
            writer.writerows(results)
            
        print("\n" + "="*40)
        print(f"=== EVALUASI SELESAI ({len(results)} DATA) ===")
        print("="*40)
        
        # Calculate & Print Average CRR for each field
        for f in fields:
            valid_crr = [r[f"CRR_{f}"] for r in results if r[f"CRR_{f}"] != ""]
            avg_crr = sum(valid_crr) / len(valid_crr) if valid_crr else 0
            print(f"Rata-rata CRR {f:<15}: {avg_crr:.2f}%")
            
        print(f"\nHasil lengkap CSV tersimpan di: {OUTPUT_CSV}")

if __name__ == "__main__":
    run_hardware_evaluation()
