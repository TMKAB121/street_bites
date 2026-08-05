variable "aws_region" {
  description = "AWS region for the state bucket, lock table, and IAM resources."
  type        = string
  default     = "us-east-1"
}

variable "project" {
  description = "Short project name, used as a resource-naming prefix (roles, tags)."
  type        = string
  default     = "street-bites"
}

variable "state_bucket_name" {
  description = "Globally-unique S3 bucket name for Terraform remote state. Must be set explicitly — bucket names collide across all of AWS, not just this account."
  type        = string
}

variable "lock_table_name" {
  description = "DynamoDB table name for Terraform state locking."
  type        = string
  default     = "street-bites-terraform-locks"
}

variable "github_org" {
  description = "GitHub org/user that owns the repo, used to scope the OIDC trust policy's `sub` claim."
  type        = string
}

variable "github_repo" {
  description = "GitHub repository name (without the org), used to scope the OIDC trust policy's `sub` claim."
  type        = string
  default     = "street_bites"
}

variable "github_owner_id" {
  description = <<-EOT
    Numeric, immutable GitHub ID of var.github_org. GitHub now issues OIDC
    subject claims with the owner's and repo's IDs appended
    ("repo:OWNER@<owner_id>/REPO@<repo_id>:ref:..."), so that renaming an
    account or repo cannot hand the role to whoever claims the freed name.
    The trust policies accept that form as well as the legacy name-only one.
    Read both with:
      gh api repos/OWNER/REPO --jq '{repo: .id, owner: .owner.id}'
  EOT
  type        = string
  default     = "6655240"
}

variable "github_repo_id" {
  description = "Numeric, immutable GitHub ID of var.github_repo — see var.github_owner_id for why both are needed."
  type        = string
  default     = "1274615786"
}

variable "github_main_branch" {
  description = "The branch terraform-apply.yml deploys from — used to scope the OIDC trust policy's `sub` claim. Deliberately `main`, not `develop` (this repo's GitHub default/integration branch): infra applies are gated behind promoting develop into main, not every merge to develop."
  type        = string
  default     = "main"
}
