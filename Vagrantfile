
Vagrant.configure(2) do |config|
if Vagrant.has_plugin? "vagrant-vbguest"
    config.vbguest.no_install  = true
    config.vbguest.auto_update = false
    config.vbguest.no_remote   = true
  end

  config.vm.box = "ubuntu/bionic64"
  config.vm.network "forwarded_port", guest: 80, host: 8081, auto_correct: true
  config.vm.synced_folder "./", "/var/www/html"

  config.vm.provision "shell", inline: <<-SHELL
    sudo su

    apt-get install software-properties-common
    add-apt-repository ppa:ondrej/php
    add-apt-repository ppa:ondrej/apache2

    apt-get update

    apt-get install apache2 -y
    apt-get install memcached -y
    apt-get install php7.4 php7.4-mbstring php7.4-zip php7.4-xml php7.4-curl php7.4-gd php7.4-mysql libapache2-mod-php7.4 php-memcached -y

    a2enmod rewrite
    a2enmod ssl

    apt-get install composer -y

    apt install git -y
    apt-get install imagemagick -y
    apt-get install php-imagick -y

    rm /etc/apache2/sites-available/000-default.conf

    cat <<EOF >> /etc/apache2/sites-available/000-default.conf
    <VirtualHost *:80>
        ServerAdmin webmaster@localhost
        DocumentRoot /var/www/html/
        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined
        <Directory /var/www/html/public/>
            Options Indexes FollowSymLinks MultiViews
            AllowOverride All
            Order allow,deny
            allow from all
        </Directory>
    </VirtualHost>
EOF

    service apache2 restart

    cd /var/www/html/
    composer install

    apt-get update
    apt-get upgrade -y
    apt-get autoremove -y
  SHELL
end
