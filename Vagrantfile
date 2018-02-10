
# Backend Server
Vagrant.configure("2") do |config|
  # Base VM
  config.vm.box = "ubuntu/xenial64"

  # Use the bash provisioning script to configure the vm
  config.vm.provision "shell" do |s|
    s.path = "provision.sh"
    s.args = "/vagrant/dev_provision.conf"
  end

  # Forward port
  config.vm.network "forwarded_port", guest: 80, host: 8080

  # Set up private networking
  config.vm.network "private_network", ip: "192.168.33.105"

  # Mount project directory to /vagrant as a shared directory
  config.vm.synced_folder "./", "/vagrant", owner: "www-data", group: "www-data"

end
