output "state_bucket_name" {
  description = "The shared state bucket — the `bucket` in every sibling repo's backend.tf."
  value       = aws_s3_bucket.terraform_state.bucket
}

output "lock_table_name" {
  description = "The shared lock table — the `dynamodb_table` in every sibling repo's backend.tf."
  value       = aws_dynamodb_table.terraform_locks.name
}
