# role_iptvservice

Sets up `nginx` + `iptv-auth` + `iptv-proxy` (one per upstream account) using
the same variables you already use in `role_iptvservice`.

## Variables (reuse from your current role)

- `role_iptvservice__iptv_hostname` (list)
- `role_iptvservice__allowed_user_agents` (list)
- `role_iptvservice__protected_paths` (list of `{path, args: []}`)
- `role_iptvservice__iptv_ssl_certificate`, `role_iptvservice__iptv_ssl_certificate_key` (optional if you later add TLS)
- `role_iptvservice__iptv_proxy_path` (path to `/usr/bin/iptv-proxy`)
- `role_iptvservice__iptv_logs_path`, `role_iptvservice__report_path`, email vars (not directly used here but kept for compatibility)
- `role_iptvservice__firewall_command`, `role_iptvservice__firewall_local_ip`, `role_iptvservice__proxy_start_port`
- `role_iptvservice__known_ips` (not used by default)
- `role_iptvservice__credentials` (exact shape you posted)
- `role_iptvservice__favicon_root` (directory containing `favicon.ico`)

## Example play

```yaml
- hosts: iptv_hosts
  become: yes
  roles:
    - role: role_iptvservice
