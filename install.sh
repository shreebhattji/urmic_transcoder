sudo apt update
sudo apt install -y apache2 php libapache2-mod-php vainfo ufw intel-media-va-driver-non-free libavcodec-extra mesa-utils i965-va-driver libmfx1 intel-gpu-tools ffmpeg v4l-utils python3-pip mpv alsa-utils vlan git zlib1g-dev php-zip php-curl numactl
sudo pip3 install psutil --break-system-packages

cat > /etc/sudoers.d/www-data << 'EOL'
www-data     ALL=(ALL) NOPASSWD: ALL
EOL

# graph monitor setup
cat > /etc/systemd/system/system-monitor.service<< 'EOL'
[Unit]
Description=Lightweight System Monitor Sampler by ShreeBhattJi
After=network.target

[Service]
Type=simple
ExecStart=/usr/bin/python3 /usr/local/bin/nginx_system_monitor_sampler.py
Restart=always
RestartSec=2
User=root
StandardOutput=null
StandardError=null

[Install]
WantedBy=multi-user.target
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
StandardOutput=null
StandardError=null

[Install]
WantedBy=multi-user.target
EOL

# Disable system logging for systemd journald and apache2
sudo mkdir -p /etc/systemd/journald.conf.d
cat > /etc/systemd/journald.conf.d/00-disable-logs.conf << 'EOL'
[Journal]
Storage=none
ForwardToSyslog=no
ForwardToKMsg=no
ForwardToConsole=no
ForwardToWall=no
MaxLevelStore=emerg
MaxLevelSyslog=emerg
MaxLevelKMsg=emerg
MaxLevelConsole=emerg
MaxLevelWall=emerg
EOL

sudo systemctl restart systemd-journald 2>/dev/null || true
sudo a2disconf other-vhosts-access-log 2>/dev/null || true
sudo sed -i 's|ErrorLog .*|ErrorLog /dev/null|g' /etc/apache2/apache2.conf 2>/dev/null || true
sudo sed -i 's|CustomLog .*|CustomLog /dev/null combined|g' /etc/apache2/apache2.conf 2>/dev/null || true
sudo sed -i 's|ErrorLog .*|ErrorLog /dev/null|g' /etc/apache2/sites-available/*.conf 2>/dev/null || true
sudo sed -i 's|CustomLog .*|CustomLog /dev/null combined|g' /etc/apache2/sites-available/*.conf 2>/dev/null || true

sudo mkdir /var/www/encoder
cp nginx_system_monitor_sampler.py /usr/local/bin/nginx_system_monitor_sampler.py
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
sudo systemctl daemon-reload
sudo systemctl enable --now system-monitor.service
sudo systemctl restart --now system-monitor.service


DEVICE_ID="$(sudo cat /sys/class/dmi/id/product_uuid | tr -d '\n')"
sudo sed -i 's/certificatecertificatecertificatecertificate/'$DEVICE_ID'/g' /var/www/html/certification.html

sudo chmod 777 -R /var/www
sudo chown -R www-data:www-data /var/www
sudo systemctl daemon-reload


sudo tee /etc/sysctl.d/99-streaming.conf >/dev/null <<'EOF'
net.core.rmem_default=16777216
net.core.wmem_default=16777216
net.core.rmem_max=67108864
net.core.wmem_max=67108864
net.core.optmem_max=25165824

net.ipv4.udp_rmem_min=131072
net.ipv4.udp_wmem_min=131072

net.core.netdev_max_backlog=300000
EOF