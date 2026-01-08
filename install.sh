sudo apt update
sudo apt install -y apache2 php libapache2-mod-php vainfo ufw intel-media-va-driver-non-free libavcodec-extra mesa-utils i965-va-driver libmfx1 intel-gpu-tools ffmpeg v4l-utils python3-pip mpv alsa-utils vlan git zlib1g-dev php-zip php-curl
sudo pip3 install psutil --break-system-packages

cat > /etc/sudoers.d/www-data << 'EOL'
www-data     ALL=(ALL) NOPASSWD: ALL
EOL


cat > /usr/local/bin/nginx_system_monitor_sampler.py<< 'EOL'
#!/usr/bin/env python3
"""
Lightweight sampler for nginx static frontend.
"""

import time, json, os
from collections import deque
from datetime import datetime
import psutil

OUT_FILE = "/var/www/encoder/metrics.json"
TMP_FILE = OUT_FILE + ".tmp"
SAMPLE_INTERVAL = 10.0               # seconds between samples
HISTORY_SECONDS = 15 * 60           # 15 minutes
MAX_SAMPLES = int(HISTORY_SECONDS / SAMPLE_INTERVAL)

# circular buffers
timestamps = deque(maxlen=MAX_SAMPLES)
cpu_hist = deque(maxlen=MAX_SAMPLES)
ram_hist = deque(maxlen=MAX_SAMPLES)
net_in_hist = deque(maxlen=MAX_SAMPLES)
net_out_hist = deque(maxlen=MAX_SAMPLES)
disk_read_hist = deque(maxlen=MAX_SAMPLES)
disk_write_hist = deque(maxlen=MAX_SAMPLES)
disk_percent_hist = deque(maxlen=MAX_SAMPLES)

_prev_net = psutil.net_io_counters()
_prev_disk = psutil.disk_io_counters()
_prev_time = time.time()

def sample_once():
    global _prev_net, _prev_disk, _prev_time
    now = time.time()
    iso = datetime.fromtimestamp(now).isoformat(timespec='seconds')
    cpu = psutil.cpu_percent(interval=None)
    ram = psutil.virtual_memory().percent

    net = psutil.net_io_counters()
    disk = psutil.disk_io_counters()
    try:
        disk_percent = psutil.disk_usage("/").percent
    except Exception:
        disk_percent = 0.0

    elapsed = now - _prev_time if _prev_time else SAMPLE_INTERVAL
    if elapsed <= 0:
        elapsed = SAMPLE_INTERVAL

    in_rate = int(((net.bytes_recv - _prev_net.bytes_recv) / elapsed) * 8)
    out_rate = int(((net.bytes_sent - _prev_net.bytes_sent) / elapsed) * 8)

    read_rate = (disk.read_bytes - _prev_disk.read_bytes) / elapsed
    write_rate = (disk.write_bytes - _prev_disk.write_bytes) / elapsed

    timestamps.append(iso)
    cpu_hist.append(round(cpu, 2))
    ram_hist.append(round(ram, 2))
    net_in_hist.append(int(in_rate))
    net_out_hist.append(int(out_rate))
    disk_read_hist.append(int(read_rate))
    disk_write_hist.append(int(write_rate))
    disk_percent_hist.append(round(disk_percent, 2))

    _prev_net = net
    _prev_disk = disk
    _prev_time = now

def write_json_atomic():
    payload = {
        "timestamps": list(timestamps),
        "cpu_percent": list(cpu_hist),
        "ram_percent": list(ram_hist),
        "net_in_Bps": list(net_in_hist),
        "net_out_Bps": list(net_out_hist),
        "disk_read_Bps": list(disk_read_hist),
        "disk_write_Bps": list(disk_write_hist),
        "disk_percent": list(disk_percent_hist),
        "sample_interval": SAMPLE_INTERVAL,
        "generated_at": datetime.utcnow().isoformat(timespec='seconds') + "Z"
    }
    with open(TMP_FILE, "w") as f:
        json.dump(payload, f)
    os.replace(TMP_FILE, OUT_FILE)

def main():
    global _prev_net, _prev_disk, _prev_time
    _prev_net = psutil.net_io_counters()
    _prev_disk = psutil.disk_io_counters()
    _prev_time = time.time()
    time.sleep(0.2)  # warm-up

    while True:
        try:
            sample_once()
            write_json_atomic()
        except Exception as e:
            # systemd journal will capture prints
            print("Sampler error:", e)
        time.sleep(SAMPLE_INTERVAL)

if __name__ == "__main__":
    main()
EOL


cat >/etc/netplan/00-stream.yaml<< 'EOL'
network:
  version: 2
  renderer: networkd
  ethernets:
    eth:
      match:
        name: enx*
      addresses:
      - 172.16.111.111/24
EOL

cat >/etc/systemd/system/encoder@.service<< 'EOL'
[Unit]
Description=Encoder Instance %i
After=network.target

[Service]
Type=simple
User=root
ExecStart=/bin/bash /var/www/encoder/%i.sh
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOL

sudo mkdir /var/www/encoder
sudo cp -r html/* /var/www/html/
sudo cp backup_private.pem /var/www/
sudo cp backup_public.pem /var/www/
sudo cp 00-stream.yaml /var/www/
sudo cp attempts.json /var/www/
sudo cp users.json /var/www/

sudo a2enmod ssl
sudo systemctl enable apache2
sudo systemctl restart apache2
sudo a2ensite default-ssl
sudo chmod +x /usr/local/bin/nginx_system_monitor_sampler.py

sudo systemctl daemon-reload

sudo chmod 777 -R /var/www
sudo chown -R www-data:www-data /var/www
sudo systemctl daemon-reload

sudo chmod 444 /sys/class/dmi/id/product_uuid
sudo systemctl disable systemd-networkd-wait-online.service
sudo systemctl mask systemd-networkd-wait-online.service

sudo ufw default allow outgoing
sudo ufw default deny incoming
sudo ufw allow 80
sudo ufw allow 443
sudo ufw allow proto udp to 224.0.0.0/4
sudo ufw route allow proto udp to 224.0.0.0/4
sudo ufw deny out to 239.255.254.254 port 39000 proto udp
sudo ufw allow from 172.16.111.112 to 172.16.111.111 port 80
sudo ufw allow from 172.16.111.112 to 172.16.111.111 port 443
sudo ufw --force enable
DEVICE_ID="$(sudo cat /sys/class/dmi/id/product_uuid | tr -d '\n')"
sudo sed -i 's/certificatecertificatecertificatecertificate/'$DEVICE_ID'/g' /var/www/html/certification.html

sudo chmod 777 -R /var/www
sudo chown -R www-data:www-data /var/www
sudo systemctl daemon-reload
