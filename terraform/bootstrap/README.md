# Bootstrap

Applied **once, by hand**, with your own AWS credentials — this is the one
Terraform root never run by CI. It solves the chicken-and-egg problem of
Terraform remote state (the bucket that holds state can't be managed by the
config that uses it as a backend) and creates the two IAM roles every GitHub
Actions workflow assumes.

## Apply

```bash
cd terraform/bootstrap
terraform init
terraform apply \
  -var="state_bucket_name=<globally-unique-bucket-name>" \
  -var="github_org=<your-github-org-or-username>"
```

For this repo specifically, that is:

```bash
terraform apply -var="state_bucket_name=streetbites-terraform-state" -var="github_org=TMKAB121"
```

Pass `github_org` in the account's **canonical casing** (`TMKAB121`, not
`tmkab121`). IAM string matching is case-sensitive and the trust policy derives
its lowercase variants from this value — passing lowercase silently produces a
policy that omits the uppercase spellings. See `local.github_sub_prefixes` in
`oidc.tf`.

### If CI fails with "Not authorized to perform sts:AssumeRoleWithWebIdentity"

GitHub changed the OIDC `sub` claim to append the owner's and repo's immutable
numeric IDs (`repo:OWNER@6655240/REPO@1274615786:ref:...`), so a trust policy
matching only the legacy name-only form stops authorizing every workflow. The
policies here accept both forms; if the IDs ever change (a repo transfer),
refresh them with:

```bash
gh api repos/OWNER/REPO --jq '{repo: .id, owner: .owner.id}'
```

and update `github_owner_id` / `github_repo_id` in `variables.tf`. Because CI
itself can't authenticate while this is broken, the fix **must** be applied by
hand from here — that's the chicken-and-egg this root exists to solve.

State for this root itself stays local (`terraform.tfstate` in this
directory) — commit it nowhere, keep it somewhere safe (or accept that
re-running `apply` is idempotent and cheap if it's lost; nothing here is
destroyed and recreated by re-applying).

## After applying

1. Copy the `state_bucket_name` and `lock_table_name` outputs into
   `terraform/environments/prod/backend.tf`.
2. Copy `gha_terraform_role_arn` and `gha_deploy_role_arn` into this repo's
   GitHub Actions **repository variables** (Settings → Secrets and variables →
   Actions → Variables): `AWS_TERRAFORM_ROLE_ARN` and `AWS_DEPLOY_ROLE_ARN`.
3. Create two GitHub **Environments** in the repo's settings (Settings →
   Environments):
   - `production` — required for `gha-deploy-role`'s OIDC trust policy; its
     `sub` claim only matches workflow runs that reference this environment
     (used by `release-deploy.yml`).
   - `production-infra` — used by `terraform-apply.yml` to gate infra changes
     behind a required reviewer before `terraform apply` runs. Add a required
     reviewer here if you want that approval gate; otherwise it applies
     automatically on every merge to `main` (not `develop` — see
     `github_main_branch` in `variables.tf`) that touches `terraform/**`.
