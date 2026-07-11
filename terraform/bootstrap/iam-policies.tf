data "aws_caller_identity" "current" {}

# --- gha-terraform-role permissions -----------------------------------------
#
# Broad, service-level create/update/delete rights so `terraform apply` can
# stand up the VPC/ECS/RDS/ElastiCache/ECR/ALB/IAM resources in
# environments/prod. Resource-level scoping isn't practical here (most ARNs
# don't exist until this role creates them); S3 state access and DynamoDB
# locking are the two places a real ARN is known up front, so those are
# scoped tightly instead of left wildcard.
resource "aws_iam_role_policy" "gha_terraform" {
  name = "${var.project}-gha-terraform"
  role = aws_iam_role.gha_terraform.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid    = "TerraformStateBackend"
        Effect = "Allow"
        Action = ["s3:GetObject", "s3:PutObject", "s3:DeleteObject", "s3:ListBucket"]
        Resource = [
          aws_s3_bucket.terraform_state.arn,
          "${aws_s3_bucket.terraform_state.arn}/*",
        ]
      },
      {
        Sid      = "TerraformStateLock"
        Effect   = "Allow"
        Action   = ["dynamodb:GetItem", "dynamodb:PutItem", "dynamodb:DeleteItem"]
        Resource = aws_dynamodb_table.terraform_locks.arn
      },
      {
        # iam:* and s3:* here, not a hand-picked action list: the AWS
        # provider's refresh step calls many more Get/List/Describe actions
        # than any curated list anticipates (e.g. iam:ListRolePolicies,
        # s3:GetAccelerateConfiguration) — trying to enumerate them
        # individually just produces a slow trickle of new AccessDenied
        # errors as the provider version changes. Matches the same
        # philosophy already applied to ec2/ecs/rds/elasticache below.
        Sid    = "InfrastructureLifecycle"
        Effect = "Allow"
        Action = [
          "ec2:*",
          "ecs:*",
          "ecr:*",
          "rds:*",
          "elasticache:*",
          "elasticloadbalancing:*",
          "application-autoscaling:*",
          "logs:*",
          "secretsmanager:*",
          "s3:*",
          "iam:*",
          "acm:*", # the domain cutover's certificate (environments/prod/domain.tf)
          "ses:*", # legacy: mail moved to Resend (SES production access denied); kept so the apply that removed the SES identity could run — safe to drop on a future bootstrap apply
        ]
        Resource = "*"
      }
    ]
  })
}

# --- gha-deploy-role permissions ---------------------------------------------
#
# Deliberately narrow: this role only ever builds/pushes an image and rolls
# out an already-provisioned ECS service. It can never touch the VPC, RDS, or
# create new IAM roles — infra lifecycle is `gha-terraform-role`'s job alone.
resource "aws_iam_role_policy" "gha_deploy" {
  name = "${var.project}-gha-deploy"
  role = aws_iam_role.gha_deploy.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid      = "EcrAuth"
        Effect   = "Allow"
        Action   = "ecr:GetAuthorizationToken"
        Resource = "*"
      },
      {
        Sid    = "EcrPush"
        Effect = "Allow"
        Action = [
          "ecr:BatchCheckLayerAvailability",
          "ecr:GetDownloadUrlForLayer",
          "ecr:InitiateLayerUpload",
          "ecr:UploadLayerPart",
          "ecr:CompleteLayerUpload",
          "ecr:PutImage",
        ]
        Resource = "arn:aws:ecr:${var.aws_region}:${data.aws_caller_identity.current.account_id}:repository/${var.project}*"
      },
      {
        Sid    = "EcsDeploy"
        Effect = "Allow"
        Action = [
          "ecs:RegisterTaskDefinition",
          "ecs:DescribeTaskDefinition",
          "ecs:DescribeServices",
          "ecs:UpdateService",
          "ecs:RunTask",
          "ecs:DescribeTasks",
          "ecs:StopTask",
        ]
        Resource = "*"
      },
      {
        Sid      = "PassTaskRoles"
        Effect   = "Allow"
        Action   = "iam:PassRole"
        Resource = "arn:aws:iam::${data.aws_caller_identity.current.account_id}:role/${var.project}-*"
      }
    ]
  })
}
