# role_iptvservice
An Ansible role to create a reverse proxy for multiple users against multiple Xtream code IPTV providers using nginx and iptv-proxy

## Update README with all required variables and usage

# role configuration (I store in vars/config.yml on playbook)

# iptv webserver config
role_tvservice__iptv_hostname:
  - "mysite.com"
  - "www.mysite.com"

role_tvservice__allowed_user_agents:
  - "Smarters"
  - "TiviMate"
  - "VLC"

role_tvservice__iptv_ssl_certificate: "/etc/letsencrypt/live/{{ role_tvservice__iptv_hostname | first }}/fullchain.pem"
role_tvservice__iptv_ssl_certificate_key: "/etc/letsencrypt/live/{{ role_tvservice__iptv_hostname | first }}/privkey.pem"

# logging & reports
role_tvservice__iptv_logs_path: "/root/iptvservice/logs"
role_tvservice__max_connections: 5
role_tvservice__report_path: "/root/iptvservice/reports"
role_tvservice__email_sender: "noreply@mysite.com"
role_tvservice__email_server: "mysite.com"
role_tvservice__email_recipients:
  - "my@email.com"
  - "other@email.com"

# firewall / network
role_tvservice__firewall_command: "iptables -F"
role_tvservice__firewall_local_ip: "1.2.3.4"
role_tvservice__proxy_start_port: 30000

# known ips
role_tvservice__known_ips:
  1.2.3.4:
    name: "Friend1 Home IP"
  5.6.7.8:
    name: "Friend2 Home IP"
  9.10.11.12:
    name: "Friend3 Home IP"

# credentials (I store in vars/credentials.yml on playbook)
role_tvservice__credentials:
  - name: "iptv service 1"
    url: "http://iptv.com:12345"
    proxy_users:
      - { name: "My Friend 1", username: "friend1", password: "somepassword" }
      - { name: "My Friend 2", username: "friend2", password: "somepassword" }
      - { name: "My Friend 3", username: "friend3", password: "somepassword" }
      - { name: "My Friend 4", username: "friend4", password: "somepassword" }
      - { name: "My Friend 5", username: "friend5", password: "somepassword" }
    provider_credentials:
      - { account: "account1", username: "providerusername1", password: "providerpassword1" }
      - { account: "account2", username: "providerusername2", password: "providerpassword2" }
      - { account: "account2", username: "providerusername3", password: "providerpassword3" }
  - name: "iptv service 2"
    url: "http://otheriptv.com"
    proxy_users:
      - { name: "My Friend 1", username: "friend6", password: "somepassword" }
      - { name: "My Friend 2", username: "friend7", password: "somepassword" }
      - { name: "My Friend 3", username: "friend8", password: "somepassword" }
      - { name: "My Friend 4", username: "friend9", password: "somepassword" }
      - { name: "My Friend 5", username: "friend10", password: "somepassword" }
    provider_credentials:
      - { account: "account1", username: "providerusername1", password: "providerpassword1" }
      - { account: "account2", username: "providerusername2", password: "providerpassword2" }


Sample Usage:

- name: IPTV Service
  hosts: my_server
  become: true
  gather_facts: false

  tasks:
    - name: Include required var files
      ansible.builtin.include_vars:
        file: 'vars/{{ item }}'
      loop:
        - config.yml
        - credentials.yml
      tags:
        - always

    - name: Include role_tvservice
      ansible.builtin.include_role:
        name: role_tvservice
      tags:
        - always

ansible-playbook site.yml -t iptvproxy
ansible-playbook site.yml -t dailyconnections
ansible-playbook site.yml -t numconnections
