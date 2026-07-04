output "state_bucket_name" {
  description = "Feed into environments/prod/backend.tf's `bucket`."
  value       = aws_s3_bucket.terraform_state.bucket
}

output "lock_table_name" {
  description = "Feed into environments/prod/backend.tf's `dynamodb_table`."
  value       = aws_dynamodb_table.terraform_locks.name
}

output "gha_terraform_role_arn" {
  description = "Set as the AWS_TERRAFORM_ROLE_ARN GitHub Actions variable, used by terraform-plan.yml / terraform-apply.yml."
  value       = aws_iam_role.gha_terraform.arn
}

output "gha_deploy_role_arn" {
  description = "Set as the AWS_DEPLOY_ROLE_ARN GitHub Actions variable, used by release-deploy.yml."
  value       = aws_iam_role.gha_deploy.arn
}
