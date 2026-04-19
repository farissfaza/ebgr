#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <Adafruit_PN532.h>

// Pin Configuration
#define SDA_PIN 21
#define SCL_PIN 22

// WiFi Configuration
const char* ssid = "UPDL BOGOR";
const char* password = "bogorkahiji";

// Server Configuration
const char* serverUrl = "localhost/ebgr/input_tamu/rfid_process.php";

// Initialize PN532
Adafruit_PN532 nfc(SDA_PIN, SCL_PIN);

void setup() {
  Serial.begin(115200);
  Serial.println("Initializing RFID Reader...");
  
  // Connect to WiFi
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi Connected!");
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());
  
  // Initialize PN532
  nfc.begin();
  uint32_t versiondata = nfc.getFirmwareVersion();
  
  if (!versiondata) {
    Serial.println("PN532 not found!");
    while (1);
  }
  
  Serial.print("Found PN532 with firmware version: ");
  Serial.println(versiondata, HEX);
  
  // Configure to read RFID tags
  nfc.SAMConfig();
  Serial.println("Waiting for e-KTP...");
}

void loop() {
  uint8_t uid[] = {0, 0, 0, 0, 0, 0, 0};
  uint8_t uidLength;
  
  // Check for card
  if (nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength)) {
    Serial.println("e-KTP Detected!");
    
    // Convert UID to string
    String cardUID = "";
    for (uint8_t i = 0; i < uidLength; i++) {
      if (uid[i] < 0x10) cardUID += "0";
      cardUID += String(uid[i], HEX);
    }
    cardUID.toUpperCase();
    
    Serial.print("UID: ");
    Serial.println(cardUID);
    
    // Send to server
    sendToServer(cardUID);
    
    // Wait before next read
    delay(3000);
  }
  
  delay(100);
}

void sendToServer(String uid) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    
    String postData = "uid=" + uid + "&action=lookup";
    int httpCode = http.POST(postData);
    
    if (httpCode > 0) {
      String response = http.getString();
      Serial.println("Server Response: " + response);
    } else {
      Serial.println("HTTP Error: " + String(httpCode));
    }
    
    http.end();
  }
}