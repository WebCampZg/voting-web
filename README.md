WebCamp Voting Web
==================

Installation
------------

Clone and provision:

```
git clone https://github.com/WebCampZg/voting-web.git wcvote
cd wcvote
vagrant up
```

After provisioning your local wcvote should be running at http://33.33.33.80/

Creating users
--------------

To use application from shell login to your VM and go to /var/www where application is situated.

```
vagrant ssh
cd /var/www
```

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
