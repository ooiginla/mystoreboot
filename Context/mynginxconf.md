server {
	listen 80;
	listen [::]:80;
        listen [::]:443 ssl ipv6only=on; # managed by Certbot
        listen 443 ssl; # managed by Certbot
	
	server_name storeboot.com www.storeboot.com *.storeboot.com;
	
        root /var/www/storeboot.com/html/public;

        index index.php;

        add_header X-Frame-Options "SAMEORIGIN";
        add_header X-Content-Type-Options "nosniff";
 
        charset utf-8;
 
        location / {
           try_files $uri $uri/ /index.php?$query_string;
        }
 
        location = /favicon.ico { access_log off; log_not_found off; }
        location = /robots.txt  { access_log off; log_not_found off; }
 
        error_page 404 /index.php;

	location ~ ^/index\.php(/|$) {	
		# With php-fpm (or other unix sockets):
		fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
                fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
                include fastcgi_params;
                fastcgi_hide_header X-Powered-By;
		# With php-cgi (or other tcp sockets):
		#fastcgi_pass 127.0.0.1:9000;
		fastcgi_param DOCUMENT_ROOT $realpath_root;

		internal;
	}
	
	location ~ \.php$ {
	   return 404;
	}
	# deny access to .htaccess files, if Apache's document root
	# concurs with nginx's one
	#
        location ~ /\.(?!well-known).* {
               # deny all;
        }

    ssl_certificate /etc/letsencrypt/live/storeboot.com-0001/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/storeboot.com-0001/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot


}

server {
    if ($host = www.storeboot.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


    if ($host = storeboot.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


        listen 80;
        listen [::]:80;

        server_name storeboot.com www.storeboot.com;
    return 404; # managed by Certbot




}
