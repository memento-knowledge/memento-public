# Customer-Owned Model Access (BYOM) Setup

**Memento Knowledge** (memento-knowledge.com) — Last updated: 2026-11-02

You create an IAM role in your own AWS account. Memento's worker assumes it, for short periods, to call Amazon Bedrock as that role. The model calls are authorised, run, logged and billed in your account. Memento keeps no standing credentials there.

The role must be in place, and validated by Memento, before BYOM is switched on for your environment.

This guide covers two **geographies**, **US** and **EU**. Your account team tells you which one applies to your environment. Follow the US or EU column or block wherever the guide shows both, and ignore the other. Everything else is the same for both.

## What BYOM covers

The model calls Memento makes on your behalf: agent reasoning (chat models) and, if you choose, the embeddings behind search (Amazon Titan Text Embeddings V2). Chat and embeddings are switched on separately, through the same role.

It changes **where a model call runs and who is billed**. It does not move Memento's compute, workflow history or databases (see "What stays where it is" below).

## If your access stops working

Memento never falls back to its own Bedrock account. If the role is removed, the external ID stops matching, a policy is narrowed, or your quota runs out, affected work pauses and is routed to human review. The failure is recorded and your account team is told.

This is deliberate: your instruction to keep inference in your account is not overridden by a fallback.

## Your geography

| | **US** | **EU** |
|---|---|---|
| Bedrock region (where Memento's calls start) | `us-east-1` | `eu-west-1` (Ireland) |
| Inference profiles Memento uses | `us.` | `eu.` |
| Regions a call can be served from | `us-east-1`, `us-east-2`, `us-west-2` | `eu-west-1` (Ireland), `eu-west-3` (Paris), `eu-central-1` (Frankfurt), `eu-north-1` (Stockholm), `eu-south-1` (Milan), `eu-south-2` (Spain) |
| Titan Text Embeddings V2 region | `us-east-1` | `eu-west-1` |
| Profiles never used | `global.` | `global.` and `us.` |

Memento supports only the starting region shown for each geography.

**For EU, one point to know up front.** `eu-south-1` and `eu-south-2` are AWS opt-in regions: if they are not enabled in your account, tell your account team before you start.

## Requirements

### Your AWS account

- An AWS account that you own and control. It must not be an account Memento controls. The Bedrock usage is billed to it.
- Someone with permission to create IAM roles and policies in it, and to enable Bedrock models.
- If your account sits in an AWS Organization, any service control policies (SCPs) must allow what is listed under "Organisation policies" below. This is the most common cause of setup failures, and it is especially likely when your organisation restricts regions.

### Region

- Your Bedrock region for BYOM is the starting region for your geography in the table above. Memento's calls start there.
- Bedrock chooses the serving region for each request from the regions in the table, and Memento cannot choose it. **All of them must therefore be usable from your account.**
- Memento never uses the `global.` profiles, which can serve a call from anywhere. For EU it never uses the `us.` profiles either.

### Models

Memento runs agents on three model tiers and picks the model for each task. Enable **every model in this table** in your Bedrock region. Memento checks each model it may route to, and a missing one is reported as unavailable:

| Tier | Models Memento may use (newest first) |
|---|---|
| Haiku | Claude Haiku 5.5, Claude Haiku 4.5 |
| Sonnet | Claude Sonnet 5.5, Claude Sonnet 5, Claude Sonnet 4.6 |
| Opus | Claude Opus 5.5, Claude Opus 5, Claude Opus 4.6 |

Model availability changes over time. Your account team sends you the exact list for your environment, and tells you when it changes.

You also need **Amazon Titan Text Embeddings V2** (`amazon.titan-embed-text-v2:0`) in your Bedrock region, even if you start with chat only: Memento's validation before BYOM is switched on checks it. Titan serves from the region you call and has no cross-region profile. Amazon's own models do not need a separate agreement.

Some models in Bedrock (Anthropic's, for example) ask for a one-time use-case form and an agreement acceptance the first time an account uses them. These are completed in the Bedrock console (Step 1), and only someone in your account can do it.

### Quotas

Enabling a model does not guarantee capacity. A newly opened Bedrock account can sit at very low default limits. We have seen Claude Haiku 4.5 limited to about 50 requests per minute in a new account, and Titan Text Embeddings V2 at 600 against 6,000 in an established one, with the models showing as "available" the whole time. Plan for this in Step 2.

For the newest models, AWS may not publish a requests-per-minute quota at all. Memento's validation then reports that model's capacity as "not verifiable". That does not block setup, because validation also makes a live call to each tier's default model.

### Organisation policies

If your organisation uses SCPs or permission boundaries, they must not block:

- `sts:AssumeRole` into the role you create in Step 4, from the Memento role ARN(s) you are given
- Bedrock invoke and discovery actions, `iam:SimulatePrincipalPolicy` on the role itself, and read-only `servicequotas:ListServiceQuotas` / `servicequotas:ListAWSDefaultServiceQuotas` (all listed in Step 3), in **every region listed for your geography**. An SCP that allows only some of them (for example only `eu-west-1`, or only `us-east-1`) refuses the calls Bedrock routes to the other regions, intermittently and without a clear error. If your SCP restricts regions, make sure calls to global services such as IAM are exempt as well.
- creating an IAM role named `MementoInferenceAccess` at the root path

### Network

No inbound access, VPN, peering, PrivateLink or IP allow-listing is needed. Memento calls Bedrock's public endpoint for your region (for example `bedrock-runtime.eu-west-1.amazonaws.com`) from its own AWS environment. Do not add `aws:SourceIp`, `aws:SourceVpc` or `aws:SourceVpce` conditions to the role or its policies: Memento's calls will not match them and will be refused.

### What you receive from Memento

Ask your Memento account team for these **for the environment being set up**. They differ per environment.

| Value | Looks like |
|---|---|
| **Memento role ARN** | `arn:aws:iam::123456789012:role/memento-…-worker`. The Memento identity your role will trust. If your environment also includes Memento's delegated coding agents, you receive a second ARN (`…-pi-bridge`) and your role must trust both. |
| **External ID** | A long random string, generated by Memento for you. Used in Step 4. Treat it like a credential: don't post it in a public ticket or chat. |
| **Model list** | The models to enable for this environment (see "Models"). |

### What you tell Memento

| Value | Notes |
|---|---|
| **Your AWS account ID** | The account that will own the role and the Bedrock usage. |
| **Your Bedrock region** | Confirm the starting region for your geography (see "Your geography"). |
| **Any region or Bedrock restrictions** | Organisation policies that deny regions or Bedrock actions. For EU, also whether `eu-south-1` and `eu-south-2` are enabled. |
| **Whether to route embeddings** | Chat and embeddings are switched on separately. |

## Step 1: Enable the models

In the [Amazon Bedrock console](https://console.aws.amazon.com/bedrock/), in your Bedrock region:

1. Enable the chat models your account team lists for your environment. Complete the use-case form and accept the model agreement where Bedrock asks for them.
2. Enable **Titan Text Embeddings V2** (`amazon.titan-embed-text-v2:0`) in the same region, whether or not you route embeddings through BYOM yet.

## Step 2: Check your quotas

1. Open **Service Quotas** → **Amazon Bedrock** in your region.
2. Compare the request and token limits for the models above with your expected load. Your account team can estimate this for you.
3. If they are too low, open an AWS Support case asking for **the model's runtime quota to be raised**. Memento cannot open this case on your behalf.

Memento can read your Service Quotas (the role in Step 3 includes read-only access to them), and Validation (Step 6) reports a model whose requests-per-minute limit is below the level Memento needs, so a low limit is found before it is felt under load. Only you can raise the limit. Validation also makes live model calls and reports any throttling it meets.

## Step 3: Create the permission policy

### First, confirm which regions can serve a call (read-only)

This check confirms, in your account, the regions a call can be served from. It sends no traffic and changes nothing. Run it as an administrator (not as the new role) once the models from Step 1 are enabled. Use the command for your geography:

```bash
# US
aws bedrock get-inference-profile --region us-east-1 \
  --inference-profile-identifier us.anthropic.claude-sonnet-5-5

# EU
aws bedrock get-inference-profile --region eu-west-1 \
  --inference-profile-identifier eu.anthropic.claude-sonnet-5-5
```

You should see `"type": "SYSTEM_DEFINED"` and a list of model ARNs, each in one of the regions listed for your geography and none outside it. Send the output to your account team. It contains no secrets. If it lists a region that is not in the policy below, or one that is not usable in your account, tell your account team before you continue.

### The policy

Create a customer-managed IAM policy named `MementoInferenceAccess-Permissions` (any name is fine; it is attached to the role in Step 4). It lets the assumed session call the specific models Memento uses, read model availability and quota limits, and check its own permissions. Nothing else.

Paste the policy for your geography **whole**, replacing `YOUR_ACCOUNT_ID` (twice). All five statements are needed; the reasons are below the policies.

**US**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowInvokeModels",
      "Effect": "Allow",
      "Action": ["bedrock:InvokeModel", "bedrock:InvokeModelWithResponseStream"],
      "Resource": [
        "arn:aws:bedrock:us-east-1:YOUR_ACCOUNT_ID:inference-profile/us.anthropic.claude-*",
        "arn:aws:bedrock:us-east-1::foundation-model/anthropic.claude-*",
        "arn:aws:bedrock:us-east-2::foundation-model/anthropic.claude-*",
        "arn:aws:bedrock:us-west-2::foundation-model/anthropic.claude-*"
      ]
    },
    {
      "Sid": "AllowInvokeTitanEmbeddings",
      "Effect": "Allow",
      "Action": ["bedrock:InvokeModel"],
      "Resource": ["arn:aws:bedrock:us-east-1::foundation-model/amazon.titan-embed-text-v2:0"]
    },
    {
      "Sid": "AllowModelDiscovery",
      "Effect": "Allow",
      "Action": [
        "bedrock:GetFoundationModelAvailability",
        "bedrock:ListFoundationModels",
        "bedrock:ListInferenceProfiles",
        "bedrock:GetInferenceProfile"
      ],
      "Resource": "*"
    },
    {
      "Sid": "AllowPolicySimulationOnOwnRole",
      "Effect": "Allow",
      "Action": ["iam:SimulatePrincipalPolicy"],
      "Resource": "arn:aws:iam::YOUR_ACCOUNT_ID:role/MementoInferenceAccess"
    },
    {
      "Sid": "AllowQuotaRead",
      "Effect": "Allow",
      "Action": ["servicequotas:ListServiceQuotas", "servicequotas:ListAWSDefaultServiceQuotas"],
      "Resource": "*"
    }
  ]
}
```

**EU**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowInvokeModels",
      "Effect": "Allow",
      "Action": ["bedrock:InvokeModel", "bedrock:InvokeModelWithResponseStream"],
      "Resource": [
        "arn:aws:bedrock:eu-west-1:YOUR_ACCOUNT_ID:inference-profile/eu.anthropic.claude-*",
        "arn:aws:bedrock:eu-west-1::foundation-model/anthropic.claude-*",
        "arn:aws:bedrock:eu-west-3::foundation-model/anthropic.claude-*",
        "arn:aws:bedrock:eu-central-1::foundation-model/anthropic.claude-*",
        "arn:aws:bedrock:eu-north-1::foundation-model/anthropic.claude-*",
        "arn:aws:bedrock:eu-south-1::foundation-model/anthropic.claude-*",
        "arn:aws:bedrock:eu-south-2::foundation-model/anthropic.claude-*"
      ]
    },
    {
      "Sid": "AllowInvokeTitanEmbeddings",
      "Effect": "Allow",
      "Action": ["bedrock:InvokeModel"],
      "Resource": ["arn:aws:bedrock:eu-west-1::foundation-model/amazon.titan-embed-text-v2:0"]
    },
    {
      "Sid": "AllowModelDiscovery",
      "Effect": "Allow",
      "Action": [
        "bedrock:GetFoundationModelAvailability",
        "bedrock:ListFoundationModels",
        "bedrock:ListInferenceProfiles",
        "bedrock:GetInferenceProfile"
      ],
      "Resource": "*"
    },
    {
      "Sid": "AllowPolicySimulationOnOwnRole",
      "Effect": "Allow",
      "Action": ["iam:SimulatePrincipalPolicy"],
      "Resource": "arn:aws:iam::YOUR_ACCOUNT_ID:role/MementoInferenceAccess"
    },
    {
      "Sid": "AllowQuotaRead",
      "Effect": "Allow",
      "Action": ["servicequotas:ListServiceQuotas", "servicequotas:ListAWSDefaultServiceQuotas"],
      "Resource": "*"
    }
  ]
}
```

The regions in the policy are the ones AWS publishes for the profiles today. If AWS changes them, Memento's validation reports it and your account team tells you what to add.

### Why each statement is needed

| Statement | Why Memento needs it |
|---|---|
| `AllowInvokeModels` | The model calls themselves. An inference profile can serve a call from any of the regions listed for your geography, so the policy must allow the chat models in all of them. Bedrock picks the serving region per request, so a policy that grants only one region fails, and can fail on some calls and not others. This is how Bedrock evaluates cross-region profiles, not a Memento choice. |
| `AllowInvokeTitanEmbeddings` | The embedding calls behind search. Titan has no cross-region profile, so only your Bedrock region is listed. Include it even if you start with chat only: Memento's validation before BYOM is switched on checks both chat and embeddings, so a missing Titan statement or an unenabled Titan model is reported as a failure. |
| `AllowModelDiscovery` | Lets Memento check that a model is available in your account and read which regions a profile can use, before any call is made. These actions have no resource type in Bedrock's IAM model, so `Resource: "*"` is required for them. They cannot invoke anything. |
| `AllowPolicySimulationOnOwnRole` | Lets Memento ask AWS whether *this role's own policy* permits the models in each of the regions, without sending any traffic. Without it Memento cannot confirm the policy covers every region the profile can use, and validation fails at the destination-regions check. It is read-only and scoped to this one role: it does not let Memento read or simulate anything else in your account. |
| `AllowQuotaRead` | Lets Memento read your Bedrock service quotas (read-only; it can list quotas and cannot change them) and check that each model's requests-per-minute limit is high enough, before you depend on it and before Memento moves you to a newer model. A new account can start at about 50 requests per minute. These actions have no resource type, so `Resource: "*"` is required. Without them the check cannot read your limits, and a model upgrade for your environment cannot be confirmed. |

Simulation only reads the role's own policies. The live model calls in Step 6 remain the decisive check.

## Step 4: Create the role

The role **must be named `MementoInferenceAccess` and must be at the root path** (`/`). Do not create it under a path prefix such as `/memento/` or `/service-role/`, even if your naming standard asks for one. Memento's side is scoped to the exact ARN `arn:aws:iam::YOUR_ACCOUNT_ID:role/MementoInferenceAccess`, and a path-prefixed role has a different ARN that Memento cannot assume. If a naming SCP blocks the name, tell your account team and do not work around it.

1. Open **IAM** → **Roles** → **Create role** → **Custom trust policy**.
2. Paste the trust policy below, replacing `MEMENTO_WORKER_ROLE_ARN` and `MEMENTO_EXTERNAL_ID` with the values you were given. If you were given a second role ARN, trust both: use the list form shown after the policy.
3. Attach `MementoInferenceAccess-Permissions` from Step 3.
4. Name the role `MementoInferenceAccess`. Leave the maximum session duration at the default (one hour); Memento requests sessions of 15 minutes.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowMementoWorkerAssume",
      "Effect": "Allow",
      "Principal": { "AWS": "MEMENTO_WORKER_ROLE_ARN" },
      "Action": "sts:AssumeRole",
      "Condition": { "StringEquals": { "sts:ExternalId": "MEMENTO_EXTERNAL_ID" } }
    }
  ]
}
```

If you were given two Memento role ARNs, replace the `Principal` line with a list of both. The external ID is the same for both:

```json
"Principal": { "AWS": ["MEMENTO_WORKER_ROLE_ARN", "MEMENTO_SECOND_ROLE_ARN"] },
```

**Why an external ID.** It stops anyone else who learns your role ARN from getting Memento to act on their behalf, because the role only trusts a request that carries the value only Memento and you hold. Memento generates it, rather than you choosing one, so it is never weak or reused.

## Step 5: Send the role ARN to Memento

Send the role ARN (`arn:aws:iam::YOUR_ACCOUNT_ID:role/MementoInferenceAccess`) to your account team, with your region. The ARN is an identifier, not a secret, so email or a ticket is fine. **Do not send the external ID back.** Memento already holds it.

## Step 6: Memento validates it

Memento validates the role in these checks:

1. The ARN is well formed, at the root path, and in an account other than Memento's.
2. The role can be assumed with the correct external ID, from each Memento role you trust.
3. **The role cannot be assumed with a wrong or missing external ID.** This proves the condition in your trust policy is actually enforced.
4. The models are available in your account.
5. The role's policy permits the models in every region of your geography (checked by policy simulation, see Step 3), and every region the profile can use belongs to that geography.
6. A live chat call succeeds, and a live embedding call succeeds (the acceptance check always includes embeddings, even if you start with chat only). Any throttling met during these calls is reported.

Check 3 is a deliberate refusal test. Whenever it runs, it shows up in your CloudTrail as failed `AssumeRole` events from the Memento role(s) you trust. That is expected, not an attack. Check 3 belongs to the acceptance check that Memento runs before BYOM is switched on; the scheduled repeat checks below do not include it.

Your account team tells you the result. Chat and embeddings are switched on separately, by Memento, after validation passes. You do not toggle them yourself.

After BYOM is on, Memento repeats the validation on a schedule and alerts your account team if it starts failing, so a change on your side is noticed quickly.

## What stays where it is

BYOM moves the model call, not Memento's processing:

- Memento's workers, workflow history and databases stay in the Memento region where your environment runs today. That is true for both geographies, including EU: **EU geography places the model calls in EU regions, and does not make Memento's processing EU-resident.** A Memento environment hosted in the EU is not part of this configuration.
- The **model call** is served by Bedrock in your account, in the regions for your geography. Prompts and results still pass through Memento's systems, so BYOM does not by itself make your environment's data residency different from today. If you have a residency requirement beyond the model call, raise it with your account team.

## Audit and logging

- Every role assumption appears in your CloudTrail as `sts:AssumeRole` by the Memento role(s) you trust.
- **Recommended during validation:** turn on Bedrock model-invocation logging in your account (Bedrock console → Settings). It records every model call, with its input and output if you choose, in a CloudWatch log group or S3 bucket that you control.
- The region that served an individual call is not shown in CloudWatch metrics, which carry the profile name. AWS documents the regions each profile can use, and Step 3 shows them for your account. If you need per-call evidence, use invocation logging.
- Memento records which account served each call.

## Cost

AWS bills the model usage to **your** account, not Memento's, at your own pricing and any discounts you hold. Usage figures inside Memento are estimates at list price, and they will not match your bill exactly.

## Model upgrades

Memento moves a BYOM environment to a new model only after the new model passes a live check **in your account**. A model that shows as available can still have no runtime quota in your account, and Memento cannot request that for you. If that happens, you stay on your last working model until you have the quota, and Memento tells you what to raise.

When a new model is added to your list, your account team tells you so you can enable it in Bedrock (Step 1) before the move.

## Revoking access

You have two controls, and they act differently.

**Stop new access: edit the trust policy (or delete the role).** New requests from Memento fail within seconds. In our testing, changing the trust policy's external ID blocked new requests in under 20 seconds; we did not time deleting the role, which should behave the same way. IAM is eventually consistent, so treat it as seconds, not instant. A session Memento already holds is **not** ended by a trust-policy edit. It keeps working until it expires, at most 15 minutes after it was issued, and it cannot be extended.

**Stop access that is already in flight: revoke active sessions.** In IAM, open the role and choose **Revoke active sessions**. This attaches an `AWSRevokeOlderSessions` deny that blocks every session issued before that moment. In our testing it stopped a live session within 19 seconds, and it did not block sessions issued afterwards.

Use both if you need access to end now. If you only want to pause Memento briefly, the revoke alone is enough: later sessions still work, so it will not hold Memento out.

Editing only the permission policy also stops model calls, but it is easier to do by mistake than deleting the role, so tell us first.

**Tell your account team before you revoke.** Memento notices from failing calls, and routes affected work to human review.

To restore access, recreate the role with the same name, the same external ID and the same trust, then tell your account team, who will revalidate. If you used **Revoke active sessions**, remove the `AWSRevokeOlderSessions` policy from the role too, once its date has passed, or leave it: it only blocks sessions issued before its timestamp.

## Rotating the external ID

Rotation is coordinated, and your account team starts it. Memento gives you a new value. You then set the `sts:ExternalId` condition in your trust policy to a **list of both the old and the new value**, so that Memento can switch over without a failed call:

```json
"Condition": { "StringEquals": { "sts:ExternalId": ["OLD_EXTERNAL_ID", "NEW_EXTERNAL_ID"] } }
```

Tell your account team when that edit is saved. Memento then switches to the new value. Once your account team confirms the switch, remove the old value from the list and confirm that to your account team in writing. If you can only replace the single value, agree a time with your account team first: calls fail between your edit and Memento's switch, which takes a few minutes.

## Security notes

- The trust policy names only the specific Memento role(s) you were given and requires your external ID. No other Memento identity can assume your role.
- The permission policy allows only the models above, read-only model discovery, read-only Service Quotas access, and policy simulation on this one role. The role cannot read your data, other services or other accounts.
- Memento's side can assume only your named role, and nothing else in your account.

## Troubleshooting

| Symptom | What to do |
|---|---|
| Memento says the ARN is invalid | Check it is `arn:aws:iam::ACCOUNT:role/MementoInferenceAccess`, with no path prefix and no typo in the account ID |
| Memento says the role is in a Memento account | BYOM needs your own AWS account, not one Memento controls |
| Validation reports AccessDenied on assume | The trust policy principal or external ID does not match what we gave you, or the role ARN is wrong. AWS returns the same message for all three, so neither of us can tell which from the error. Check each against the values for **this** environment, with no stray spaces or quotes |
| Validation reports the refusal test did not refuse | The `sts:ExternalId` condition is missing or uses `StringLike` with `*`. Restore the exact `StringEquals` condition |
| AccessDenied on a model call | The permission policy does not cover the model, profile or region. Compare it to Step 3 |
| Some calls succeed and some are refused, with no pattern | Bedrock is routing some calls to a region that your policy or an SCP does not allow. For EU this is often `eu-south-1` or `eu-south-2` (not enabled in the account, or missing from an SCP's allowed regions). Compare the output of the Step 3 check with the policy and your SCPs |
| Validation says the quota could not be verified, or that the role may not call a Service Quotas action | Add the `AllowQuotaRead` statement from Step 3 exactly as written. If it is present, check that no SCP blocks `servicequotas:*` read actions, then tell your account team |
| Validation says a model's capacity is "not verifiable" but the live call succeeded | AWS publishes no requests-per-minute quota for that model. This does not block setup |
| Validation reports the role may not call `iam:SimulatePrincipalPolicy` | Add the `AllowPolicySimulationOnOwnRole` statement from Step 3 exactly as written, with your account ID in the role ARN |
| A model call fails with `ValidationException` rather than `AccessDenied` | The model itself is not usable in your account (for example it needs an agreement or a setting AWS has not enabled for you). Your role and policies are fine; check the model's status in the Bedrock console |
| The model shows as available but calls are refused or throttled | A quota problem (Step 2). Open an AWS Support case for the model's runtime quota |
| Calls fail in one region only | An organisation policy (SCP) is blocking the region or a Bedrock action. Check with whoever manages your AWS Organization |
| `MalformedPolicyDocument` when creating the role | Check the JSON is pasted whole and that the Memento role ARN(s) are the ones for **this** environment |
