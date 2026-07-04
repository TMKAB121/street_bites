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
   `s3_bucket_name`.
3. `terraform init && terraform plan` (or apply, if you're ready to actually
   provision — this creates real, billable AWS resources: NAT gateway, RDS,
   ElastiCache, ALB/NLB, Fargate tasks).

## Known limitations (see the plan's "Fast-follows")

- No custom domain/TLS — the ALB serves plain HTTP on its `*.elb.amazonaws.com`
  name (`SESSION_SECURE_COOKIE=false` in `main.tf` reflects this; flip it once
  HTTPS exists).
- Single-AZ RDS (`multi_az` defaults false in `modules/rds`).
- No SSM/bastion path into the private subnets for debugging RDS/Redis.
- `REVERB_HOST` for server-side broadcasting hairpins out through the public
  NLB rather than using internal service discovery — works, but AWS Cloud Map
  would be cheaper/faster.
