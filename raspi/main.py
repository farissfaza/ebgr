import time
import requests
import json
import os
import cv2
import threading
from flask import Flask, Response

import board
import busio
import digitalio
from adafruit_pn532.spi import PN532_SPI
import adafruit_mlx90614

import config

# --- GLOBALS ---
pn532 = None
mlx = None
camera = None
camera_lock = threading.Lock()
current_frame = None

app = Flask(__name__)

def camera_thread_func():
    global current_frame
    while True:
        if camera is not None:
            ret, frame = camera.read()
            if ret:
                with camera_lock:
                    current_frame = frame.copy()
            else:
                time.sleep(0.1)
        else:
            time.sleep(1)

def generate_frames():
    while True:
        with camera_lock:
            frame = current_frame
            
        if frame is None:
            time.sleep(0.1)
            continue
            
        ret, buffer = cv2.imencode('.jpg', frame)
        if not ret:
            continue
            
        frame_bytes = buffer.tobytes()
        yield (b'--frame\r\n'
               b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')
        
        time.sleep(0.05) # Cap at ~20 FPS to save CPU

@app.route('/video_feed')
def video_feed():
    return Response(generate_frames(), mimetype='multipart/x-mixed-replace; boundary=frame')

def init_hardware():
    global pn532, mlx, camera
    print("Initializing hardware...")
    
    # 1. Init PN532 (SPI)
    try:
        spi = busio.SPI(board.SCLK, board.MOSI, board.MISO)
        cs_pin = digitalio.DigitalInOut(board.D8) # Changed to GPIO 8 (Physical Pin 24)
        pn532 = PN532_SPI(spi, cs_pin, debug=False)
        pn532.SAM_configuration()
        print("PN532 initialized.")
    except Exception as e:
        print(f"Error initializing PN532: {e}")
        pn532 = None

    # 2. Init MLX90614 (I2C)
    try:
        i2c = busio.I2C(board.SCL, board.SDA)
        mlx = adafruit_mlx90614.MLX90614(i2c)
        print("MLX90614 initialized.")
    except Exception as e:
        print(f"Error initializing MLX90614: {e}")
        mlx = None

    # 3. Init Camera
    try:
        camera = cv2.VideoCapture(0)
        if camera.isOpened():
            # Set HD resolution for better OCR quality
            camera.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
            camera.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)
            actual_w = int(camera.get(cv2.CAP_PROP_FRAME_WIDTH))
            actual_h = int(camera.get(cv2.CAP_PROP_FRAME_HEIGHT))
            print(f"Camera initialized at {actual_w}x{actual_h}.")
            # Start the background camera reading thread
            threading.Thread(target=camera_thread_func, daemon=True).start()
        else:
            print("Failed to open camera.")
            camera = None
    except Exception as e:
        print(f"Error initializing Camera: {e}")
        camera = None

def send_health_check():
    while True:
        status = [
            {
                "component": "pn532",
                "status": "connected" if pn532 else "error"
            },
            {
                "component": "mlx90614",
                "status": "connected" if mlx else "error"
            },
            {
                "component": "camera",
                "status": "connected" if camera else "error"
            }
        ]
        
        try:
            url = f"{config.SERVER_URL}/api/raspi/health_check.php"
            requests.post(url, json=status, timeout=5)
            print("Health check sent.")
        except Exception as e:
            print(f"Failed to send health check: {e}")
            
        time.sleep(config.HEALTH_CHECK_INTERVAL)

def capture_photo(task_token, folder, checkin_session):
    if not camera:
        report_task_complete(task_token, "failed", {"error": "Camera not available"})
        return
        
    print(f"Capturing photo for {folder}...")
    
    with camera_lock:
        frame = current_frame
        
    if frame is None:
        report_task_complete(task_token, "failed", {"error": "Camera frame empty or not ready"})
        return
        
    # Save temporarily with high quality
    filename = f"/tmp/{task_token}.jpg"
    cv2.imwrite(filename, frame, [cv2.IMWRITE_JPEG_QUALITY, 95])
    
    # Send temperature reading simultaneously if it's a KTP scan
    if folder == "ocr-ktp" and mlx and checkin_session:
        try:
            temp = mlx.object_temperature
            requests.post(
                f"{config.SERVER_URL}/api/raspi/temperature.php",
                data={"checkin_session": checkin_session, "temperature": temp},
                timeout=5
            )
            print(f"Recorded temperature: {temp} C")
        except Exception as e:
            print(f"Error reading/sending temperature: {e}")
    
    # Upload to S3 via PHP API
    try:
        url = f"{config.SERVER_URL}/api/raspi/upload_to_s3.php"
        with open(filename, 'rb') as f:
            files = {'file': (f"{task_token}.jpg", f, 'image/jpeg')}
            data = {'folder': folder}
            response = requests.post(url, files=files, data=data)
            
        result = response.json()
        if result.get("success"):
            s3_path = result.get("s3_key")
            report_task_complete(task_token, "completed", {}, s3_path=s3_path)
            print(f"Photo uploaded successfully to {s3_path}")
        else:
            report_task_complete(task_token, "failed", {"error": result.get("message")})
            print(f"Failed to upload photo: {result.get('message')}")
            
    except Exception as e:
        report_task_complete(task_token, "failed", {"error": str(e)})
        print(f"Error uploading photo: {e}")
        
    # Cleanup
    if os.path.exists(filename):
        os.remove(filename)

def scan_rfid(task_token):
    global pn532
    if not pn532:
        report_task_complete(task_token, "failed", {"error": "PN532 not available"})
        return
        
    print("Waiting for RFID card...")
    
    # Wake up the PN532 before scanning (in case it went to sleep)
    try:
        pn532.SAM_configuration()
    except Exception as e:
        print(f"Warning: Failed to configure SAM before scan: {e}")

    start_time = time.time()
    
    # Timeout after 30 seconds
    while time.time() - start_time < 30:
        try:
            uid = pn532.read_passive_target(timeout=0.5)
            if uid is not None:
                uid_str = "".join([hex(i)[2:].zfill(2) for i in uid]).upper()
                print(f"Found card with UID: {uid_str}")
                report_task_complete(task_token, "completed", {"uid": uid_str})
                return
        except RuntimeError as e:
            print(f"SPI read error: {e}. Re-initializing PN532...")
            try:
                # Force re-initialize to wake it up from crash state
                import board, busio, digitalio
                from adafruit_pn532.spi import PN532_SPI
                
                spi = busio.SPI(board.SCLK, board.MOSI, board.MISO)
                cs_pin = digitalio.DigitalInOut(board.D8)
                pn532 = PN532_SPI(spi, cs_pin, debug=False)
                pn532.SAM_configuration()
            except Exception as reset_e:
                print(f"Failed to reset PN532: {reset_e}")
            time.sleep(0.5)
            
    print("RFID scan timed out.")
    report_task_complete(task_token, "failed", {"error": "Timeout waiting for card"})

def report_task_complete(task_token, status, result_data, s3_path=None):
    url = f"{config.SERVER_URL}/api/raspi/complete_task.php"
    data = {
        "task_token": task_token,
        "status": status,
        "result_data": json.dumps(result_data)
    }
    if s3_path:
        data["s3_path"] = s3_path
        
    try:
        requests.post(url, data=data, timeout=5)
    except Exception as e:
        print(f"Failed to report task completion: {e}")

def main_loop():
    url = f"{config.SERVER_URL}/api/raspi/poll_tasks.php"
    
    print("Starting polling loop...")
    while True:
        try:
            response = requests.get(url, timeout=5)
            data = response.json()
            
            if data.get("has_task"):
                task_token = data.get("task_token")
                task_type = data.get("task_type")
                checkin_session = data.get("checkin_session")
                
                print(f"Received task: {task_type} (Token: {task_token})")
                
                if task_type == "rfid_scan":
                    scan_rfid(task_token)
                elif task_type == "camera_ktp":
                    capture_photo(task_token, "ocr-ktp", checkin_session)
                elif task_type == "camera_payment":
                    capture_photo(task_token, "bukti-pembayaran", checkin_session)
                else:
                    print(f"Unknown task type: {task_type}")
                    report_task_complete(task_token, "failed", {"error": "Unknown task type"})
                    
        except requests.exceptions.RequestException:
            # Silently handle connection errors during polling to avoid spamming the console
            pass
        except Exception as e:
            print(f"Error polling tasks: {e}")
            
        time.sleep(config.POLL_INTERVAL)

if __name__ == "__main__":
    init_hardware()
    
    # Start health check thread
    health_thread = threading.Thread(target=send_health_check, daemon=True)
    health_thread.start()
    
    # Start Flask server thread for camera stream
    flask_thread = threading.Thread(target=lambda: app.run(host='0.0.0.0', port=5000, debug=False, use_reloader=False), daemon=True)
    flask_thread.start()
    
    # Run main polling loop
    main_loop()
