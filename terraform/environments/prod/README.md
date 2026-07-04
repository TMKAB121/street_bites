# prod environment

The only environment today. Provisions the VPC, ECS cluster + three Fargate
services (`web`, `reverb`, `queue-worker`), RDS MariaDB, ElastiCache Redis, the
ECR repo, the public S3 storage bucket, and the IAM task roles. Adding a
second environment later means copying this directory (new `backend.tf` state
key, new `terraform.tfvars`) — the modules themselves aren't environment-
specific.

## First-time setup

1. Run `terraform/bootstrap` first (see its README) and fill its
   `state_bucket_name`/`lock_table_name` outputs into `backend.tf` above.
2. `cp terraform.tfvars.example terraform.tfvars` and fill in a globally-unique
   `s3_bucket_name`. For CI, set it as the `S3_BUCKET_NAME` repository
   variable — terraform-plan.yml / terraform-apply.yml read it via `TF_VAR_*`.
   (The domain, `street-bites.org`, is the `domain` variable's default — no
   tfvars entry needed.)
3. Create a **Cloudflare API token** (Zone:Read + DNS:Edit, scoped to the
   street-bites.org zone) and export it as `CLOUDFLARE_API_TOKEN` locally; for
   CI add it as a repository **secret** of the same name. Terraform manages
   the zone's records (site CNAMEs, ACM validation, SES DKIM) through it —
   see `domain.tf`.
4. `terraform init && terraform plan` (or apply, if you're ready to actually
   provision — this creates real, billable AWS resources: NAT gateway, RDS,
   ElastiCache, ALB/NLB, Fargate tasks). The first apply pauses a few minutes
   inside `aws_acm_certificate_validation` while ACM sees the Cloudflare
   records and issues the cert — normal, not a hang. SES domain verification
   (DKIM) is also automatic; no click-a-link step.
5. While the AWS account is in the **SES sandbox**, mail is only delivered
   *to* verified addresses — request production access in the SES console
   (one-time; Terraform can't) or sign-up/2FA emails to real users silently
   fail.
6. Set the browser-side Reverb repository variables to the domain values —
   `VITE_REVERB_HOST=ws.street-bites.org`, `VITE_REVERB_PORT=443`,
   `VITE_REVERB_SCHEME=https` — and publish a release: they're baked into the
   JS bundle at image build (see release-deploy.yml), so an https page can
   open wss://.

## Known limitations (see the plan's "Fast-follows")

- Single-AZ RDS (`multi_az` defaults false in `modules/rds`).
- SES may still be in the sandbox (recipient addresses must be verified until
  production access is granted).
- Cloudflare is DNS-only (grey cloud) by design — TLS terminates at the
  ALB/NLB with the ACM cert. Proxying through Cloudflare's edge is a separate
  decision (see `domain.tf`).
- No SSM/bastion path into the private subnets for debugging RDS/Redis.
- `REVERB_HOST` for server-side broadcasting hairpins out through the public
  NLB rather than using internal service discovery — works, but AWS Cloud Map
  would be cheaper/faster.
