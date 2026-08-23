# API Configuration
SERVER_URL = "http://YOUR_XAMPP_IP:8000" # Change to your PC's IP address (e.g., http://192.168.1.100:8000)

# Hardware Pins (as requested)
# PN532 (SPI)
PN532_CS = 24   # SS/CS: PIN 24 (GPIO 8 / CE0) - Blue 2 / White
PN532_MOSI = 19 # MOSI: PIN 19 (GPIO 10) - White / Green
PN532_MISO = 21 # MISO: PIN 21 (GPIO 9) - Gray / Black
PN532_SCLK = 23 # SCK: PIN 23 (GPIO 11) - Purple 2 / Red

# MLX90614 (I2C)
# SDA: PIN 3 (GPIO 2) - Yellow / Green
# SCL: PIN 5 (GPIO 3) - Orange / White

# Timers
POLL_INTERVAL = 2.0
HEALTH_CHECK_INTERVAL = 300 # 5 minutes
