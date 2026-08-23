import csv
import time
import os

try:
    import board
    import busio
    import adafruit_mlx90614
except ImportError:
    print("[ERROR] Library sensor belum terinstall!")
    print("Jalankan: pip install adafruit-circuitpython-mlx90614")
    exit(1)

OUTPUT_CSV = "hasil_eval_suhu.csv"
NUM_TESTS = 30

def init_sensor():
    print("[INFO] Menginisialisasi sensor MLX90614...")
    try:
        i2c = busio.I2C(board.SCL, board.SDA)
        mlx = adafruit_mlx90614.MLX90614(i2c)
        print("[SUKSES] Sensor MLX90614 siap digunakan!")
        return mlx
    except Exception as e:
        print(f"[ERROR] Gagal menginisialisasi sensor: {e}")
        print("Pastikan kabel SDA/SCL terpasang dengan benar di Raspi.")
        exit(1)

def run_temperature_evaluation():
    mlx = init_sensor()
    results = []
    
    print("\n=======================================================")
    print(" PIPELINE EVALUASI SENSOR SUHU (MLX90614 vs THERMOGUN)")
    print("=======================================================\n")
    print("Instruksi:")
    print("1. Arahkan dahi/tangan ke sensor MLX90614 (jarak ~3-5 cm).")
    print("2. Tekan ENTER di laptop/Raspi Anda untuk membaca suhu.")
    print("3. Tembak dahi/tangan yang sama menggunakan Thermogun.")
    print("4. Masukkan angka dari Thermogun ke layar dan tekan ENTER.\n")

    for i in range(1, NUM_TESTS + 1):
        print("-" * 50)
        input(f"[PENGUJIAN {i}/{NUM_TESTS}] Posisikan target, lalu tekan [ENTER] untuk mengukur...")
        
        # Baca dari MLX90614
        try:
            mlx_suhu_obj = round(mlx.object_temperature, 2)
            mlx_suhu_amb = round(mlx.ambient_temperature, 2)
            print(f"  -> Terbaca dari MLX90614: {mlx_suhu_obj} °C (Ambient: {mlx_suhu_amb} °C)")
        except Exception as e:
            print(f"  -> [ERROR] Gagal membaca MLX90614: {e}")
            continue
            
        # Minta input Thermogun dari user
        while True:
            try:
                thermo_input = input("  -> Masukkan suhu dari Thermogun (contoh: 36.5): ").strip()
                # Ganti koma jadi titik kalau user salah ketik
                thermo_input = thermo_input.replace(',', '.') 
                thermo_suhu = float(thermo_input)
                break
            except ValueError:
                print("     [ERROR] Format salah! Masukkan angka seperti 36.5")
                
        # Hitung Error (Selisih)
        selisih = round(abs(mlx_suhu_obj - thermo_suhu), 2)
        print(f"  -> Selisih (Error)      : {selisih} °C")
        
        results.append({
            "No": i,
            "Suhu_Ambient_MLX": mlx_suhu_amb,
            "Suhu_Object_MLX": mlx_suhu_obj,
            "Suhu_Thermogun": thermo_suhu,
            "Error_Selisih": selisih
        })

    # Simpan ke CSV
    if results:
        file_exists = os.path.isfile(OUTPUT_CSV)
        with open(OUTPUT_CSV, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=results[0].keys())
            writer.writeheader()
            writer.writerows(results)
            
        # Hitung Rata-rata
        avg_error = sum(r["Error_Selisih"] for r in results) / len(results)
        
        print("\n" + "="*40)
        print(f"=== EVALUASI SUHU SELESAI ({len(results)} DATA) ===")
        print("="*40)
        print(f"Rata-rata Selisih Suhu (Error) : {avg_error:.2f} °C")
        
        if avg_error <= 0.5:
            print("Status Sensor: SANGAT BAIK (Akurasi Tinggi)")
        elif avg_error <= 1.0:
            print("Status Sensor: BAIK (Bisa Diterima)")
        else:
            print("Status Sensor: KURANG BAIK (Perlu Kalibrasi / Jarak terlalu jauh)")
            
        print(f"\nHasil lengkap tersimpan di: {OUTPUT_CSV}")

if __name__ == "__main__":
    run_temperature_evaluation()
