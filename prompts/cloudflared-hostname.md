# Prompt — route a new subdomain through the tunnel

For when you add a project on this box and want `<sub>.example.com` to reach it.

```
I want to expose `<HOSTNAME>` (e.g. app.example.com) through the existing Cloudflare tunnel
running on this box. The local service listens on http://localhost:80 (nginx, server_name
<HOSTNAME>). Do NOT open any inbound firewall ports — the tunnel is outbound.

Steps:
1. Verify the tunnel is running: `systemctl status cloudflared`. If not active, stop and tell me.
2. Tell me the tunnel ID this box is using (it's in /etc/cloudflared or via
   `cloudflared tunnel list`). I'll need it for step 4.
3. Verify nginx has a server block with `server_name <HOSTNAME>` and `listen 127.0.0.1:80`. If
   not, add one (don't touch any other vhost).
4. Print the exact action I need to take in the Cloudflare Zero Trust dashboard:
   - Tunnel <ID> -> Public Hostname -> Add a public hostname
   - Subdomain: <SUB>   Domain: <APEX>
   - Service: HTTP   URL: localhost:80
   - Additional application settings -> HTTP Settings -> HTTP Host Header: <HOSTNAME>
5. Wait for me to confirm I've added it in the dashboard. Then test from the box:
   `curl -s -o /dev/null -w "%{http_code}\n" -H "Host: <HOSTNAME>" http://localhost/`
   and from off-box: `curl -s -o /dev/null -w "%{http_code}\n" https://<HOSTNAME>/`
6. If both return 200, append the hostname to a checklist file `/etc/vibebox/hostnames.txt`
   for inventory.

Do not edit firewall rules. Do not restart cloudflared unless the dashboard mapping requires it
(it usually doesn't — public hostnames are picked up live).
```
