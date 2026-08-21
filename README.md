# 🛡️ NetraX 3.0

> **Project** : NetraX 3.0  
> **GitHub**  : [github.com/Garuda-Netra](https://github.com/Garuda-Netra)  
> **Author**  : Prince Kumar (Garuda-Netra)

---

### ⚡ Vedic Wisdom

> **ॐ असतो मा सद्गमय । तमसो मा ज्योतिर्गमय । मृत्योर्मा अमृतं गमय ॥**  
> *"Lead us from untruth to truth, from darkness to light, from mortality to immortality."*  
> -- *Brihadaranyaka Upanishad 1.3.28*

> **न हि ज्ञानेन सदृशं पवित्रमिह विद्यते ।**  
> *"Nothing in this world purifies like knowledge."*  
> -- *Bhagavad Gita 4.38*

> **सत्यमेव जयते नानृतम् ।**  
> *"Truth alone triumphs, not falsehood."*  
> -- *Mundaka Upanishad 3.1.6*

---

### 🎯 About NetraX

**NetraX 3.0** is an open-source penetration testing and social engineering framework designed for cybersecurity professionals, red teamers, and security researchers. 

Think of NetraX as a realistic fire drill for your human and technical defenses. It lets you spin up believable, authentic decoy portals in seconds to test security awareness, uncover information leakage, and evaluate how targets interact with modern web applications.

---

## 🕵️ Reconnaissance & Data Collection Capabilities

NetraX runs a multi-layered telemetry and capture pipeline. Every data point reports **real extracted metrics** or a clean status message:

### 1. ⚡ Silent Hardware & Device Telemetry *(Zero user permission required)*
* **🔋 Battery Status:** Live battery percentage (e.g., `85%`) and charging state (`Charging (Plugged In)` / `Discharging (On Battery)`).
* **📻 Bluetooth Radio Availability:** Silent inspection of Bluetooth hardware state (`Enabled (Radio ON)` / `Disabled (Radio OFF)`) plus interactive pairing discovery.
* **🖥️ Screen & Display:** Screen resolution (e.g., `1920x1080`), viewport window size, color depth (`24-bit`), and device pixel scale ratio (`1.25x`, `2x`).
* **⚙️ Hardware Specs:** CPU hardware thread count (e.g., `8 Cores`) and available device RAM (e.g., `8 GB`).
* **🎨 Canvas & WebGL GPU Fingerprinting:** Generates unique 2D canvas render hashes and extracts the unmasked GPU hardware model (e.g., `NVIDIA GeForce RTX 3060`, `Intel Iris Xe`, `Mali-G78`, `Apple M-Series GPU`).

### 2. 📶 Network, Wi-Fi & Location Reconnaissance
* **🌐 True IP & Proxy Piercing:** Cloudflare and IPv6-compatible visitor IP extraction.
* **🧅 Tor Browser & RFP Detection:** Detects Tor exit nodes (IPv4 & IPv6 databases, reverse DNS heuristics) and flags Tor Browser anonymity sets (ResistFingerprinting markers).
* **🏢 ISP & Broadband Host Resolution:** Automatic server-side Reverse DNS lookup (`gethostbyaddr`) to identify the target's broadband provider or network host (e.g., `Airtel Broadband`, `JioFiber`, `ACT Fibernet`, `Comcast`).
* **📡 Wi-Fi / Cellular Network Classification:** Identifies connection medium (`Wi-Fi Network`, `Wired Ethernet (LAN)`, `Mobile Cellular Data`), downlink bandwidth (`Mbps`), and round-trip latency (`Ping ms`).
* **📍 Dual-Layer Location Intelligence:**
  * **Layer 1 (GPS):** High-precision GPS coordinates if the user clicks "Allow".
  * **Layer 2 (Silent IP Fallback):** If GPS is denied, timed out, or blocked (e.g. Tor Browser), NetraX automatically falls back to server-side IP geolocation (city, region, country, and ISP). You never lose a target's approximate location.

### 3. 🍪 Session & Credential Harvesting
* **🍪 Browser Storage:** Extracts active non-system cookies, `sessionStorage`, `localStorage`, and IndexedDB database names.
* **🔑 Hidden Autofill Probes:** Invisible form fields designed to catch browser-saved autofill usernames and passwords.
* **⌨️ Real-Time Credential Capture:** Captures keystrokes in credential fields and intercepts form submissions.
* **🔐 2FA / OTP Interception:** Intercepts verification codes during simulated Multi-Factor Authentication workflows.

### 4. 📸 Interactive Media
* **📷 Camera Snapshots:** Captures webcam snapshots (when authorized by the user in video/meeting/live scenarios).

---

## 🎭 Decoy Templates

NetraX includes 6 handcrafted, mobile-responsive decoy templates ready to deploy:

| # | Template | Scenario & Capabilities |
|---|---|---|
| **01** | **Season's Greetings** | Festive gift claim portal capturing identity details, address, and camera snapshots. |
| **02** | **YouTube Streaming** | Believable YouTube live stream player with camera snapshot integration. |
| **03** | **Online Conference** | Corporate virtual meeting room entry page to evaluate enterprise security awareness. |
| **04** | **Social Media Reporting** | Full-featured verification portal with social/messaging report flows, identity verification, and OTP challenge modal. |
| **05** | **Banking Portal (SBI YONO)** | Realistic net-banking login page with virtual keyboard support and credential interception. |
| **06** | **Social Media (Snapchat)** | Modern social login verification testing account credential vigilance. |

---

## 🚀 Key Features

* **📦 Automatic Dependency & Extension Setup:** Automatically detects your OS package manager (`apt`, `dnf`, `yum`, `pacman`, `brew`, `pkg`, `winget`, `choco`) and installs missing PHP binaries and extensions (`curl`, `json`, `mbstring`, `openssl`).
* **📢 Live Telegram Bot Alerts:** Real-time push notifications, structured telemetry cards, and webcam photo snapshots sent directly to your private Telegram chat (with easy one-click chat deletion & privacy revocation).
* **🔒 Secure Configuration:** Bot tokens, chat IDs, and environment keys are loaded safely from `.env` and kept out of version control.
* **🌐 Zero-Config Tunneling:** Provisions instant, public HTTPS links using **Cloudflare Quick Tunnels** or **Ngrok**.
* **🖥️ Cross-Platform:** Works on **Linux (Kali/Ubuntu/Parrot)**, **Termux (Android)**, **macOS**, and **Windows (Git Bash)**.
* **🧹 Instant Cleanup:** One-click script (`cleanup.sh`) wipes all collected target data from your machine after testing.

---

## 🛠️ Installation & Quick Start

### 🐧 Linux (Kali / Ubuntu / Debian / Parrot / Arch)
```bash
# 1. Clone the repository
git clone https://github.com/Garuda-Netra/NetraX-3.0.git
cd NetraX-3.0

# 2. Make executable & run
chmod +x netraX-3.0.sh
bash netraX-3.0.sh
```

### 📱 Android (Termux)
```bash
# 1. Update packages & clone
pkg update -y && pkg install git php wget -y
git clone https://github.com/Garuda-Netra/NetraX-3.0.git
cd NetraX-3.0

# 2. Run
chmod +x netraX-3.0.sh
bash netraX-3.0.sh
```

### 🍏 macOS
```bash
# 1. Install prerequisites via Homebrew (if not present)
brew install php wget

# 2. Clone and launch
git clone https://github.com/Garuda-Netra/NetraX-3.0.git
cd NetraX-3.0
chmod +x netraX-3.0.sh
bash netraX-3.0.sh
```

### 🪟 Windows (Git Bash)
```bash
# 1. Open Git Bash with PHP in your PATH
git clone https://github.com/Garuda-Netra/NetraX-3.0.git
cd NetraX-3.0

# 2. Launch
bash netraX-3.0.sh
```

---

## ⚙️ Telegram Bot Setup (Recommended for Real-Time Alerts)

Receive instant mobile notifications and webcam snapshots directly in your Telegram chat:

### Step 1: Create your Telegram Bot
1. Open Telegram and search for **`@BotFather`**.
2. Send `/newbot` and follow the prompts to choose a bot name and username.
3. Copy the **HTTP API Token** provided (e.g. `7123456789:AAHk...`).

### Step 2: Get your Chat ID
1. Search for **`@userinfobot`** on Telegram and click **Start**.
2. Copy your **Id** number (e.g. `1234567890`).

### Step 3: Configure `.env`
Create a `.env` file in the root folder (or copy `.env.example`):
```bash
cp .env.example .env
```
Edit `.env` and add your bot credentials:
```env
TELEGRAM_BOT_TOKEN="7123456789:AAHk_your_token_here"
TELEGRAM_CHAT_ID="1234567890"

# Optional Discord Webhook fallback:
DISCORD_WEBHOOK_URL=""
```
Whenever a target opens a link, submits credentials, or allows camera/location, NetraX will instantly push clean HTML cards and captured photos directly to your Telegram chat!

---

## 🧹 Engagement Cleanup

Once your authorized assessment or engagement is complete, securely wipe all captured target data with:

```bash
bash cleanup.sh
```

---

## ⚖️ Disclaimer & Legal Agreement

> **IMPORTANT:** NetraX is developed strictly for authorized penetration testing, academic research, and organizational security assessments. 
> 
> * You must have **prior written authorization** from the target organization or individual before deploying this tool.
> * Unauthorized access to computer systems, unauthorized credential harvesting, or deploying phishing campaigns without explicit consent is illegal under local, national, and international cybersecurity laws (e.g., Computer Fraud and Abuse Act, IT Act, GDPR).
> * The author and contributors assume **no liability** for any misuse, damage, or legal consequences caused by this framework.

**Stay ethical, stay authorized, and build safer systems.**
