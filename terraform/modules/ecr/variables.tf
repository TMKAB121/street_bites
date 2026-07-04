variable "name" {
  description = "ECR repository name. All three ECS services (web/reverb/queue-worker) pull from this one repo."
  type        = string
}
