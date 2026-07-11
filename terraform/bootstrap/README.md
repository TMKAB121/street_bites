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
