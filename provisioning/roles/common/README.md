Common
========

Common set of tools we need to have available across the projects

Requirements
------------

This role requires Ansible 1.4 or higher and tested platforms are listed in the metadata file.

Role Variables
--------------

The variables that can be passed to this role and a brief description about
them are as follows.

    # A list of packages to be installed by the common module
    common_packages:
      - gcc
      - curl
      - make
      - screen
      - git-core
      - vim
      - ack
      - htop

    # Default timezone for our project servers
    project_timezone: 'UTC'

Dependencies
------------

None.


Author Information
------------------

Srdjan Vranac