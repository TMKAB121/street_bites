# Bootstrap — account-level, outlives street_bites

> **The street_bites app was decommissioned on 2026-10-18** (its `environments/prod`
> stack is destroyed). This root is the one thing from that project that must
> **stay applied**: it owns three account-wide resources that the other projects
> on the same AWS account (`916554063143`) depend on.

| Resource | Depended on by |
|---|---|
| S3 bucket `streetbites-terraform-state` | Terraform remote state for **louisburg-community-choir** (`lccsite/prod/terraform.tfstate`), **sayge_dev** (`sayge-dev/prod/terraform.tfstate`), **divapets** (`divapets/prod/terraform.tfstate`) |
| DynamoDB table `street-bites-terraform-locks` | State locking for the same three backends |
| IAM OIDC provider `token.actions.githubusercontent.com` | Every sibling repo's GitHub Actions CI roles (they `data`-lookup it with `create_github_oidc_provider = false`); AWS allows only one per account |

All three carry `prevent_destroy`. **Never run a plain `terraform destroy` here**,
and never do a tag-based cleanup of `Project=street-bites` — the provider still
wears that tag.

The two street_bites CI roles (`street-bites-gha-terraform` / `-deploy`) and
their policies were removed with a targeted destroy on 2026-10-18; the code was
deleted to match. If this repo is ever archived, move this directory **and its
local `terraform.tfstate`** to a standalone account-bootstrap repo first.

## Apply (idempotent; re-run only to change the shared resources)

Applied **by hand**, with your own AWS credentials — never by CI. State for this
root stays local (`terraform.tfstate` in this directory, gitignored). Keep a copy
somewhere safe; if it's lost, `terraform import` the three resources above by
name/ARN rather than re-applying (an apply without state would try to create a
bucket that already exists).

```bash
cd terraform/bootstrap
terraform init
terraform plan -var="state_bucket_name=streetbites-terraform-state"   # expect: No changes
```

The state bucket is versioned (old versions expire after 90 days) and encrypted;
the lock table is on-demand billing. Together they cost well under $1/month.
