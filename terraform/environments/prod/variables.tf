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
