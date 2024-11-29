# role_iptvservice
An Ansible role to create a reverse proxy for multiple users against multiple Xtream code IPTV providers using nginx and iptv-proxy

Requirements
------------

* Collections:

```yaml
community.general
```
* System:
```yaml
nginx
iptables
```

Role Variables
--------------

| Variable | Type | Required | Comments |
|-|:-:|:-:|-|
| role_iptvservice__iptv_hostname | List | Yes | List of domain names for Web Server and iptv-proxy |
| role_iptvservice__allowed_user_agents | List | Yes | List of allowed user agents to connect to proxy |
| role_iptvservice__iptv_ssl_certificate | String | No | SSL Certificate for HTTPS |
| role_iptvservice__iptv_ssl_certificate_key | String | No | SSL Key for HTTPS |
| role_iptvservice__iptv_logs_path | String | Yes | Path to store logs |
| role_iptvservice__iptv_proxy_path | String | No | Path to iptv-proxy (Defaults to /usr/bin/iptv-proxy) |
| role_iptvservice__max_connections | Int | Yes | Maximum number of connections per provider account |
| role_iptvservice__report_path | String | Yes | Path to store reports |
| role_iptvservice__email_sender | String | Yes | Report Sender Email Address |
| role_iptvservice__email_server | String | Yes | Email Server |
| role_iptvservice__email_recipients | List | Yes | List of report Email recipients |
| role_iptvservice__firewall_command | String | Yes | Command to restart iptables firewall |
| role_iptvservice__firewall_local_ip | String | Yes | IP address of machine running proxy and web server |
| role_iptvservice__proxy_start_port | Int | Yes | Starting port to use for proxies |
| role_iptvservice__known_ips | Dict | No | Dictionary that defines known proxy user IPs for report |
| role_iptvservice__credentials | Dict | Yes | Dictionary that defines proxy and provider credentials |

# role configuration (I store in vars/config.yml on playbook)
```yaml
# Iptv Webserver Config
role_iptvservice__iptv_hostname:
  - "mysite.com"
  - "www.mysite.com"

role_iptvservice__allowed_user_agents:
  - "Smarters"
  - "TiviMate"
  - "VLC"

role_iptvservice__iptv_ssl_certificate: "/etc/letsencrypt/live/{{ role_iptvservice__iptv_hostname | first }}/fullchain.pem"
role_iptvservice__iptv_ssl_certificate_key: "/etc/letsencrypt/live/{{ role_iptvservice__iptv_hostname | first }}/privkey.pem"
role_iptvservice__iptv_proxy_path: /usr/bin/iptv-proxy

# logging & reports
role_iptvservice__iptv_logs_path: "/root/iptvservice/logs"
role_iptvservice__max_connections: 5
role_iptvservice__report_path: "/root/iptvservice/reports"
role_iptvservice__email_sender: "noreply@mysite.com"
role_iptvservice__email_server: "mysite.com"
role_iptvservice__email_recipients:
  - "my@email.com"
  - "other@email.com"

# Firewall / Network
role_iptvservice__firewall_command: "iptables -F"
role_iptvservice__firewall_local_ip: "1.2.3.4"
role_iptvservice__proxy_start_port: 30000

# Known IPs
role_iptvservice__known_ips:
  1.2.3.4:
    name: "Friend1 Home IP"
  5.6.7.8:
    name: "Friend2 Home IP"
  9.10.11.12:
    name: "Friend3 Home IP"
```
# credentials (I store in vars/credentials.yml on playbook)
```yaml
role_iptvservice__credentials:
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
      - { name: "My Friend 1", username: "friend6", password: "somepassword", live: false }
      - { name: "My Friend 2", username: "friend7", password: "somepassword", vod: false }
      - { name: "My Friend 3", username: "friend8", password: "somepassword" }
      - { name: "My Friend 4", username: "friend9", password: "somepassword" }
      - { name: "My Friend 5", username: "friend10", password: "somepassword" }
    provider_credentials:
      - { account: "account1", username: "providerusername1", password: "providerpassword1" }
      - { account: "account2", username: "providerusername2", password: "providerpassword2" }
```

Sample Usage:
```yaml
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

    - name: Include role_iptvservice
      ansible.builtin.include_role:
        name: role_iptvservice
      tags:
        - always
```

Run Usage:
```
Setup Webserver and run Proxies. Relaunch when adding new credentials
  ansible-playbook site.yml -t iptvproxy

Run report on previous day connection statistics. Run on daily cron or scheduled job.
  ansible-playbook site.yml -t dailyconnections

Check number of current active connections and kill processes if provider account is overloaded. Run on frequent cron or scheduled job.
  ansible-playbook site.yml -t numconnections
```