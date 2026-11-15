# Full Patching at Machine Speed — Demo Runbook

This is a guided walkthrough for delivering the demo. Each section is a beat in the narrative. Open the tabs/windows listed before you start so transitions are smooth.

> **Sibling note:** The kpatch (zero-reboot) runbook lives in `aap-patching-at-machine-speed-demo`. This runbook is the permanent-fix path: package upgrade + reboot.

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
| Terminal | SSH ready: `ssh -i colombo-demo.pem ec2-user@rhel-dev-01.trading-demo.chrislab.dev` |
| Terminal | Local shell in the repo root for running the simulate script |

Make sure the demo has been reset (`scripts/reset-demo.sh`), ServiceNow is clean (`scripts/cleanup-servicenow.sh --all`), and the EDA rulebook activation **Full Patch CVE Response** is running.

The reset script automatically updates `/etc/hosts` with the current IPs for `rhel-dev-01` and `rhel-prod-01`, so you can SSH using their FQDNs:

```bash
ssh -i colombo-demo.pem ec2-user@rhel-dev-01.trading-demo.chrislab.dev
ssh -i colombo-demo.pem ec2-user@rhel-prod-01.trading-demo.chrislab.dev
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
  - Find a kernel-related CVE on one of the nodes
  - Click into it to show the severity, CVSS score, affected systems
  - "This CVE affects our trading platform nodes. In a traditional workflow, someone would notice this, raise a ticket, schedule a maintenance window... that could take days or weeks."

### 1.4 — Prove it on the node itself

- Switch to the **Terminal** (SSH into `rhel-dev-01.trading-demo.chrislab.dev`)

```bash
# Confirm which node we're on (matches the display name in Insights)
hostname

# Show the running kernel
uname -r

# Show the specific CVE is present
sudo dnf updateinfo info --security kernel 2>/dev/null | grep -A2 "CVE-2026-43037"

# Confirm a reboot would be needed once packages are updated
sudo needs-restarting -r; echo "exit=$?"
```

- "This is `rhel-dev-01.trading-demo.chrislab.dev` — the same system you just saw in Insights. Same hostname, same node."
- "The CVE is right there — CVE-2026-43037, CVSS 8.8, sitting unpatched. The permanent fix is a package upgrade that requires a reboot to take effect."

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
  - Walk through the 10 nodes left to right:
    1. **Assess & SBOM Baseline** — "First, we scan every node. Kernel version, outstanding CVEs, whether a package update is available, and capture a Software Bill of Materials."
    2. **Open Incident** — "Only after assessment do we raise ServiceNow incidents — one for Dev, one for Prod — with the actual findings baked in."
    3. **Pre-check All Hosts** — "Safety checks. Disk space, memory, critical services running, no unexpected pending reboot. Make sure it's safe to patch."
    4. **Canary: Apply Full Patch** — "Dev goes first. CVE-targeted `dnf` upgrade, then a controlled reboot so the fix is active."
    5. **Canary: Post-checks** — "Validate the canary: no pending reboot, CVE cleared from advisories, services healthy, SBOM diff captured."
    6. **Open Emergency CR** — "Dev succeeded, so now we raise an emergency Change Request for production. Full ITIL justification, implementation plan, backout plan — including the expected reboot window."
    7. **Awaiting CR Approval** — "The workflow pauses here. It will not touch production until a human approves the CR in ServiceNow."
    8. **Apply Full Patch (Prod)** — "Once approved, production gets the same package upgrade + reboot."
    9. **Post-checks (Prod)** — "Same validation on production nodes."
    10. **Post-Implementation Review** — "Close out the ITIL records — resolve both incidents, move the CR to review, and mark the CVE as Resolved in Insights. No follow-up kernel CR — this remediation *is* the permanent fix."
  - "So the key thing here — dev is fully automated. Production has a governance gate. The human only needs to make one decision: approve or reject the Change Request. And unlike a live kpatch stop-gap, when this finishes the CVE is permanently remediated."

---

## Act 3: The Event

**Narrative:** "Now let's trigger it. In a real environment, Insights would fire an event the moment a new critical CVE is detected. We're going to simulate that."

### 3.1 — Simulate the CVE event

- Switch to the **local terminal**

```bash
./scripts/simulate-cve-event.sh
```

- The script will echo the payload details — pause here and talk through it:
  - "This is a Critical CVE, CVSS 8.8, detected on rhel-dev-01"
  - "Insights has sent this event to Event-Driven Ansible"
  - "EDA is now going to trigger our entire response workflow — no human intervention needed to start the process"

### 3.2 — Show the workflow kick off

- Switch to **AAP — Workflow Jobs**
  - A new "Trading Service: CVE Full Patch Response" workflow should appear within seconds
  - Click into it to show the **Visualizer**
  - "You can see it's already executing the same 10-node workflow we just walked through."

---

## Act 4: The Automated Response

**Narrative:** Walk through each phase as it executes. Dev canary includes a reboot, so expect a short wait — use the time to explain what each step is doing.

### 4.1 — Assess & SBOM Baseline

- Watch "Assess & SBOM Baseline" complete in the **Workflow Visualizer**
- "It's already scanned all 6 nodes and captured the baseline. Now it knows exactly what's exposed and that a reboot will be required for the permanent fix."

### 4.2 — Open Incident

- As soon as "Open Incident" completes, switch to **ServiceNow**
- Show the **Dev service** — a new Incident has appeared
  - "Now that we know which machines are exposed, the automation opens an incident with the full assessment findings baked in. Not a generic alert — it contains the kernel version, CVE list, reboot-required flag, and SBOM confirmation."
- Show the **Prod service** — another Incident
  - "Both environments get their own incident — proper ITIL governance."
- Once complete, go to **ServiceNow** and check the dev incident's **Work Notes**
  - "See? The automation has written the assessment findings directly into the incident. Every node, kernel version, CVE count — full transparency."

### 4.3 — Pre-checks & Dev Canary

- "Pre-checks confirm it's safe to patch — disk space, memory, services all healthy."
- "Now watch — it's applying the full package upgrade to dev first, then rebooting. This is our canary. Brief reboot window per host, permanent fix."
- After "Canary: Post-checks" completes:
  - "Post-checks confirmed: no pending reboot, CVE cleared from outstanding advisories, all services healthy, Trading Service is UP. SBOM diff captured — we know exactly what changed."

### 4.4 — Open Emergency CR + Approval

- The workflow reaches **"Open Emergency CR"** then pauses at **"Awaiting CR Approval"**
- Switch to **ServiceNow**
  - Show the new **Change Request** — it's an emergency CR (`Emergency full patch …`)
  - "The automation has raised a governed change request. Dev canary passed, but production needs human approval. This is the governance gate — and the CR already documents the reboot impact."
  - Show the CR details — linked to the Trading Service, canary evidence in the description
- **Approve the CR** in ServiceNow and click **Implement**
  - "The moment we approve and implement, EDA picks up the state change and automatically approves the paused workflow node in AAP."
- Switch back to **AAP** — the workflow should resume within ~10 seconds

### 4.5 — Production Deployment

- "Now it's applying the same full patch + reboot to production — governed, approved, audited."
- Watch "Apply Full Patch (Prod)" and "Post-checks (Prod)" complete
  - "Same post-checks on prod — no pending reboot, services healthy, Trading Service UP."

### 4.6 — Post-Implementation Review

- The final node runs
- Switch to **ServiceNow**:
  - **Dev Incident** — resolved, work notes show the full timeline
  - **Prod Incident** — resolved, same audit trail
  - **Emergency CR** — moved to Review, detailed review notes with SBOM diff
  - **No follow-up CR** — "Unlike a live kpatch mitigation, this *is* the permanent fix. The automation does not schedule a second kernel change — the package upgrade and reboot already completed the remediation."

---

## Act 5: The Proof

**Narrative:** "Let me prove the vulnerability is actually gone."

### 5.1 — Prove it on the node

- SSH back into `rhel-dev-01.trading-demo.chrislab.dev`

```bash
# Kernel may have changed after the upgrade + reboot
uname -r

# No pending reboot remaining
sudo needs-restarting -r; echo "exit=$?"

# CVE should no longer appear in outstanding kernel advisories
sudo dnf updateinfo info --security kernel 2>/dev/null | grep -A2 "CVE-2026-43037" || echo "CVE cleared from outstanding advisories"
```

- "Before the workflow, that CVE was sitting in `dnf updateinfo`. Now it's gone from the outstanding advisories, and `needs-restarting -r` is clean — the reboot already activated the fix."
- "This is a permanent remediation: packages updated, systems rebooted, vulnerability closed."

### 5.2 — Insights resolution

- Navigate to **Red Hat Insights — Vulnerabilities** and refresh
- "Notice the CVE status has been updated to **Resolved** for the demo systems. The Post-Implementation Review step didn't just close out ServiceNow — it called the Red Hat Insights API (`status_id: 4`) to mark the CVE as Resolved."
- "That matters for two reasons:"
  1. "It stops Insights from re-firing vulnerability events for this CVE — no duplicate workflows."
  2. "It gives your security team an accurate picture: the vulnerability is permanently remediated, not temporarily mitigated."
- Show the incident work notes — they include the Insights status update confirmation
- "Because the RPM set actually changed, Insights scanners will also clear the advisory as they re-check. The API status update closes the loop immediately so the SOC isn't waiting on the next scan cycle."

### 5.3 — The full audit trail

- Walk through ServiceNow:
  - **Incidents**: Dev and Prod — both resolved with detailed work notes showing the full timeline
  - **Emergency CR**: In Review state — ready for human sign-off
  - **Follow-up CR**: none — permanent fix already applied
- "Every step from detection to resolution, including the Insights API update, is documented in ServiceNow. This is a complete ITIL audit trail, generated automatically."

---

## Key Talking Points

Use these throughout the demo as the workflow progresses:

- **Speed**: "From CVE detection to full remediation across 6 nodes — minutes, not days."
- **Permanent fix**: "Package upgrade + reboot is the lasting remediation. No temporary mitigation, no second change request for the 'real' kernel update."
- **Controlled reboot**: "We accept a brief, orchestrated reboot window per host — planned, audited, and staged canary-first."
- **Governance**: "Every step is audited. Incidents, change requests, SBOM diffs, work notes — all automated, all ITIL-compliant."
- **Dev-first canary**: "We never touch production without proving it works on dev first — including surviving the reboot."
- **Human in the loop**: "Production deployment requires an approved change request. The automation pauses and waits — governance is not optional."
- **SBOM**: "We capture a full Software Bill of Materials before and after every patch. You know exactly what changed on every system."
- **Insights closed-loop**: "The automation doesn't just patch — it tells Red Hat Insights the CVE is Resolved. No duplicate events, no manual status updates, no noise for the SOC team."
  - **If asked "how is this different from kpatch?":** "Kpatch patches the running kernel in memory without a reboot — great for immediate risk reduction, but Insights OVAL still sees the old RPM until you do a full update. This workflow changes the packages and reboots, so the fix is permanent and the scanner path clears naturally. We also mark Insights Resolved immediately so operations doesn't wait on the next check-in."
- **Scale**: "This same workflow works whether you have 6 nodes or 6,000. The only thing that changes is how many hosts Ansible targets."

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Workflow doesn't start after simulate script | Check EDA activation `Full Patch CVE Response` is running and event stream is not in test mode |
| ServiceNow CR approval doesn't resume workflow | Check EDA rulebook is polling (look at EDA audit logs). Verify `remote_servicenow_timezone` matches your PDI. Filter expects `Emergency full patch` |
| Insights not showing systems | Run `sudo insights-client --status` on a node. If not reporting, run `sudo insights-client` to force upload |
| SSH timeouts to nodes | Your local IP may have changed — update the AWS Security Group |
| Post-checks fail on Trading Service service check | This is simulated and always returns UP. If it fails, check the node is reachable |
| Host still reports pending reboot | Wait for reboot to finish, then re-check `needs-restarting -r` |
| Wrong fleet / colliding with kpatch demo | Confirm EC2 tag `demo=patching-full` and inventory `Full Patching Demo` |

---

## Reset for Next Demo

```bash
# Clean ServiceNow records
./scripts/cleanup-servicenow.sh --all

# Reprovision fresh EC2 instances (full reset)
./scripts/reset-demo.sh

# Re-run CaC if needed
source .env && ansible-playbook ansible_deployment/cac/apply.yml
```
