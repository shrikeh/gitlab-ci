#!/usr/bin/env ruby
# -*- mode: ruby -*-
# vi: set ft=ruby :

# Fail if the version of Vagrant is pre 1.8 (when Ansible local was added)
Vagrant.require_version '>= 1.8'

ENV['VAGRANT_NO_PARALLEL'] = 'no'

require 'ipaddr'
require 'pathname'

ansible_host_dir    = Pathname.new(ENV['VAGRANT_ANSIBLE_DIR'] || './ansible')
ansible_guest_dir   = Pathname.new(ENV['VAGRANT_ANSIBLE_GUEST_DIR'] || '/ansible')

# Check the Ansible directory exists
unless ansible_host_dir.readable?
  abort "Ansible directory #{ansible_dir_path} not readable"
end

required_plugins = %w(vagrant-hostmanager vagrant-share vagrant-cachier)

plugins_to_install = required_plugins.select { |plugin| not Vagrant.has_plugin? plugin }
if not plugins_to_install.empty?
  puts "Installing plugins: #{plugins_to_install.join(' ')}"
  if system "vagrant plugin install #{plugins_to_install.join(' ')}"
    exec "vagrant #{ARGV.join(' ')}"
  else
    abort 'Installation of one or more plugins has failed. Aborting.'
  end
end

# See https://gist.github.com/shrikeh/7773030d8b237ea3b7c5baab2652d927
$bootstrap_script = 'https://gist.githubusercontent.com/shrikeh/7773030d8b237ea3b7c5baab2652d927/raw/centos6-python-bootstrap.sh'

# All Vagrant configuration is done below. The "2" in Vagrant.configure
# configures the configuration version (we support older styles for
# backwards compatibility). Please don't change it unless you know what
# you're doing.
Vagrant.configure('2') do |config|
  config.hostmanager.enabled            = true
  config.hostmanager.manage_host        = true
  config.hostmanager.manage_guest       = true
  config.hostmanager.ignore_private_ip  = false
  config.hostmanager.include_offline    = true

  # Get the dynamic hostname from the running box so we know what to put in
  # /etc/hosts even though we don't specify a static private ip address
  config.hostmanager.ip_resolver = proc do |vm, resolving_vm|
    if vm.communicate.ready?
      result = ""
      vm.communicate.execute('ifconfig eth1') do |type, data|
        result << data if type == :stdout
      end
    end
    (ip = /inet addr:(\d+\.\d+\.\d+\.\d+)/.match(result)) && ip[1]
  end

  # Every Vagrant development environment requires a box. You can search for
  # boxes at https://atlas.hashicorp.com/search.
  # config.vm.box                           = './boxes/gitlab.box'
  config.vm.box                         = 'bento/centos-6.8'
  config.vm.box_download_checksum       = '7171e4c8db640cd93a1547baf96a0bb65547e134bfc5b1d34040523e9ba9886f'
  config.vm.box_download_checksum_type  = :sha256
  config.cache.scope                    = :machine

  # CentOS 6.8 doesn't come with Python 2.7, so we need to tweak this
  config.vm.provision 'bootstrap',
    type: 'shell',
    path: $bootstrap_script

  %w(phpspec phpunit rspec cucumber).each do |runner|
    config.vm.define "runner-#{runner}" do |node|
      node.vm.hostname  = "runner-#{runner}"
      node.vm.provider :virtualbox do |v|
        v.name          = "jard-gitlab-runner-#{runner}"
        v.linked_clone  = true
        v.memory        = 512
        v.cpus          = 1
      end
      node.vm.network :private_network, type: :dhcp
    end
  end

  config.vm.define :gitlab_controller, primary: true do |gitlab|
    box_name                    = 'jard-ci'
    # jard_ip                     = IPAddr.new (ENV['VAGRANT_JARD_IP'] || '192.168.33.11')
    host_ip                     = IPAddr.new '127.0.0.1'
    gitlab.vm.hostname          = box_name
    gitlab.hostmanager.aliases  = %w(gitlab jard_ci jard-ci.local)

    gitlab.vm.provider :virtualbox do |v|
      v.name          = 'jard-gitlab-ci'
      v.linked_clone  = true
      v.memory        = 1024
      v.cpus          = 2
    end

    gitlab.vm.network :private_network, type: :dhcp

    galaxyPath = '/galaxy'

    # gitlab.vm.network 'forwarded_port',
    #   guest:    5432,
    #   host:     5432,
    #   host_ip:  host_ip.to_s,
    #   id:       'postgresql'

      # We create a local Galaxy roles folder, shared with the VM.
      # This allows us to cache downloaded Galaxy roles, but still easily delete them if we wish to.
      gitlab.vm.synced_folder './ansible/galaxy', galaxyPath,
        create:   true,
        id:       :galaxy,
        type:     :nfs

      gitlab.vm.provision :ansible_local do |ansible|
        ansible.install_mode      = :pip
        ansible.config_file       = 'ansible/ansible.cfg'
        ansible.playbook          = 'ansible/playbook.yml'
        ansible.galaxy_role_file  = 'ansible/galaxy.yml'
        ansible.galaxy_roles_path = galaxyPath
        # The default command uses --force which causes provisioning to be slow
        ansible.galaxy_command    = 'ansible-galaxy install --role-file=%{role_file} --roles-path=%{roles_path} --ignore-errors'
        ansible.limit             = 'all' # or only "nodes" group, etc.
        ansible.inventory_path    = 'ansible/inventory/vagrant'

        ansible.sudo              = true
      end
  end
end
