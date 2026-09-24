# Customer-Managed Encryption Key (BYOK) Setup

**Memento Knowledge** (memento-knowledge.com) — Last updated: 2026-09-24

You create an AWS KMS key in your own AWS account. Memento uses it to encrypt your environment's data at rest, and gets access only through the key policy you attach. Memento never imports or copies your key material.

The key must be in place before your environment is created.

## What your key covers

Your Memento database (tickets, issues, knowledge graph, embeddings, backups), repository clone storage, agent workspace storage, the credentials for your database and integrations, and the payload of any scheduled process you configure (Operator-Configured Schedules).

Operational logs and processing records sit outside the database. They remain encrypted, under Memento-managed keys.

## The policy is attached in two steps

- **Phase A**, before provisioning — grants Memento's provisioning role access, so your environment can be created under your key.
- **Phase B**, after provisioning — grants your environment's worker role runtime access (decrypt only, no key management).

Two steps because KMS rejects a policy naming a principal that does not exist yet, and the worker role is created with your environment.

## One key per Memento environment

Each Memento environment runs in a separate AWS account, and a KMS key belongs to one account and one region. If you have two environments you maintain two keys, both permanently in use.

If your account team tells you your environment is scheduled to move to a different Memento account, you will need to create a second key there — a KMS key cannot move between accounts. You will get the new values in advance, and the existing key stays in use until the move completes.

## Prerequisites

Ask your Memento account team for these, **for the environment being set up** — they differ per environment.

**Verify before you trust.** Confirm these values through a channel you already trust — your named account contact, an existing email thread, or your onboarding ticket — not solely from an unsolicited email or a link. If anything about how you received an ARN feels off, confirm with Memento directly before attaching a policy that grants access to your key.

| Value | Looks like |
|---|---|
| **Provisioning role ARN** | `arn:aws:iam::123456789012:role/MementoCustomerProvisioner` |
| **Region** | `us-east-1` — create your key here |
| **Worker role ARN** | `arn:aws:iam::123456789012:role/memento-…-worker` — arrives after provisioning, for Step 4 |
| **Workload role ARN** | `arn:aws:iam::123456789012:role/memento-…-workload` — arrives with the worker role ARN, for Step 4. The role Memento's portal and event-consumer services run as; the portal stores your integration credentials under your key. |
| **Webhook role ARN** | `arn:aws:iam::123456789012:role/webhook-lambda-…` — arrives with the worker role ARN, for Step 4. The role that receives webhooks from your Git and ticketing systems and verifies their signatures against secrets stored under your key. |
| **Scheduler role ARN** | `arn:aws:iam::123456789012:role/memento-…-scheduler` — arrives after provisioning, for Step 4. A different role from the worker role above — only needed if you use Operator-Configured Schedules. |

## Step 1: Create the key

1. Open the [AWS KMS Console](https://console.aws.amazon.com/kms/) in your chosen AWS account, in the region above.
2. **Create key** → Key type **Symmetric**, Key usage **Encrypt and decrypt**.
3. Set an alias (e.g. `memento-encryption-key`) and complete the wizard per your own key-management standards.

Automatic annual rotation is supported and requires no action from Memento. Key administrator choices do not affect Memento's access.

## Step 2: Attach the provisioning statement (Phase A)

Add these statements to the key policy, replacing `MEMENTO_PROVISIONING_ROLE_ARN` with the ARN you were given. Paste it whole.

```json
{
  "Sid": "AllowMementoProvisioning",
  "Effect": "Allow",
  "Principal": { "AWS": "MEMENTO_PROVISIONING_ROLE_ARN" },
  "Action": [
    "kms:Encrypt",
    "kms:Decrypt",
    "kms:ReEncrypt*",
    "kms:GenerateDataKey*",
    "kms:DescribeKey"
  ],
  "Resource": "*"
},
{
  "Sid": "AllowMementoGrantsForAWSServices",
  "Effect": "Allow",
  "Principal": { "AWS": "MEMENTO_PROVISIONING_ROLE_ARN" },
  "Action": ["kms:CreateGrant", "kms:ListGrants", "kms:RevokeGrant"],
  "Resource": "*",
  "Condition": { "Bool": { "kms:GrantIsForAWSResource": "true" } }
}
```

`Resource: "*"` in a key policy means this key only. The grant statement is restricted to grants AWS services create on Memento's behalf.

## Step 3: Send the ARN to Memento

Send the key ARN (`arn:aws:kms:REGION:YOUR_ACCOUNT_ID:key/KEY_ID`) to your account team. We validate it before provisioning.

**Send the key ARN, not the alias ARN** — it must contain `:key/`. Alias ARNs are rejected, since an alias can be repointed at a different key.

The ARN is an identifier, not a secret, so email or a ticket is fine.

## Step 4: Attach the runtime statement (Phase B)

Your account team sends you the worker, workload and webhook role ARNs once your environment exists. Add all three statements to the same key policy:

```json
{
  "Sid": "AllowMementoRuntimeUse",
  "Effect": "Allow",
  "Principal": { "AWS": "MEMENTO_WORKER_ROLE_ARN" },
  "Action": ["kms:Decrypt", "kms:GenerateDataKey*", "kms:DescribeKey"],
  "Resource": "*"
},
{
  "Sid": "AllowMementoWorkloadRuntimeUse",
  "Effect": "Allow",
  "Principal": { "AWS": "MEMENTO_WORKLOAD_ROLE_ARN" },
  "Action": ["kms:Decrypt", "kms:GenerateDataKey*", "kms:DescribeKey"],
  "Resource": "*"
},
{
  "Sid": "AllowMementoWebhookDecrypt",
  "Effect": "Allow",
  "Principal": { "AWS": "MEMENTO_WEBHOOK_ROLE_ARN" },
  "Action": ["kms:Decrypt"],
  "Resource": "*"
}
```

Three statements because they are three different roles, each doing one job: the worker runs your processes; the workload role is what Memento's portal and event-consumer services run as, and the portal is what stores your integration credentials (Jira, GitHub, and so on) under your key when you connect them; the webhook role receives events from those systems and reads the same secrets to verify each event's signature — decrypt only, it never creates anything. Without the second statement, connecting an integration in the portal fails; without the third, every webhook from your systems is rejected.

If you plan to use Operator-Configured Schedules, also add the scheduler role statement (the ARN arrives at the same time as the worker role ARN):

```json
{
  "Sid": "AllowMementoSchedulerDecrypt",
  "Effect": "Allow",
  "Principal": { "AWS": "MEMENTO_SCHEDULER_ROLE_ARN" },
  "Action": ["kms:Decrypt"],
  "Resource": "*"
}
```

This is a separate statement because the scheduler role is a different principal from the worker role — without it, a scheduled process's payload is created successfully but silently fails to decrypt at fire time.

## Revoking access

**Disable the key.** That is the action that revokes.

Removing the `AllowMementoRuntimeUse` statement is not equivalent: your database and repository storage reach the key through AWS grants, which survive key-policy edits and keep working. Note also that a running database can continue serving for several minutes after the key is disabled.

**Disabling is reversible** — re-enable the key and tell us, and we will restart the affected systems. **Deletion is not.** AWS enforces a waiting period of 7 to 30 days, after which the key and everything encrypted under it are unrecoverable, including to Memento.

**Tell your account team before you disable or schedule deletion.** Memento gets no automated signal from either — we find out when operations start failing, and AWS gives us no visibility into a pending deletion.

## Security notes

Each statement names one specific Memento role for your environment. The runtime role can decrypt only — it cannot modify the key policy, create grants, or reach any other customer's data.

All KMS calls Memento makes against your key appear in your own CloudTrail.

## Troubleshooting

| Symptom | What to do |
|---|---|
| Memento says the ARN is invalid | An alias ARN was sent — send the one containing `:key/` |
| Memento says the key is in the wrong region | KMS keys are regional; create it in the region you were given |
| Memento says the key type is unsupported | Create a symmetric encrypt/decrypt key, not asymmetric or HMAC |
| `MalformedPolicyDocument` on a Phase B statement | Check the worker, workload, webhook or scheduler role ARN, whichever statement failed. If it is correct, wait a minute and retry — IAM is eventually consistent and briefly rejects policies naming a just-created role |
| Provisioning fails with a KMS access error | The Phase A statement names the wrong principal — check it against the ARN for **this** environment |

## License

MIT — see [LICENSE](../LICENSE) in the repository root.
