# Requirements

## Platform

| Component | Version | Notes |
|---|---|---|
| Red Hat AAP | 2.5+ | Controller + EDA + Gateway |
| ServiceNow | Washington DC+ | PDI or production instance |
| AWS | Any | EC2 fleet for demo hosts |
| RHEL | 9.x | Demo nodes with Insights registered |

## Ansible Collections

```bash
ansible-galaxy collection install -r ansible_deployment/cac/requirements.yml
```

| Collection | Purpose |
|---|---|
| `infra.aap_configuration` | Configuration as Code for AAP |
| `ansible.controller` | Controller API interactions |
| `servicenow.itsm` | ServiceNow incident and change request management |

## EDA Decision Environment

The EDA rulebook uses `servicenow.itsm.records`, which requires a Decision Environment with that collection:

- Definition: `ansible_deployment/ee/decision-environment.yml`
- Default image referenced in CaC: `quay.io/crenwick93/snow-de:latest`

## Credentials Required

| Credential | Purpose |
|---|---|
| AAP OAuth Token | CaC deployment + EDA controller auth |
| ServiceNow credentials | Incident/CR management + EDA polling |
| AWS credentials | EC2 provisioning + dynamic inventory |
| SSH key | Demo host access |
| RHSM org + activation key | Subscribe + register Insights on fresh nodes |
| Lightspeed client ID/secret | Insights CVE status update in PIR |

## Sibling demos

| Repo | Strategy |
|---|---|
| `aap-patching-at-machine-speed-demo` | Live **kpatch** (zero reboot) |
| `aap-patching-at-machine-speed-demo_v2` (this repo) | **Full package upgrade + reboot** |

EC2 tag for this demo: `demo=patching-full` (avoids colliding with the kpatch fleet).
