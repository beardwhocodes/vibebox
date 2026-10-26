# Finish the security setup

Use this guide after the installer and a fresh Tailscale SSH connection succeed.
Run the installer's `confirm` command before changing the provider firewall.
The installer joins Tailscale and configures UFW. It does not isolate the tailnet
or close the temporary provider SSH rule.

## 1. Separate server identities from personal devices

Save the current tailnet policy before editing it. Record the administrator
devices and their Tailscale IPv4 and IPv6 addresses. Record each server address.
An enrollment `TS_AUTHKEY` cannot edit the tailnet policy. Use the signed-in
admin console or a separately authorized API credential.

Tagging replaces a device's user identity. Prepare an owner-to-tag SSH rule
before tagging the server. Keep the laptop and phone as personal devices.
Tailscale's [tag guide](https://tailscale.com/docs/features/tags) explains this
identity change and the authentication required to remove every tag.

Merge the following example into the policy. Replace the email and both
addresses with the actual administrator identity and device addresses.
This is a configuration example, not a replacement for an existing policy.

```json
{
  "hosts": {
    "admin-v4": "100.64.0.10",
    "admin-v6": "fd7a:115c:a1e0::10"
  },
  "tagOwners": {
    "tag:vps": ["owner@example.com"]
  },
  "grants": [
    {
      "src": ["admin-v4", "admin-v6"],
      "dst": ["tag:vps"],
      "ip": ["tcp:22"]
    }
  ],
  "ssh": [
    {
      "action": "accept",
      "src": ["owner@example.com"],
      "dst": ["tag:vps"],
      "users": ["root", "autogroup:nonroot"]
    }
  ],
  "sshTests": [
    {"src": "owner@example.com", "dst": ["tag:vps"], "accept": ["root"]},
    {"src": "tag:vps", "dst": ["tag:vps"], "deny": ["root", "www-data"]}
  ]
}
```

The network grant limits the permitted source devices. The separate SSH rule
selects the login identity and OS accounts. `accept` permits unattended SSH
without a browser check; use `check` if interactive approval is required.
See the [grant examples](https://tailscale.com/docs/reference/examples/grants)
and [SSH policy reference](https://tailscale.com/docs/reference/syntax/policy-file).

Apply the changes in this order:

1. Add `tagOwners` and the owner-to-`tag:vps` SSH rule to the current policy.
2. Save the policy and confirm that validation succeeds.
3. Assign `tag:vps` through Machines → the server menu → Edit ACL tags.
4. Verify a fresh administrator SSH connection after each server tag change.
5. Add the network grant for the selected personal device addresses.
6. Remove or narrow every overlapping ACL or grant that permits VPS sources.
7. Save network tests for administrator access and rejected server access.
8. Repeat the live checks below.

Rules are additive. A narrow grant does not override an existing allow-all rule.
Audit both legacy `acls` and `grants`. Preserve required personal-device access.
Give each required service connection a separate, narrow rule.
For example, an optional Hermes SSH worker needs an explicit gateway-to-worker
grant and SSH rule. It is an exception to the isolated-server baseline.
Mosh and private application access need grants for their configured ports.
Keep those grants limited to the selected administrator devices.

Add each administrator device's two addresses explicitly. Add network tests
for each address family separately. Test each server against the other servers
and the selected personal devices. Network tests use `tests`; SSH role tests
use `sshTests`. Both must pass before the final policy save.

The completed policy has no broad server source grant. Tagged servers cannot
use an owner's `autogroup:self` SSH rule as personal devices. Check application
capabilities and node attributes separately. Grant Funnel or Taildrop access
only when the server needs it.

## 2. Close provider ingress after bootstrap

Keep the initial public SSH rule until the fresh Tailscale connection succeeds
and the installer deadman is cancelled. A host deadman cannot change the
provider firewall or repair a tailnet policy.

Complete the recovery checks in section 4 before the provider change.
In the deployment's `terraform/` directory, save this non-secret setting as
`security.auto.tfvars.json`:

```json
{"public_ssh_enabled": false}
```

This file is deployment configuration. Keep a private operational copy with
the deployment inputs; the publisher excludes deployment variable files.
For an existing server, review the bounded firewall plan:

```bash
terraform plan -target=hcloud_firewall.rescue -out=firewall.tfplan
terraform apply firewall.tfplan
rm firewall.tfplan
```

The plan must change only the firewall rules. Saved plans can contain secrets.
This targeted operation isolates the firewall change from unrelated server
or SSH-key differences. Review a complete plan separately before a full apply.
The server configuration keeps provider delete and rebuild protection and
Terraform `prevent_destroy`. Intentional replacement needs a separate plan.

For a manually provisioned server, attach a Hetzner firewall and clear its
inbound rules after the same access checks. Inspect every attached firewall:
their rules combine. An empty firewall beside a permissive firewall is not
sufficient. Leave outbound rules empty for unrestricted outbound connections.
Review private networks separately; this provider firewall protects public traffic.

Verify the provider state after the apply. Terraform can silently retain the
last rule; see the [provider report](https://github.com/hetznercloud/terraform-provider-hcloud/issues/1482).
Read `GET /v1/firewalls/{id}` and confirm an empty `rules` array.
Confirm that the firewall is applied to the intended server.
If a rule remains, use the console or
`POST /v1/firewalls/{id}/actions/set_rules` with `{"rules":[]}`.
Wait for every returned action to succeed, then read the firewall again.
Keep the firewall attached throughout this operation.

No public application ports are required for an outbound Cloudflare Tunnel.
Tailscale can use outbound connections and relays. A closed provider firewall
can affect direct Tailscale paths; verify access and transfer performance.
See [Hetzner firewall behavior](https://docs.hetzner.com/cloud/firewalls/faq/).

## 3. Verify the boundaries

Use fresh sessions. Existing connections can survive a firewall change.
Keep host-key verification enabled and disable SSH connection reuse.

- From each selected personal device, connect to each server over IPv4 and IPv6.
- From each VPS, attempt TCP connections to peer VPS Tailscale addresses.
- Repeat those attempts against peer public addresses and both address families.
- Test a controlled listener on a personal device. Verify that the listener
  accepts an allowed control connection before testing rejected VPS connections.
- Repeat the cross-server SSH attempt as the web worker or the Hermes runtime user.
- Confirm that each VPS reports `tag:vps` in `tailscale status --json`.
- Run `tailscale file cp --targets` on each isolated VPS. Expect no targets.
- Probe public IPv4 and IPv6 addresses from outside the VPS. Record the ports
  tested and successful Internet controls. A sample is not an all-port scan.
- Verify each active website through its Cloudflare hostname.
- For Hermes, also verify `hermes-gateway` and a reply through the selected channel.

Record policy test results, addresses, timestamps, and the recovery path.
New personal devices or changed addresses need a reviewed policy update.
Network isolation does not replace application authentication, upload checks,
patches, or an application security review.

## 4. Keep a usable recovery path

Before closing public ingress, verify access to the Hetzner console. Confirm
that a registered rescue SSH public key matches a usable local private key.
An authenticated console session does not prove an OS login. Images can have
locked OS passwords.

Opening provider port 22 alone does not bypass UFW. After `confirm`, the deadman
no longer restores access. For an installed OS that you cannot reach, follow
[Hetzner Rescue](https://docs.hetzner.com/cloud/servers/getting-started/rescue-system/).
A rescue boot interrupts the workload. Use a planned window for a drill.
Select the correct server and registered SSH key before the reboot.
Verify the rescue host key through the console and keep its known-hosts entry
separate from the normal OS entry.

Inspect the disks before a repair. Return to the installed OS after the repair.
Remove temporary provider rules and verify the empty rules array again.
Verify fresh Tailscale SSH and the active workload.
Hetzner documents provider-service exceptions to its firewall, including rescue
traffic. Verify rescue access against current provider behavior.

When repairing the tailnet policy, retain the owner-to-`tag:vps` SSH rule.
The policy from before tagging can remove administrator SSH access.
Use a policy that permits the selected personal devices; an allow-all policy
restores the lateral access risk.

Keep registered recovery keys. On an SSH-key uniqueness error, inspect the
existing key and its Terraform ownership before another apply. Reuse an
existing managed key through an explicit configuration reference. Import an
unmanaged key only into one state. Verify the proposed resource changes first.

## 5. Record backup coverage and update ownership

Installed software is not an active backup. A copy on the same VPS cannot
recover data after disk or server loss. A one-time laptop copy does not update.
Record the active data paths, destination, schedule, retention, and latest
successful isolated restore. Keep backup credentials and restored data private.

Vibebox installs Litestream, but its bucket, endpoint, credentials, and database
path still need configuration. Check the installed version's help, start the
service, and restore from the remote replica into a separate directory.
For SQLite, verify the restored database with `PRAGMA integrity_check`.
Use SQLite's backup API or a supported backup tool for a live database copy.

Hermes needs coverage for the actual `HERMES_HOME` of its runtime user, normally
`/home/hermes/.hermes/`. Include configuration, authentication, memory, sessions,
and any separate application data. A legacy OpenClaw backup does not cover
Hermes automatically. Verify restored files and application data separately.

Paid provider backups are optional. Record the operator's choice; keep them off
when declined. They are not enabled by these steps. A provider image still needs
an isolated restore test and an application consistency check.

Ubuntu unattended upgrades cover configured package origins. Check separate
update paths for Tailscale, cloudflared, Hermes, and application dependencies.
An enabled Ubuntu updater does not prove that each vendor package updates.
