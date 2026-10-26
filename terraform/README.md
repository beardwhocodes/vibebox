# vibe — Terraform module

Provisions a Hetzner server **from your `vibe-coding` snapshot** (built by [`../packer/`](../packer/)),
attaches your SSH key, and adds a Hetzner firewall with a temporary bootstrap SSH rule.

Use this when you want the boring "create the box" step to be one `apply` instead of clicks.

## What it creates
- `hcloud_ssh_key` — your public key, registered in the project.
- `hcloud_firewall` — temporary public TCP 22 from `admin_ip_cidr` while `public_ssh_enabled` is true. Close it after the security checks.
- `hcloud_server` — built from the most recent `vibe-coding` snapshot, with your SSH key attached.

## What it does NOT do
No Tailscale join, no `ufw`, no `cloudflared`, no Litestream — those need secrets and must run
**after** first boot, safely, via [`../vibebox.sh`](../vibebox.sh) (with its deadman timer).

## Use
```bash
# 1. Build the image first (one time per Hetzner account)
cd ../packer && packer build vibe-base.pkr.hcl

# 2. Provision
cd ../terraform
cp terraform.tfvars.example terraform.tfvars   # fill in token + SSH key + your IP
terraform init
terraform apply

# 3. Lock down
ssh root@$(terraform output -raw server_ip)
# upload vibebox.sh and config.env, then:
bash vibebox.sh
# then, from a NEW Tailscale SSH session:
bash vibebox.sh confirm
```

## Inputs
| Variable | Required | Notes |
|---|---|---|
| `hcloud_token` | yes | Hetzner Cloud API token. Sensitive; goes in `terraform.tfvars` (gitignored). |
| `ssh_public_key` | yes | The single line from `~/.ssh/id_ed25519.pub`. |
| `admin_ip_cidr` | yes | Temporary bootstrap SSH source, such as `203.0.113.7/32`. |
| `public_ssh_enabled` | no | Defaults to `true` for bootstrap. Set `false` after security acceptance. |
| `project` | no | Server name + label. Default `vibebox-1`. |
| `server_type` | no | `cx23` (cheap EU, default) / `cpx11` (cheap US) / `cx32` (more headroom) / `cax11` (ARM). |
| `location` | no | `nbg1` / `fsn1` / `hel1` / `ash` / `hil`. Default `hel1`. |

## Notes
- The snapshot lookup uses the `name=vibe-coding` label set by Packer + `most_recent = true`.
  Rebuild the image whenever you want fresh apt updates baked in.
- `terraform.tfvars` and `*.tfstate*` are gitignored. State contains secrets — keep it private.
- Server destruction is blocked by Terraform and provider protection. Intentional removal requires a separate plan and a verified backup.

## Field-tested gotchas (caught during v0.2 validation)

### `admin_ip_cidr`: use **IPv4** + `/32`
`curl ifconfig.me` returns whichever address family is preferred by your network, and many home
ISPs prefer IPv6. Hetzner's firewall rules require valid CIDR notation: `/32` is only valid for
IPv4, and an IPv6 like `2600::1/32` errors with "is not the start of the cidr block 2600::/32".

Get your IPv4 explicitly:
```bash
curl -4 ifconfig.me        # use this output as `admin_ip_cidr = "<that>/32"`
```
If you're on an IPv6-only network, use `<your-v6>/128` instead.

## SSH-key uniqueness collision

Hetzner deduplicates keys by public-key content. On `uniqueness_error 409`,
inspect the existing key and its Terraform ownership. Keep registered recovery keys.
For an unmanaged key, review adoption into one state with `terraform import`.
This module creates its own key; it has no existing-key input. Reusing a key
managed elsewhere requires a reviewed configuration change that references its ID.
Review the complete plan before an apply; an SSH-key difference can propose server replacement.
The module's `prevent_destroy` guard rejects that replacement.
See [recovery and key ownership](../SECURITY-SETUP.md#4-keep-a-usable-recovery-path).

## Close public ingress after setup

`public_ssh_enabled` defaults to `true` for bootstrap. After a fresh Tailscale
SSH connection succeeds, verify the recovery key and provider console.
Then save `security.auto.tfvars.json` in the deployment directory:

```json
{"public_ssh_enabled": false}
```

Follow [the provider closeout and live checks](../SECURITY-SETUP.md#2-close-provider-ingress-after-bootstrap).
That procedure isolates the firewall change and verifies the actual provider state.
A successful Terraform exit alone is insufficient; the last rule can remain.
The shared guide includes the API correction and recovery steps.

The server configuration retains provider delete and rebuild protection and
Terraform `prevent_destroy`. Intentional replacement needs a separate plan.
