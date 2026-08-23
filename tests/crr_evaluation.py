import csv
import os
import requests

try:
    import Levenshtein
except ImportError:
    Levenshtein = None

# =====================================================================
# CONFIGURATION
# =====================================================================
# Change this URL to point to your actual local server endpoint
# Make sure your XAMPP/Laragon is running!
OCR_API_URL = "http://localhost/ebgr/services/ocr_vision.php" 

GROUND_TRUTH_CSV = "dummy_ktp_data.csv"
IMAGES_DIR = "images"
OUTPUT_CSV = "hasil_crr.csv"

# =====================================================================
# 1. HITUNG CRR (Character Recognition Rate)
# =====================================================================
def calculate_crr(ground_truth, ocr_result):
    """CRR = (1 - (S+D+I)/N) x 100%, dengan S+D+I = Levenshtein distance."""
    if Levenshtein is None:
        raise RuntimeError("python-Levenshtein belum terinstall. Jalankan: pip install python-Levenshtein")

    if not ground_truth:
        return None
    if not ocr_result:
        return 0.0

    errors = Levenshtein.distance(ground_truth.upper().strip(), ocr_result.upper().strip())
    n = len(ground_truth)
    if n == 0:
        return 0.0
        
    crr = (1 - errors / n) * 100
    return max(crr, 0.0)  # jangan sampai minus kalau hasil OCR jauh berbeda

# =====================================================================
# 2. PIPELINE UTAMA (Integrasi dengan Backend E-BGR)
# =====================================================================
def run_evaluation():
    if not os.path.exists(GROUND_TRUTH_CSV):
        print(f"File {GROUND_TRUTH_CSV} tidak ditemukan!")
        return

    results = []

    with open(GROUND_TRUTH_CSV, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        rows = list(reader)

    print(f"Memulai evaluasi OCR ke endpoint: {OCR_API_URL}")
    print("-" * 50)

    for row in rows:
        no = row.get("No") or row.get("no")
        image_path = os.path.join(IMAGES_DIR, f"ktp_{no}.jpg")

        if not os.path.exists(image_path):
            print(f"[SKIP] Gambar tidak ditemukan: {image_path}")
            continue

        print(f"[PROSES] Menganalisis KTP No {no}...")
        
        # Kirim gambar ke API PHP lokal kita menggunakan HTTP POST (multipart/form-data)
        try:
            with open(image_path, 'rb') as img_file:
                files = {'identitas': (f"ktp_{no}.jpg", img_file, 'image/jpeg')}
                response = requests.post(OCR_API_URL, files=files)
                response_data = response.json()
        except Exception as e:
            print(f"  -> Error koneksi ke API: {e}")
            continue

        # Ekstrak data dari response JSON PHP
        if response_data.get('success'):
            data = response_data.get('data', {})
            ocr_nik = data.get('nik', '')
            ocr_nama = data.get('nama', '')
        else:
            print(f"  -> API gagal membaca KTP: {response_data.get('message')}")
            ocr_nik = ""
            ocr_nama = ""

        # Hitung CRR
        crr_nik = calculate_crr(row.get("NIK", ""), ocr_nik)
        crr_nama = calculate_crr(row.get("Nama", ""), ocr_nama)

        results.append({
            "no": no,
            "nik_ground_truth": row.get("NIK", ""),
            "nik_ocr": ocr_nik,
            "crr_nik": round(crr_nik, 2) if crr_nik is not None else "",
            "nama_ground_truth": row.get("Nama", ""),
            "nama_ocr": ocr_nama,
            "crr_nama": round(crr_nama, 2) if crr_nama is not None else "",
        })

    # Simpan hasil ke CSV
    if results:
        with open(OUTPUT_CSV, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=results[0].keys())
            writer.writeheader()
            writer.writerows(results)

        valid_crr_nik = [r["crr_nik"] for r in results if r["crr_nik"] != ""]
        valid_crr_nama = [r["crr_nama"] for r in results if r["crr_nama"] != ""]
        
        avg_crr_nik = sum(valid_crr_nik) / len(valid_crr_nik) if valid_crr_nik else 0
        avg_crr_nama = sum(valid_crr_nama) / len(valid_crr_nama) if valid_crr_nama else 0

        print("\n=== RINGKASAN HASIL ===")
        print(f"Jumlah sampel diproses : {len(results)}")
        print(f"Rata-rata CRR NIK      : {avg_crr_nik:.2f}%")
        print(f"Rata-rata CRR Nama     : {avg_crr_nama:.2f}%")
        print(f"Hasil detail disimpan  : {OUTPUT_CSV}")
    else:
        print("Tidak ada hasil untuk disimpan.")

if __name__ == "__main__":
    run_evaluation()
