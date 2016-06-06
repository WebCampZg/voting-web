MySQL
========

MySQL server setup for projects

Requirements
------------

This role requires Ansible 1.4 or higher and tested platforms are listed in the metadata file.

Role Variables
--------------

The variables that can be passed to this role and a brief description about
them are as follows.

    # A list of packages to be installed by the mysql module
    mysql_packages:
      - python-mysqldb
      - mysql-server

Dependencies
------------

Common module


Author Information
------------------

Srdjan Vranac


