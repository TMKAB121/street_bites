module "vpc" {
  source  = "../../modules/vpc"
  project = var.project

  # No NAT gateway (~$33/mo plus per-GB processing). Tasks run in the public
  # subnets with public IPs instead — see the ECS service modules below. Set
  # this back to 1 and move those services onto private_subnet_ids together;
  # one without the other leaves the tasks with no route to the internet.
  nat_gateway_count = 0
}

module "ecr" {
  source = "../../modules/ecr"
  name   = var.project
}

module "s3_public" {
  source      = "../../modules/s3-public"
  bucket_name = var.s3_bucket_name
}

module "ecs_cluster" {
  source  = "../../modules/ecs-cluster"
  project = var.project
}

# Both LBs take the cert through aws_acm_certificate_validation (domain.tf)
# rather than the certificate resource directly, so their TLS listeners can't
# be created before ACM has actually issued the cert.
module "alb" {
  source            = "../../modules/alb"
  project           = var.project
  vpc_id            = module.vpc.vpc_id
  public_subnet_ids = module.vpc.public_subnet_ids
  certificate_arn   = aws_acm_certificate_validation.this.certificate_arn
}

module "reverb_lb" {
  count             = var.enable_reverb ? 1 : 0
  source            = "../../modules/reverb-lb"
  project           = var.project
  vpc_id            = module.vpc.vpc_id
  public_subnet_ids = module.vpc.public_subnet_ids
  target_port       = var.reverb_port
  certificate_arn   = aws_acm_certificate_validation.this.certificate_arn
}

module "iam_task_roles" {
  source        = "../../modules/iam-task-roles"
  project       = var.project
  s3_bucket_arn = module.s3_public.bucket_arn
  secrets_arns  = local.secrets_arns
}

# --- Per-service security groups, created here (not inside modules/ecs-service)
# so RDS/ElastiCache can allow-list them without a module dependency cycle: RDS
# needs these SG ids, while each service's env vars need RDS's endpoint. ------

resource "aws_security_group" "web" {
  name_prefix = "${var.project}-web-"
  vpc_id      = module.vpc.vpc_id

  ingress {
    from_port       = 8080
    to_port         = 8080
    protocol        = "tcp"
    security_groups = [module.alb.security_group_id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  lifecycle {
    create_before_destroy = true
  }
}

# NLBs don't carry their own security group (see modules/reverb-lb), so the
# reverb task itself is opened to 0.0.0.0/0 on its port — an accepted
# simplification (browsers use the NLB's TLS:443 listener; this port also
# carries the server-side broadcast hairpin).
resource "aws_security_group" "reverb" {
  count       = var.enable_reverb ? 1 : 0
  name_prefix = "${var.project}-reverb-"
  vpc_id      = module.vpc.vpc_id

  ingress {
    from_port   = var.reverb_port
    to_port     = var.reverb_port
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_security_group" "queue_worker" {
  name_prefix = "${var.project}-queue-worker-"
  vpc_id      = module.vpc.vpc_id

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  lifecycle {
    create_before_destroy = true
  }
}

module "rds" {
  source                     = "../../modules/rds"
  project                    = var.project
  vpc_id                     = module.vpc.vpc_id
  private_subnet_ids         = module.vpc.private_subnet_ids
  allowed_security_group_ids = [aws_security_group.web.id, aws_security_group.queue_worker.id]
  db_name                    = var.db_name
  username                   = var.db_username
  password                   = random_password.db_password.result
}

module "elasticache" {
  source                     = "../../modules/elasticache"
  project                    = var.project
  vpc_id                     = module.vpc.vpc_id
  private_subnet_ids         = module.vpc.private_subnet_ids
  allowed_security_group_ids = concat([aws_security_group.web.id, aws_security_group.queue_worker.id], aws_security_group.reverb[*].id)
}

# --- Shared env vars for all services ---------------------------------------
#
# With enable_reverb = false (the default) there is no NLB and no reverb task,
# so BROADCAST_CONNECTION falls back to `log` and the REVERB_* vars drop out
# entirely — nothing in the app subscribes to Reverb yet (resources/js/app.js
# deliberately doesn't import echo.js), so broadcasts have no listeners either way.
#
# When enable_reverb is true, REVERB_HOST points at the same public NLB DNS name
# the browser uses (no internal service-discovery/Cloud Map in this first cut) —
# the `web` and `queue-worker` containers hairpin out through the NLB to
# broadcast events. Cheaper/faster internal-only routing via AWS Cloud Map is a
# reasonable fast-follow, not a correctness requirement.
locals {
  reverb_environment = var.enable_reverb ? [
    { name = "BROADCAST_CONNECTION", value = "reverb" },
    { name = "REVERB_HOST", value = module.reverb_lb[0].dns_name },
    { name = "REVERB_PORT", value = tostring(var.reverb_port) },
    { name = "REVERB_SCHEME", value = "http" },
    ] : [
    { name = "BROADCAST_CONNECTION", value = "log" },
  ]

  base_environment = concat(local.reverb_environment, [
    { name = "APP_NAME", value = "Street Bites" },
    { name = "APP_ENV", value = "production" },
    { name = "APP_DEBUG", value = "false" },
    { name = "APP_URL", value = "https://www.${var.domain}" },
    # stderr, not stack: the stack channel writes to a file inside the
    # ephemeral container, so app exceptions never reach CloudWatch — only
    # nginx access lines do. stderr flows through the awslogs driver.
    { name = "LOG_CHANNEL", value = "stderr" },
    { name = "DB_CONNECTION", value = "mariadb" },
    { name = "DB_HOST", value = module.rds.endpoint },
    { name = "DB_PORT", value = tostring(module.rds.port) },
    { name = "DB_DATABASE", value = var.db_name },
    { name = "DB_USERNAME", value = var.db_username },
    { name = "REDIS_CLIENT", value = "phpredis" },
    { name = "REDIS_HOST", value = module.elasticache.primary_endpoint },
    { name = "REDIS_PORT", value = tostring(module.elasticache.port) },
    { name = "CACHE_STORE", value = "redis" },
    { name = "SESSION_DRIVER", value = "redis" },
    { name = "SESSION_SECURE_COOKIE", value = "true" }, # the ALB serves HTTPS-only (HTTP:80 just redirects)
    { name = "QUEUE_CONNECTION", value = "redis" },
    { name = "MAIL_MAILER", value = "resend" }, # Resend (AWS denied SES production access) — RESEND_API_KEY rides in via task_secrets; the sending domain is verified in the Resend dashboard, not here
    { name = "MAIL_FROM_ADDRESS", value = local.mail_from_address },
    { name = "FILESYSTEM_PUBLIC_DISK", value = "s3" },
    { name = "AWS_DEFAULT_REGION", value = var.aws_region },
    { name = "AWS_BUCKET", value = module.s3_public.bucket_name },
    { name = "AWS_URL", value = "https://${module.s3_public.bucket_regional_domain_name}" },
    # Content moderation: the admin allowlist (/admin/trucks) and image screening
    # via Rekognition. The task role's rekognition:DetectModerationLabels grant is
    # in modules/iam-task-roles; region falls back to AWS_DEFAULT_REGION above.
    # Text screening (a word-list) always runs and needs nothing here.
    { name = "ADMIN_EMAILS", value = var.admin_emails },
    { name = "MODERATION_REKOGNITION_ENABLED", value = "true" },
  ])
}

# All services run in the *public* subnets with public IPs — the NAT gateway is
# gone for cost (modules/vpc, nat_gateway_count = 0), so this is how tasks reach
# ECR, Secrets Manager, Resend, and OSM. A public IP is not public reach:
# inbound is still governed by the per-service security groups above (web admits
# only the ALB; queue-worker admits nothing at all). RDS and ElastiCache stay in
# the private subnets, unreachable from the internet.
module "web" {
  source                = "../../modules/ecs-service"
  project               = var.project
  service_name          = "web"
  cluster_arn           = module.ecs_cluster.cluster_arn
  cluster_name          = module.ecs_cluster.cluster_name
  vpc_id                = module.vpc.vpc_id
  subnet_ids            = module.vpc.public_subnet_ids
  assign_public_ip      = true
  security_group_id     = aws_security_group.web.id
  container_image       = "${module.ecr.repository_url}:${var.image_tag}"
  container_port        = 8080
  execution_role_arn    = module.iam_task_roles.execution_role_arn
  task_role_arn         = module.iam_task_roles.task_role_arn
  environment_variables = local.base_environment
  secrets               = local.task_secrets
  desired_count         = 1
  log_retention_days    = 7
  target_group_arn      = module.alb.target_group_arn
  enable_autoscaling    = true
  min_capacity          = 1
  max_capacity          = 3
}

module "reverb" {
  count                 = var.enable_reverb ? 1 : 0
  source                = "../../modules/ecs-service"
  project               = var.project
  service_name          = "reverb"
  cluster_arn           = module.ecs_cluster.cluster_arn
  cluster_name          = module.ecs_cluster.cluster_name
  vpc_id                = module.vpc.vpc_id
  subnet_ids            = module.vpc.public_subnet_ids
  assign_public_ip      = true
  security_group_id     = aws_security_group.reverb[0].id
  container_image       = "${module.ecr.repository_url}:${var.image_tag}"
  container_command     = ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=${var.reverb_port}"]
  container_port        = var.reverb_port
  execution_role_arn    = module.iam_task_roles.execution_role_arn
  task_role_arn         = module.iam_task_roles.task_role_arn
  environment_variables = local.base_environment
  secrets               = local.task_secrets
  desired_count         = 1
  log_retention_days    = 7
  target_group_arn      = module.reverb_lb[0].target_group_arn
}

# Interruption-tolerant (a reclaimed job is retried), so this one runs on Spot.
module "queue_worker" {
  source                = "../../modules/ecs-service"
  project               = var.project
  service_name          = "queue-worker"
  cluster_arn           = module.ecs_cluster.cluster_arn
  cluster_name          = module.ecs_cluster.cluster_name
  vpc_id                = module.vpc.vpc_id
  subnet_ids            = module.vpc.public_subnet_ids
  assign_public_ip      = true
  security_group_id     = aws_security_group.queue_worker.id
  container_image       = "${module.ecr.repository_url}:${var.image_tag}"
  container_command     = ["php", "artisan", "queue:work", "redis", "--tries=3"]
  execution_role_arn    = module.iam_task_roles.execution_role_arn
  task_role_arn         = module.iam_task_roles.task_role_arn
  environment_variables = local.base_environment
  secrets               = local.task_secrets
  desired_count         = 1
  log_retention_days    = 7
  use_fargate_spot      = true
}
