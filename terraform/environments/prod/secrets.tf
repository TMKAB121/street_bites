# Generated once by Terraform and stored in Secrets Manager — never plaintext
# in the task definitions. Injected into containers via the `secrets` block
# (see modules/ecs-service), resolved by the ECS agent using the execution
# role's secretsmanager:GetSecretValue grant (modules/iam-task-roles).

resource "random_password" "db_password" {
  length  = 32
  special = false
}

# Laravel's APP_KEY is `base64:` + 32 random bytes, base64-encoded.
resource "random_id" "app_key" {
  byte_length = 32
}

resource "random_id" "reverb_app_id" {
  byte_length = 8
}

resource "random_password" "reverb_app_key" {
  length  = 32
  special = false
}

resource "random_password" "reverb_app_secret" {
  length  = 32
  special = false
}

resource "aws_secretsmanager_secret" "app_key" {
  name = "${var.project}/app-key"
}

resource "aws_secretsmanager_secret_version" "app_key" {
  secret_id     = aws_secretsmanager_secret.app_key.id
  secret_string = "base64:${random_id.app_key.b64_std}"
}

resource "aws_secretsmanager_secret" "db_password" {
  name = "${var.project}/db-password"
}

resource "aws_secretsmanager_secret_version" "db_password" {
  secret_id     = aws_secretsmanager_secret.db_password.id
  secret_string = random_password.db_password.result
}

resource "aws_secretsmanager_secret" "reverb_app_id" {
  name = "${var.project}/reverb-app-id"
}

resource "aws_secretsmanager_secret_version" "reverb_app_id" {
  secret_id     = aws_secretsmanager_secret.reverb_app_id.id
  secret_string = random_id.reverb_app_id.hex
}

resource "aws_secretsmanager_secret" "reverb_app_key" {
  name = "${var.project}/reverb-app-key"
}

resource "aws_secretsmanager_secret_version" "reverb_app_key" {
  secret_id     = aws_secretsmanager_secret.reverb_app_key.id
  secret_string = random_password.reverb_app_key.result
}

resource "aws_secretsmanager_secret" "reverb_app_secret" {
  name = "${var.project}/reverb-app-secret"
}

resource "aws_secretsmanager_secret_version" "reverb_app_secret" {
  secret_id     = aws_secretsmanager_secret.reverb_app_secret.id
  secret_string = random_password.reverb_app_secret.result
}

locals {
  secrets_arns = [
    aws_secretsmanager_secret.app_key.arn,
    aws_secretsmanager_secret.db_password.arn,
    aws_secretsmanager_secret.reverb_app_id.arn,
    aws_secretsmanager_secret.reverb_app_key.arn,
    aws_secretsmanager_secret.reverb_app_secret.arn,
  ]

  task_secrets = [
    { name = "APP_KEY", valueFrom = aws_secretsmanager_secret.app_key.arn },
    { name = "DB_PASSWORD", valueFrom = aws_secretsmanager_secret.db_password.arn },
    { name = "REVERB_APP_ID", valueFrom = aws_secretsmanager_secret.reverb_app_id.arn },
    { name = "REVERB_APP_KEY", valueFrom = aws_secretsmanager_secret.reverb_app_key.arn },
    { name = "REVERB_APP_SECRET", valueFrom = aws_secretsmanager_secret.reverb_app_secret.arn },
  ]
}
