# 🛡️ NETRA-X 3.0

> **Project** : NETRA-X 3.0
> **GitHub**  : [github.com/Garuda-Netra](https://github.com/Garuda-Netra)
> **Creator** : Raj Kumar

---

### ⚡ Vedic Wisdom ⚡

> **ॐ असतो मा सद्गमय । तमसो मा ज्योतिर्गमय । मृत्योर्मा अमृतं गमय ॥**
> *"Lead us from untruth to truth, from darkness to light, from mortality to immortality."*
> --Brihadaranyaka Upanishad 1.3.28

> **न हि ज्ञानेन सदृशं पवित्रमिह विद्यते ।**
> *"Nothing in this world purifies like knowledge."*
> --Bhagavad Gita 4.38

> **सत्यमेव जयते नानृतम् ।**
> *"Truth alone triumphs, not falsehood."*
> --Mundaka Upanishad 3.1.6

---

### *Your Friendly Neighborhood Penetration Testing & Social Engineering Framework*

**⚠️ Legal Notice:** Please play nice! This tool is strictly for authorized penetration testing. You absolutely must have written consent before using it. Unauthorized access to computer systems is illegal and definitely not cool.

---

## 👋 What is NetraX?

Hey there! Welcome to **NetraX 3.0**. 

If you're a security professional, penetration tester, or ethical hacker, NetraX is built just for you. Think of it as a realistic fire drill for your organization's digital defenses. It lets you quickly spin up incredibly convincing decoy pages to see what critical information your targets might inadvertently hand over. 

We’ve packed it with engaging, true-to-life templates—from a festive gift claim portal to a fully simulated banking login.

### 🕵️ What Kind of Data Can It Collect?
When your authorized targets interact with a NetraX decoy page, the toolkit quietly goes to work:

- **Device Fingerprinting:** Captures the real IP address, browser type, operating system, and whether they're on mobile or desktop.
- **Location Tracking:** If they click "Allow", it grabs exact GPS coordinates. If they deny or ignore it? **NetraX falls back to a silent IP-based location lookup** so you still get their approximate city, region, and ISP.
- **Camera Snapshots:** Grabs a picture from their front camera (only if they explicitly grant permission).
- **Form Submissions:** Safely logs names, emails, phone numbers, and any credentials they type in.
- **OTP Codes:** Intercepts one-time passwords during simulated multi-factor authentication (MFA) tests.
- **Cookies & Session IDs:** Harvests accessible browser cookies, sessionStorage, and localStorage data.
- **Stored Credentials:** Tricks the browser's autofill into revealing saved usernames and passwords through hidden probe fields.

Everything is neatly organized, timestamped, saved locally in your `data/` folder, printed live to your terminal, and **instantly forwarded to your Discord channel** so you never miss a beat.

---

## 🚀 Why You'll Love It

✅ **Smart IP Detection:** Effortlessly pierces through proxies to reveal the true IP (fully Cloudflare & IPv6 aware).
✅ **Silent Location Fallback:** Never lose a target because they clicked "Block". NetraX automatically resolves their city/region via their IP address.
✅ **Auto-Dependency & Extension Installation:** NetraX dynamically detects missing PHP binaries or PHP extensions (`curl`, `json`, `mbstring`, `openssl`) and automatically downloads and installs them using your system's package manager.
✅ **Secure Webhook Configuration:** We respect your operational security. Discord webhook URLs are safely loaded from a `.env` file and excluded from version control.
✅ **Interactive Captures:** Seamlessly requests camera and GPS access just like real-world web applications.
✅ **Free Tunneling:** Automatically provisions public links using Cloudflare Quick Tunnels or Ngrok.
✅ **Cross-Platform Compatibility:** Runs beautifully on Linux, Termux, macOS, and Windows.
✅ **Easy Cleanup:** A single command securely wipes all collected data once your engagement concludes.

---

## 🎭 Our Decoy Templates

We've crafted several realistic scenarios ready to deploy right out of the box:

| Template | What it does |
|----------|--------------|
| **Season's Greetings** | A festive gift claim form designed to request personal details. |
| **YouTube Streaming** | A convincingly fake live stream player. |
| **Online Conference** | A virtual meeting room entry page to test corporate awareness. |
| **Social Media Reporting** | A sophisticated portal mimicking account verification, capturing credentials and OTPs for Facebook, Instagram, WhatsApp, and Telegram. |
| **Banking Portal** | A simulated net banking login page (e.g., SBI YONO) to evaluate financial vigilance. |
| **Social Media** | A simulated login page for platforms like Snapchat to test user awareness regarding their social accounts. |

---

## 🛠️ Let's Get Started (Installation)

You don't need much to run NetraX—just PHP, `wget`, and bash! Here's how to get it running on your favorite system:

### 🐧 Linux / Kali / Ubuntu / Parrot
```bash
# 1. Grab the required packages
sudo apt update
sudo apt install php wget -y

# 2. Clone the repository
git clone https://github.com/Garuda-Netra/NetraX-3.0
cd NetraX-3.0

# 3. Make it executable and run!
chmod +x netraX-3.0.sh
bash netraX-3.0.sh
```

### 📱 Termux (Android)
```bash
# 1. Update and install packages
pkg update -y
pkg install php wget git -y

# 2. Clone and run
git clone https://github.com/Garuda-Netra/NetraX-3.0
cd NetraX-3.0
bash netraX-3.0.sh
```

### 🍏 macOS
```bash
# 1. Install dependencies using Homebrew
brew install php wget

# 2. Clone and run
git clone https://github.com/Garuda-Netra/NetraX-3.0
cd NetraX-3.0
bash netraX-3.0.sh
```

### 🪟 Windows (Git Bash)
```bash
# 1. Make sure you have Git Bash and PHP installed and added to your system PATH.

# 2. Clone and run right from Git Bash
git clone https://github.com/Garuda-Netra/NetraX-3.0
cd NetraX-3.0
bash netraX-3.0.sh
```

---

## 🎮 How It Works in Practice

Using NetraX is super straightforward. The interactive menu will guide you through the entire process:

1. **Fire it up:** Run the script, and it instantly starts a local PHP server.
2. **Setup Discord:** Ensure your `.env` file has your `DISCORD_WEBHOOK_URL` set so you get live alerts.
3. **Choose your tunnel:** Select either Cloudflare Quick Tunnel or Ngrok.
4. **Pick a template:** Choose the scenario you want to test with.
5. **Share the link:** The script generates a live, public URL for you to send to your authorized target.
6. **Watch the data roll in:** As they interact with the page, check your terminal, Discord, or the `data/` folder for logs, coordinates, and captures.

---

## 🧹 Cleaning Up

When your test is over and you need to securely wipe all the collected data, simply run:

```bash
bash cleanup.sh
```

This will instantly clear out everything in the `data/` folder so you're ready for your next engagement.

---

## ⚖️ A Final Word on Ethics

⚠️ **Important Reminder:** We built this tool to help security professionals protect people, not harm them. 

Before you share a link with anyone, make sure you have:
- Written, explicit permission from the organization or individual you are testing.
- A formal testing agreement.
- A clear understanding of what is and isn't allowed in your scope.

Using this tool without permission is illegal and unethical. Stay safe, stay legal, and happy testing!
