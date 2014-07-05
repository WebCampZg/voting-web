WebCamp Voting Web
==================

Installation
------------

Clone and install dependencies:

```
git clone https://github.com/WebCampZg/voting-web.git wcvote
cd wcvote
curl -sS https://getcomposer.org/installer | php
php composer.phar install
```

Copy the settings file template and edit:

```
cp etc/config.php.dist etc/config.php
vim etc/config.php
```

* Set the path to the mongo database in the format
  `mongodb://<user>:<pass>@<host>:<port>/<database>`
* Add users

Nginx Setup
-----------

```
server {
    listen 80 ;
    server_name wcvote.local;
    root /path/to/wcvote/web/;
    index index.php;

    # Strip slashes
    rewrite ^/(.*)/$ /$1 permanent;

    access_log /var/log/nginx/wcvote.access_log;
    error_log /var/log/nginx/wcvote.error_log;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php5-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

Creating users
--------------

To add an user, run the following command and enter data as prompted:

```
bin/webcamp adduser
```

To create an admin user, run:
```
bin/webcamp adduser --admin
```

It's possible to pass the username and password as commandline options:
```
bin/webcamp adduser --username=ivan --password="un grand poisson"
```
