# -*- mode: ruby -*-
# vi: set ft=ruby :

# Vagrantfile API/syntax version. Don't touch unless you know what you're doing!
VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|

  config.vm.box = "trusty"
  config.vm.box_url = "https://cloud-images.ubuntu.com/vagrant/trusty/current/trusty-server-cloudimg-amd64-vagrant-disk1.box"

  config.vm.network "private_network", ip: "33.33.33.80"

  config.vm.synced_folder "./", "/var/www", type: "nfs"

  config.vm.boot_timeout = 9000

  config.vm.hostname = "wczg-voting"

  config.vm.provider "virtualbox" do |vb|
    # Boot with headless mode
    # vb.gui = true

    # Use VBoxManage to customize the VM. For example to change memory:
    vb.customize ["modifyvm", :id, "--memory", "2048"]
  end

  config.vm.provision "ansible" do |ansible|
    ansible.playbook = "provisioning/vagrant.yml"
    # output as much as you can, or comment this out for silence
    ansible.verbose = "vvvv"
    ansible.sudo = true
  end

end

