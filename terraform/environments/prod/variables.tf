variable "aws_region" {
  type    = string
  default = "us-east-1"
}

variable "project" {
  type    = string
  default = "street-bites"
}

variable "s3_bucket_name" {
  description = "Globally-unique bucket name for the app's public storage disk (truck images, cached map PNGs)."
  type        = string
}

variable "image_tag" {
  description = "ECR image tag to deploy. release-deploy.yml passes the GitHub release tag; defaults to a placeholder so `terraform plan`/`apply` don't require it for infra-only changes."
  type        = string
  default     = "initial"
}

variable "db_name" {
  type    = string
  default = "steet_bites"
}

variable "db_username" {
  type    = string
  default = "steet_bites"
}

variable "reverb_port" {
  type    = number
  default = 8080
}

variable "domain" {
  description = "The site's domain (DNS hosted at Cloudflare — see domain.tf). Drives APP_URL (https://www.<domain>), the ACM cert, the ws.<domain> Reverb host, and the SES domain identity mail sends from (noreply@<domain>). Not sensitive, so the prod value is the default — no tfvars/CI variable needed."
  type        = string
  default     = "street-bites.org"
}

variable "admin_emails" {
  description = "Comma-separated allowlist of content-moderation admins (ADMIN_EMAILS → config/admin.php → User::isAdmin(); the /admin/trucks queue). Not sensitive, so the prod value is the default — no tfvars/CI variable needed. Add ops addresses here without a code change."
  type        = string
  default     = "sayge.dev121@gmail.com"
}
