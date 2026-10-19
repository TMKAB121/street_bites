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
