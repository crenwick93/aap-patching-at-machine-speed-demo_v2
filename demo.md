# Full Patching at Machine Speed — Demo Runbook

This is a guided walkthrough for delivering the demo. Each section is a beat in the narrative. Open the tabs/windows listed before you start so transitions are smooth.

> **Sibling note:** The kpatch (zero-reboot) runbook lives in `aap-patching-at-machine-speed-demo`. This runbook is the permanent package-upgrade path. The story CVE is **PHP RCE CVE-2026-109618** — reboot is assessed as **not required**, and Apply Patch skips reboot accordingly.

---

## Pre-flight

Have these open and ready:

| Tab | URL / Tool |
|-----|-----------|
| ServiceNow — Trading Service (Dev) | Favourited service in ServiceNow |
| ServiceNow — Trading Service (Prod) | Favourited service in ServiceNow |
| Red Hat Insights — Systems | console.redhat.com > Insights > Inventory |
| Red Hat Insights — Vulnerabilities | console.redhat.com > Insights > Vulnerability > CVEs |
| AAP — Workflow Jobs | AAP > Jobs (filtered to Workflow Jobs) |
| AAP — Workflow Visualizer | AAP > Templates > Trading Service: CVE Full Patch Response > Visualizer |
| Terminal | SSH ready: `ssh -i patching-demo.pem ec2-user@rhel-dev-01.trading-demo.chrislab.dev` |
| Terminal | Local shell in the repo root for running the simulate script |

Make sure the demo has been reset (`scripts/reset-demo.sh`), ServiceNow is clean (`scripts/cleanup-servicenow.sh --all`), and the EDA rulebook activation **Full Patch CVE Response** is running.

The reset script seeds vulnerable PHP (`8.0.30-6.el9_8`) and updates `/etc/hosts`:

```bash
ssh -i patching-demo.pem ec2-user@rhel-dev-01.trading-demo.chrislab.dev
ssh -i patching-demo.pem ec2-user@rhel-prod-01.trading-demo.chrislab.dev
```

---

## Act 1: The Environment

**Narrative:** "Here's our Trading Service — a business-critical financial services application running across six RHEL 9 nodes, split into dev and prod."

### 1.1 — Show the services in ServiceNow

- Open **Trading Service (Dev)** in ServiceNow
  - Show the 3 dev CIs: `rhel-dev-01`, `rhel-dev-02`, `rhel-dev-03`
  - Point out they're healthy Linux Servers
- Switch to **Trading Service (Prod)**
  - Show the 3 prod CIs: `rhel-prod-01`, `rhel-prod-02`, `rhel-prod-03`
  - "These are the production nodes — this is what we really care about."

### 1.2 — Show the systems in Insights

- Switch to **Red Hat Insights — Systems**
  - Show all 6 nodes registered and reporting
  - "Red Hat Insights is continuously monitoring these systems for vulnerabilities, compliance, and drift."

### 1.3 — Show the vulnerability in Insights

- Navigate to **Vulnerabilities** in Insights
  - Find [CVE-2026-109618](https://console.redhat.com/insights/vulnerability/cves/CVE-2026-109618)
  - Click into it: Important, CVSS 8.1, **known exploit**, PHP RCE
  - "This CVE affects our trading platform nodes. In a traditional workflow, someone would notice this, raise a ticket, schedule a maintenance window... that could take days or weeks."

### 1.4 — Prove it on the node itself

- Switch to the **Terminal** (SSH into `rhel-dev-01.trading-demo.chrislab.dev`)

```bash
hostname
rpm -q php
sudo dnf updateinfo info --cve CVE-2026-109618 2>/dev/null | head -30
sudo needs-restarting -r; echo "exit=$?"
```

- "This is `rhel-dev-01` — same system you just saw in Insights."
- "PHP is sitting on a vulnerable build — `php-8.0.30-6.el9_8`. RHSA-2026:87151 is outstanding."
- "And notice: `needs-restarting -r` is clean. This is a userland package — we can remediate without a reboot."

---

## Act 2: The Automation — What's About to Happen

**Narrative:** Before triggering anything, walk the audience through the automation that's waiting to respond.

### 2.1 — Show Event-Driven Ansible

- Switch to **AAP — Event-Driven Ansible > Rulebook Activations**
  - Show the running activation **Full Patch CVE Response** — "This is listening 24/7 for events from Red Hat Insights. The moment a critical CVE is detected on any of our Trading Service nodes, it fires."
  - Click into the rulebook to show the rules:
    - "Rule one: when Insights reports a new CVE, it triggers the full response workflow automatically."
    - "Rule two: it's also polling ServiceNow for CRs matching `Emergency full patch`. When that CR is approved, it bridges the approval back into AAP to resume production deployment."

### 2.2 — Walk through the Workflow

- Switch to **AAP — Templates > Trading Service: CVE Full Patch Response**
  - Open the **Visualizer**
  - Walk through the nodes left to right:
    1. **Assess & SBOM Baseline** — "Scan every node: outstanding CVEs, whether a package update is available, whether a reboot will be required, and capture an SBOM."
    2. **Open Incident** — "Raise ServiceNow incidents for Dev and Prod with the assessment findings baked in."
    3. **Pre-check All Hosts** — "Safety checks before we touch anything."
    4. **Canary: Apply Patch** — "Dev goes first. CVE-targeted `dnf` upgrade, restart affected services, reboot **only if** `needs-restarting` says so."
    5. **Canary: Post-checks** — "Validate the canary: CVE cleared, no pending reboot, services healthy, SBOM diff."
    6. **Open Emergency CR** — "Dev succeeded — raise an emergency CR for production. For this PHP CVE, the CR explicitly says reboot is **not** required."
    7. **Awaiting CR Approval** — "Workflow pauses until a human approves in ServiceNow."
    8. **Apply Patch (Prod)** — "Same upgrade path on production."
    9. **Post-checks (Prod)** — "Same validation on production."
    10. **Post-Implementation Review** — "Resolve incidents, move CR to review, mark CVE Resolved in Insights."
  - "Dev is fully automated. Production has a governance gate. One human decision: approve or reject the Change Request."

---

## Act 3: The Event

**Narrative:** "Now let's trigger it. In a real environment, Insights would fire an event the moment a new critical CVE is detected. We're going to simulate that."

### 3.1 — Simulate the CVE event

- Switch to the **local terminal**

```bash
./scripts/simulate-cve-event.sh
```

- Pause and talk through the payload:
  - "Critical/Important CVE, CVSS 8.1, known exploit, detected on rhel-dev-01"
  - "Insights has sent this event to Event-Driven Ansible"
  - "EDA triggers our entire response workflow — no human intervention to start"

### 3.2 — Show the workflow kick off

- Switch to **AAP — Workflow Jobs**
  - A new "Trading Service: CVE Full Patch Response" workflow should appear within seconds
  - Click into it to show the **Visualizer**

---

## Act 4: The Automated Response

**Narrative:** Walk through each phase as it executes. For PHP there is no reboot wait — use the time to explain assess vs patch decisions.

### 4.1 — Assess & SBOM Baseline

- Watch "Assess & SBOM Baseline" complete
- "It's scanned all 6 nodes. Patch available: yes. Reboot required for fix: **false**."

### 4.2 — Open Incident

- As soon as "Open Incident" completes, switch to **ServiceNow**
- Show Dev and Prod incidents with assessment findings (CVE list, reboot required: false)
- Show work notes — full transparency into what the automation found

### 4.3 — Pre-checks & Dev Canary

- "Pre-checks confirm it's safe to patch."
- "Now it's applying the CVE-targeted package upgrade to dev. No reboot expected for PHP."
- After "Canary: Post-checks" completes:
  - "CVE cleared from outstanding updates, services healthy, Trading Service UP, SBOM diff captured."

### 4.4 — Open Emergency CR + Approval

- Workflow reaches **"Open Emergency CR"** then pauses at **"Awaiting CR Approval"**
- Switch to **ServiceNow**
  - Show the emergency CR (`Emergency full patch …`)
  - Call out the implementation plan: **no reboot expected — package upgrade and service restart only**
  - "Governance gate: production waits for a human."
- **Approve the CR** and click **Implement**
- Switch back to **AAP** — workflow resumes within ~10 seconds

### 4.5 — Production Deployment

- "Same package upgrade on production — governed, approved, audited. Still no reboot."
- Watch "Apply Patch (Prod)" and "Post-checks (Prod)" complete

### 4.6 — Post-Implementation Review

- Switch to **ServiceNow**:
  - Dev + Prod incidents resolved
  - Emergency CR moved to Review
  - No follow-up CR — this *is* the permanent fix

---

## Act 5: The Proof

**Narrative:** "Let me prove the vulnerability is actually gone."

### 5.1 — Prove it on the node

```bash
rpm -q php
sudo dnf check-update --cve CVE-2026-109618; echo "exit=$?"
sudo needs-restarting -r; echo "exit=$?"
```

- "Before: `php-8.0.30-6.el9_8`. After: patched build from RHSA-2026:87151."
- "`dnf check-update --cve` is clean. No reboot was needed."

### 5.2 — Insights resolution

- Navigate to **Red Hat Insights — Vulnerabilities** and refresh
- "Because we changed the RPMs, Insights clears this on the next evaluation after check-in — no manual status API needed for a full package patch."

### 5.3 — The full audit trail

- Walk through ServiceNow incidents + CR
- "Every step from detection to resolution is documented automatically."

---

## Key Talking Points

- **Speed**: "From CVE detection to full remediation across 6 nodes — minutes, not days."
- **One path, many CVEs**: "Same workflow for PHP, openssl, kernel — reboot only when `needs-restarting` says so."
- **Honest change risk**: "The CR told the approver reboot was not required. That matched what Apply Patch actually did."
- **Permanent fix**: "Package upgrade is the lasting remediation. No temporary mitigation, no second CR."
- **Governance**: "Dev automated; production paused for CAB approval."
- **SBOM**: "Before/after bill of materials on every host."
- **Insights closed-loop**: "Automation marks the CVE Resolved so the SOC isn't waiting on the next scan."
- **If asked about kpatch:** "Kpatch is the zero-reboot stop-gap for kernel. This workflow is the permanent package fix — and for userland CVEs like PHP, there was never a reboot to avoid."

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Workflow doesn't start after simulate script | Check EDA activation `Full Patch CVE Response` is running and event stream is not in test mode |
| ServiceNow CR approval doesn't resume workflow | Check EDA rulebook is polling. Filter expects `Emergency full patch` |
| PHP not vulnerable on node | Confirm `rpm -q php` shows `8.0.30-6.el9_8`; re-run `./scripts/reset-demo.sh` |
| Prod job fails on `.stdout` for kernel_before | Fixed in current `patch.yml` — register vars renamed to avoid workflow artifact collision |
| Insights not showing systems | Force `sudo insights-client` on the node |
| Wrong fleet / colliding with kpatch demo | Confirm EC2 tag `demo=patching-full` |

---

## Reset for Next Demo

```bash
./scripts/cleanup-servicenow.sh --all
./scripts/reset-demo.sh
source .env && ansible-playbook ansible_deployment/cac/apply.yml
```
