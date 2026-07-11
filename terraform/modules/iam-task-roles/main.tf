data "aws_iam_policy_document" "ecs_tasks_assume" {
  statement {
    effect  = "Allow"
    actions = ["sts:AssumeRole"]
    principals {
      type        = "Service"
      identifiers = ["ecs-tasks.amazonaws.com"]
    }
  }
}

# --- Execution role: how the ECS agent pulls the image and starts the task --
# Never touched by the app itself at runtime.
resource "aws_iam_role" "execution" {
  name               = "${var.project}-ecs-execution"
  assume_role_policy = data.aws_iam_policy_document.ecs_tasks_assume.json
}

resource "aws_iam_role_policy_attachment" "execution_managed" {
  role       = aws_iam_role.execution.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

resource "aws_iam_role_policy" "execution_secrets" {
  name = "${var.project}-ecs-execution-secrets"
  role = aws_iam_role.execution.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect   = "Allow"
      Action   = "secretsmanager:GetSecretValue"
      Resource = var.secrets_arns
    }]
  })
}

# --- Task role: what the running Laravel app itself is allowed to do --------
# S3 access for the public-storage bucket and Rekognition image screening —
# the AWS SDK's default credential chain picks this role up automatically, so
# AWS_ACCESS_KEY_ID/SECRET stay blank in the app's env (config/filesystems.php's
# `s3` disk). Mail is Resend (an external API key via Secrets Manager), so the
# task role carries no mail permissions.
resource "aws_iam_role" "task" {
  name               = "${var.project}-ecs-task"
  assume_role_policy = data.aws_iam_policy_document.ecs_tasks_assume.json
}

resource "aws_iam_role_policy" "task_s3" {
  name = "${var.project}-ecs-task-s3"
  role = aws_iam_role.task.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect   = "Allow"
      Action   = ["s3:GetObject", "s3:PutObject", "s3:DeleteObject", "s3:ListBucket"]
      Resource = [var.s3_bucket_arn, "${var.s3_bucket_arn}/*"]
    }]
  })
}

# Content-moderation image screening: App\Actions\ScreenImage calls Rekognition
# DetectModerationLabels via the SDK credential chain (same as S3 above),
# gated by MODERATION_REKOGNITION_ENABLED. Rekognition's image operations don't
# support resource-level permissions, so Resource must be "*".
resource "aws_iam_role_policy" "task_rekognition" {
  name = "${var.project}-ecs-task-rekognition"
  role = aws_iam_role.task.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect   = "Allow"
      Action   = ["rekognition:DetectModerationLabels"]
      Resource = "*"
    }]
  })
}
