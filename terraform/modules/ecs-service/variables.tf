variable "project" {
  type = string
}

variable "service_name" {
  description = "web | reverb | queue-worker — matches the Lando service split in .lando.yml."
  type        = string
}

variable "cluster_arn" {
  type = string
}

variable "cluster_name" {
  description = "Needed (in addition to cluster_arn) for the appautoscaling target's resource_id."
  type        = string
}

variable "vpc_id" {
  type = string
}

# Named generically because these are the *public* subnets in prod: with the
# NAT gateway removed for cost, tasks get public IPs and reach ECR/Secrets/
# Resend through the internet gateway instead. Inbound is still closed —
# security groups admit only the ALB (see environments/prod/main.tf).
variable "subnet_ids" {
  type = list(string)
}

variable "cpu" {
  type    = string
  default = "256"
}

variable "memory" {
  type    = string
  default = "512"
}

variable "container_image" {
  description = "ECR image URI including tag — same image for all three services, differing only in command."
  type        = string
}

variable "container_command" {
  description = "Overrides the image's default CMD. null = use the image's default (nginx+php-fpm via supervisord, the `web` case)."
  type        = list(string)
  default     = null
}

variable "container_port" {
  description = "null for queue-worker (no listener at all)."
  type        = number
  default     = null
}

variable "execution_role_arn" {
  type = string
}

variable "task_role_arn" {
  type = string
}

variable "environment_variables" {
  type    = list(object({ name = string, value = string }))
  default = []
}

variable "secrets" {
  description = "Secrets Manager-backed env vars — never plaintext in the task definition (APP_KEY, DB_PASSWORD, Reverb credentials)."
  type        = list(object({ name = string, valueFrom = string }))
  default     = []
}

variable "desired_count" {
  type    = number
  default = 1
}

variable "target_group_arn" {
  description = "null = no load balancer attachment (queue-worker). Set for web (ALB) and reverb (NLB)."
  type        = string
  default     = null
}

variable "security_group_id" {
  description = "Created in environments/prod (not by this module) so RDS/ElastiCache can reference it as an allowed source without creating a dependency cycle (this module's env vars, in turn, depend on the RDS/ElastiCache endpoints)."
  type        = string
}

variable "assign_public_ip" {
  description = "Required when tasks run in public subnets with no NAT gateway — without it they can't pull the ECR image or reach Secrets Manager."
  type        = bool
  default     = false
}

variable "use_fargate_spot" {
  description = "Run on FARGATE_SPOT (~70% cheaper) instead of on-demand. Safe for interruption-tolerant work (queue-worker: AWS gives 2 minutes' notice and jobs retry); leave false for user-facing services."
  type        = bool
  default     = false
}

variable "enable_autoscaling" {
  type    = bool
  default = false
}

variable "min_capacity" {
  type    = number
  default = 1
}

variable "max_capacity" {
  type    = number
  default = 1
}

variable "cpu_target" {
  description = "Target CPU utilization % for the autoscaling policy, when enabled."
  type        = number
  default     = 70
}

variable "log_retention_days" {
  type    = number
  default = 14
}
