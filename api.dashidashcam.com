server {
    listen 80;
    listen 443;

	root /var/www/api.dashidashcam.com;

	index index.php;
	server_name api.dashidashcam.com;

	location / {
		try_files $uri $uri/ /index.php$is_args$args;
	}

	location ~ \.php {
		try_files $uri =404;
	        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        	include fastcgi_params;
	        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
	        fastcgi_param SCRIPT_NAME $fastcgi_script_name;
        	fastcgi_index index.php;
	        fastcgi_pass unix:/var/run/php/php7.0-fpm.sock;
	}
}