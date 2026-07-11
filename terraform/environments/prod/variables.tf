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
  default = "street_bites"
}

variable "db_username" {
  type    = string
  default = "street_bites"
}

variable "reverb_port" {
  type    = number
  default = 8080
}

variable "domain" {
  description = "The site's domain (DNS hosted at Cloudflare — see domain.tf). Drives APP_URL (https://www.<domain>), the ACM cert, the ws.<domain> Reverb host, and the from-address mail sends as (noreply@<domain>, verified as a sending domain in the Resend dashboard). Not sensitive, so the prod value is the default — no tfvars/CI variable needed."
  type        = string
  default     = "street-bites.org"
}

variable "resend_api_key" {
  description = "Resend API key (create it sending-only, scoped to the domain) backing MAIL_MAILER=resend — auth verification / 2FA / reset codes. Externally issued, so unlike the Terraform-generated secrets it must be supplied: terraform.tfvars locally, TF_VAR_resend_api_key (from the RESEND_API_KEY repo secret) in CI."
  type        = string
  sensitive   = true
}

variable "admin_emails" {
  description = "Comma-separated allowlist of content-moderation admins (ADMIN_EMAILS → config/admin.php → User::isAdmin(); the /admin/trucks queue). Not sensitive, so the prod value is the default — no tfvars/CI variable needed. Add ops addresses here without a code change."
  type        = string
  default     = "sayge.dev121@gmail.com"
}
